# FreshCart Integrations

Connects the FreshCart Bagisto store with **Akaunting** (accounting) and **NovaCore HR**, the modified Frappe HR
(<https://github.com/jayvhen/FrappeHR>). Bagisto is the hub: it pushes accounting entries to Akaunting, and it
receives employees and payroll totals from NovaCore HR through NovaCore's own integration contract (`novacore/API.md`
in that repository).

| Flow | Trigger in the source system | Result |
|---|---|---|
| Sales → Akaunting | Bagisto invoice created | Akaunting customer (by e-mail) + **invoice** + income **payment** marking it paid |
| Refunds → Akaunting | Bagisto refund created | Akaunting **expense** transaction to the customer (category "refunds") |
| Suppliers → Akaunting | FreshCart supplier created / edited / deleted | Akaunting **vendor** created or updated (disabled when deleted) |
| Receiving → Akaunting | FreshCart goods receipt posted | Akaunting **bill**: accepted qty × PO unit cost, due date from the supplier's payment terms ("Net 30" → +30 days) |
| Supplier credits → Akaunting | Supplier return resolved with a credit amount | Akaunting **income** transaction from the vendor |
| NovaCore HR → Bagisto | `employee.created` / `employee.updated` / `employee.separated` | Bagisto **admin user** created, updated or disabled; role from designation, location from branch |
| Payroll → Akaunting | `payroll.released` (payroll approved and slips submitted) | Akaunting **expense** for total net pay + **bills** to SSS, PhilHealth, Pag-IBIG (employee + employer + EC shares) and BIR (withholding tax) |

## How it works

* **Outbox.** Bagisto events and FreshCart model observers write a row to `fc_integration_outbox` in the same database
  transaction as the invoice, receipt or return. Checkout and receiving never wait for Akaunting and never fail
  because it is down.
* **Push.** The scheduler runs `fc:integrations:push` every minute. Failures are kept on the row with the error and
  retried with backoff (1, 2, 4 … 60 minutes, up to `FC_SYNC_MAX_ATTEMPTS`, default 10).
* **Idempotent.** Every Akaunting id is stored in `fc_integration_links`. A retried message, or a duplicated NovaCore
  delivery, never creates a second invoice, bill or transaction.
* **NovaCore HR in.** Signed webhooks from an *ERP Event Subscription* give near-real-time updates.
  `fc:integrations:pull-frappe` runs every 15 minutes as a safety net and also does the initial import. It uses
  `get_employees?modified_since=` and `list_payroll_runs` / `get_payroll_summary`. Both paths share one code path.
* **Privacy.** NovaCore only exposes aggregated payroll (RA 10173), so Akaunting receives totals per payroll run,
  never one employee's pay.
* **Amounts** are in the store's base currency with rate 1. The Akaunting company currency must equal Bagisto's base
  currency (PHP).

## Setup

### 1. Network (AWS security groups)

| From | To | Port | Why |
|---|---|---|---|
| Bagisto 34.193.191.155 | Akaunting 3.230.237.192 | 80 / 443 | push accounting entries |
| Bagisto 34.193.191.155 | NovaCore HR 34.195.44.138 | 80 / 443 | pull employees and payroll |
| NovaCore HR 34.195.44.138 | Bagisto 34.193.191.155 | **443** | webhooks |

NovaCore's ERP Event Subscription accepts only **https://** endpoint URLs, unless the site runs in developer mode.
Without a domain, Bagisto can still get a free certificate by using the IP's sslip.io name. Set this in Bagisto's
`.env`:

```dotenv
SITE_ADDRESS=34-193-191-155.sslip.io
APP_URL=https://34-193-191-155.sslip.io
```

Caddy (already in `docker-compose.prod.yml`) obtains the certificate automatically once ports 80 and 443 are open to
the internet. Without HTTPS, skip the webhooks: the 15-minute pull keeps everything in sync.

### 2. Akaunting

1. Create a user, e.g. `api@freshcart…`, whose role has the **read-api** permission (the Admin role has it). Give it
   access to the FreshCart company.
2. Note the ids (shown in the URL when editing each record) of:
   * the **company**, and the bank/cash **account** that receives and pays money;
   * **categories**:
     * income category for sales;
     * expense categories for refunds, purchases, payroll (net pay) and statutory contributions;
     * income category for supplier credits.
3. If you use a payment method other than the default cash method, set `AKAUNTING_PAYMENT_METHOD`
   (Settings → Offline Payments shows its code, e.g. `offline-payments.bank_transfer.2`).

The vendors **SSS**, **PhilHealth**, **Pag-IBIG** and **BIR** are created automatically on the first payroll run, or
reused if vendors with those exact names exist.

### 3. NovaCore HR (as described in its API.md)

1. Create a user, e.g. `freshcart.integration@…`, with the roles **ERP Integration** and **ERP Finance
   Integration**. Under *Settings → API Access → Generate Keys*, copy the key and secret.
2. Create an **ERP Event Subscription**:
   * System name: `FreshCart`
   * Endpoint URL: `https://34-193-191-155.sslip.io/integrations/novacore-hr`
   * Secret: a long random string, also put in `FRAPPE_WEBHOOK_SECRET`
   * Events: employee created, employee updated, employee separated, payroll released
3. Press **Send Test Event**. Bagisto answers `{"result":"pong"}`, and *Integration Request* in NovaCore logs the call.

Requests with a wrong `X-NovaCore-Signature` are rejected with 401. If `FRAPPE_WEBHOOK_SECRET` is empty, the endpoint
returns 503.

### 4. Bagisto `.env` (on the Bagisto server)

```dotenv
AKAUNTING_URL=http://3.230.237.192
AKAUNTING_EMAIL=api@freshcart.example
AKAUNTING_PASSWORD=...
AKAUNTING_COMPANY_ID=1
AKAUNTING_ACCOUNT_ID=1
AKAUNTING_CATEGORY_SALES=3
AKAUNTING_CATEGORY_REFUNDS=4
AKAUNTING_CATEGORY_PURCHASES=5
AKAUNTING_CATEGORY_SUPPLIER_CREDITS=6
AKAUNTING_CATEGORY_PAYROLL=7
AKAUNTING_CATEGORY_PAYROLL_STATUTORY=8
AKAUNTING_STATUTORY_DUE_DAYS=30

FRAPPE_URL=http://34.195.44.138
FRAPPE_API_KEY=...
FRAPPE_API_SECRET=...
FRAPPE_WEBHOOK_SECRET=...                     # same value as the ERP Event Subscription secret
FRAPPE_COMPANY=                               # optional: only import payroll of this company
FRAPPE_WITHHOLDING_COMPONENT="Withholding Tax"
FRAPPE_DEFAULT_ROLE="FreshCart Warehouse Personnel"
FRAPPE_ROLE_MAP={"Store Manager":"FreshCart Store Manager","Purchasing Officer":"FreshCart Purchasing Officer"}
FRAPPE_BRANCH_MAP={"Makati":"makati"}         # NovaCore branch => Bagisto inventory source code
```

Role names must match Bagisto roles exactly (`php artisan fc:install-roles` creates the FreshCart set). Then:

```bash
docker compose up -d --build                  # migrations run on start; the scheduler container runs push/pull
docker compose exec app php artisan fc:integrations:status
docker compose exec app php artisan fc:integrations:pull-frappe --full    # first import of employees and payroll runs
docker compose exec app php artisan fc:integrations:backfill --since=2026-11-01   # optional: older store records
```

## Commands

| Command | Purpose |
|---|---|
| `fc:integrations:status` | Configuration, connectivity, outbox counts and the latest errors |
| `fc:integrations:push [--limit=]` | Push due outbox messages now (scheduled every minute) |
| `fc:integrations:pull-frappe [--only=employees\|payroll] [--full]` | Pull changes from NovaCore HR (scheduled every 15 minutes) |
| `fc:integrations:retry [id …]` | Re-queue failed messages, including ones that used up their attempts |
| `fc:integrations:backfill [--since=Y-m-d]` | Queue store records created before the integration was installed |

Each flow can be switched off with `FC_SYNC_SALES`, `FC_SYNC_PROCUREMENT`, `FC_SYNC_EMPLOYEES` and `FC_SYNC_PAYROLL`.

## Employee rules

* The e-mail is NovaCore's `work_email` (company e-mail, else the user id). Employees without one are skipped and
  reported by the pull command.
* An employee gets an enabled admin when **status is Active** and the **employment stage is not Suspended or
  Separated**, the same rule as NovaCore's `is_active_employee`. Anyone else is disabled. That includes
  `employee.separated`, which NovaCore says must revoke access. Admins are never deleted, so approval history keeps
  its names.
* New admins get a random password and set their own through **Forgot password** on the admin login page.
* An existing admin with the same e-mail is adopted. An admin with a **full-access role** is only linked and never
  changed, so an HR record cannot lock out the system administrator.
* Locations are reassigned only when the employee's branch appears in `FRAPPE_BRANCH_MAP`. Otherwise the manual
  assignment under Operations → Setup → Locations is kept.

## Adapting the mapping

All mapping lives in `src/Services/AkauntingSync.php` (one method per flow) and `src/Services/EmployeeSync.php`.

## Known limitations

* Akaunting has no credit notes or debit notes in its free core, so refunds and supplier credits post as standalone
  transactions, not as notes against the original document.
* Taxes post as a "Tax" line on the Akaunting invoice, not as Akaunting tax rates. Line prices are net of item
  discounts.
* Payroll deductions other than SSS / PhilHealth / Pag-IBIG / BIR (loans, advances …) are not posted separately.
  They are already excluded from net pay.
* Cancelling or editing a Bagisto invoice after it was pushed is not mirrored. Adjust it in Akaunting.
* NovaCore can also accept sales commissions from Bagisto (`post_incentives`). That flow is not implemented, because
  Bagisto orders are not attributed to employees.
