# FreshCart Operations (Bagisto 2.4.8 extension)

Supermarket operations for FreshCart Supermarket Corp. on top of Bagisto 2.4.8 / Laravel 12:
procurement and receiving, branch replenishment, inter-branch transfers, damage/expiry/returns,
promotions and bulk pricing, retirement and disposal, role dashboards and reports.

It is a self-contained package (`packages/FreshCart/Operations`, namespace `FreshCart\Operations`).
No file inside `packages/Webkul` was changed.

## Installation

The package is already registered in this repository:

| File | Change |
|---|---|
| `composer.json` | PSR-4 `FreshCart\Operations\` (src) and `FreshCart\Operations\Tests\` (tests) |
| `bootstrap/providers.php` | `OperationsServiceProvider` |
| `config/concord.php` | `FreshCart\Operations\Providers\ModuleServiceProvider` |
| `phpunit.xml` | test suite "FreshCart Operations Feature Test" |
| `tests/Pest.php` | binds `OperationsTestCase` |

```bash
composer dump-autoload
php artisan migrate                      # creates the fc_* tables only
php artisan optimize:clear
php artisan fc:install-roles             # optional: creates the FreshCart roles (see below)
```

Then, in the admin panel:

1. **Settings → Inventory Sources**: create one source per location (Central Warehouse, Calapan Flagship,
   Pinamalayan, San Jose, Batangas City, Puerto Princesa) and attach them to the channel.
2. **Operations → Setup → Locations**: mark warehouses, choose the central warehouse, tick which users work where.
3. **Operations → Setup → Suppliers** and **Reorder Policies** (CSV import available).
4. **Configure → Operations**: approval thresholds, self-approval policy, receiving tolerance, low-stock defaults,
   transfer approval, retirement criteria, promotion limits, e-mail notifications.
5. **Settings → Users**: give each user one of the FreshCart roles.

Rollback (removes only FreshCart tables; data in them is lost):

```bash
php artisan migrate:rollback --path=packages/FreshCart/Operations/src/Database/Migrations
```

Inventory sources referenced by FreshCart documents cannot be deleted (foreign keys `RESTRICT`)
so audit history is never orphaned. Deactivate the source instead.

## Branding

The system is branded **FreshCart** without editing Bagisto core files:

* `.env`: `APP_NAME=FreshCart`, `VITE_APP_NAME=FreshCart`, `MAIL_FROM_NAME="FreshCart Supermarket"`.
* `php artisan fc:apply-branding [--name="FreshCart Supermarket"]` copies the logo and favicon from
  `src/Resources/branding/` to `storage/app/public/freshcart/`. It sets them as the admin logo and favicon
  (Configure → General → Design) and as every channel's storefront logo and favicon. It also renames the channels and
  sets the home-page SEO title. Re-run it after a fresh install. Replace the SVGs (or upload new ones in the admin)
  to change the artwork.
* `lang/vendor/admin/en/app.php` and `lang/vendor/shop/en/app.php` replace the "Powered by Bagisto / Webkul"
  footers and logo labels (Laravel merges them over the core English strings).

## Supermarket catalog

`php artisan fc:seed-grocery-catalog --replace` replaces Bagisto's demo catalog (fashion, electronics, bookings) with
the FreshCart assortment in `src/Resources/data/grocery-catalog.php`. The assortment is 31 categories (produce, meat
and seafood, dairy and eggs, bakery, rice and noodles, pantry, snacks, beverages, frozen, household, personal care,
baby) and 99 simple products with PHP prices and costs. The command:

* creates the products through Bagisto's product repository and events, so they are indexed like admin-created products;
* books opening stock through the FreshCart ledger. Every active location gets stock (branches get one third of the
  warehouse quantity), with batch `OPEN-YYYYMMDD` and an expiry date from each product's shelf life;
* creates a reorder policy per product and location: reorder at 25% of opening stock, minimum 10%, maximum 200%;
* switches the homepage fashion banners off and turns the product carousels into Fresh Produce, Pantry Staples,
  Beverages and Household Essentials. It also updates the offer strip and the service highlights.

It refuses to run when orders exist unless `--force` is given. **Back up the database first**: with `--replace`,
every existing product, product image and category (except the root) is deleted. Product photos are not included,
so products show Bagisto's placeholder image until photos are uploaded in Catalog → Products.

## Scheduler and queue

All scheduled work runs through one command, also usable manually:

```bash
php artisan fc:monitor all            # or: low-stock | transfers | purchase-orders | promotions | price-changes | retirement
```

| Schedule | Check |
|---|---|
| hourly | low-stock digest per location (per-product cooldown), overdue transfers / temporary returns, promotion activation, expiry and expiry warnings |
| every 15 min | apply approved price-change batches whose effective time has arrived |
| daily 07:00 | overdue purchase-order deliveries |
| daily 02:00 | refresh the retirement review list |

Production cron (Laravel scheduler): `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`.
Development: `php artisan schedule:work`. The queue connection is `sync` in `.env`; with a real queue, run
`php artisan queue:work` (Bagisto index jobs and notification e-mails are queued).

Set `APP_TIMEZONE=Asia/Manila` in `.env` so date-based rules (expiry, promotions, overdue) use local time
(it is currently `Asia/Kolkata`).

## How stock works

* **Sellable stock** remains Bagisto's `product_inventories`, so checkout, order reservations, shipments and the
  storefront inventory index behave exactly as before.
* **Held stock** (quarantine, damaged, expired, awaiting supplier return, in transit) lives in `fc_stock_holds`.
  Moving units into a hold removes them from `product_inventories` in the same transaction. They therefore cannot be
  sold or reserved.
* **Batches** (`fc_stock_lots`) record batch number, expiry and unit cost from receiving and are consumed
  first-expiry-first-out. They travel with transfers.
* **Ledger** (`fc_stock_movements`) is append-only: actor, timestamp, location, quantity, sellable balance after,
  bucket from/to, unit cost, source document and reason. Stock changes made by Bagisto itself (shipments, product
  edits) are logged too, as movement type `external`.
* `Services/StockLedgerService` is the only code that changes stock. Every operation runs in a DB transaction,
  locks the affected `product_inventories` rows, refuses negative stock, and refuses to move units that open orders
  have reserved.
* Documents never move stock by themselves. Stock changes only at goods receipt, dispatch, transfer receipt, incident
  quarantine/classification, posting an approved adjustment, and completing an approved disposal.

## Workflows

| Module | Flow |
|---|---|
| A Procurement | PR draft → submit → approval (2nd level above threshold) → PO from approved lines → issue (PDF) → goods receipt(s) with batch/expiry/inspection, partial deliveries, over-receipt control, barcode scan-to-count → rejected units raise a supplier return |
| B Replenishment | Branch request → warehouse allocation screen (requested / available / committed / min / suggested; overrides need a reason) → approved replenishment transfer → pick/pack (picking list PDF) → dispatch → branch receipt; remainder stays on backorder |
| C Transfers | Inter-branch / temporary / emergency: draft → (approval if configured) → picking → dispatch → partial/complete receipt with damaged units and proof of delivery → shortages closed as transit-loss adjustments; logistics schedule, driver, vehicle; temporary-transfer return leg and overdue monitoring |
| D Quality | Report (photos) → immediate quarantine → inspection checklist → classification: saleable (released), return to supplier (supplier return), damaged/expired (adjustment that needs approval, then posting) |
| E Pricing | Promotion proposal → approval → native Bagisto catalog rule with the approved dates → automatic activation/expiry; CSV bulk price change validated row by row → approval → applied at the effective time with price history |
| F Retirement | Review list (expired / near-expiry batches, held damaged/expired stock, slow movers) → nominate → inspection → finance valuation → approval → segregation into quarantine → completion with mandatory evidence (PDF record) |

All approvals: the server re-checks permission, location, document status (row lock) and blocks self-approval
(unless configured) and one person signing both levels. Decisions are stored in append-only `fc_approvals`.
Duplicate form submissions (receipts, dispatch, transfer receipts) are rejected by idempotency keys.

## Roles (`php artisan fc:install-roles`)

| Role | Main permissions |
|---|---|
| FreshCart System Administrator | everything (`permission_type = all`) |
| FreshCart Management | all approvals incl. level 2, all reports, all locations |
| FreshCart Purchasing Officer | suppliers, purchase requests, purchase orders, supplier returns, procurement reports |
| FreshCart Inventory Manager | reorder policies, allocation, transfer approval, inspection/classification, adjustments (create/approve/post), disposal nomination/inspection |
| FreshCart Warehouse Personnel | receiving, picking, dispatch, transfer receipt, incident reporting/inspection, disposal execution (assigned locations only) |
| FreshCart Store Manager | branch requests, transfer requests and receipts, incidents, adjustment drafts, branch reports (assigned branch only) |
| FreshCart Logistics | delivery scheduling and dispatch |
| FreshCart Marketing | promotion proposals, price change batches, sales reports |
| FreshCart Finance | valuation, adjustment and disposal reports, disposal valuation |

Users without "Operations → Data Scope → All Locations" only see documents and report rows for the
locations assigned to them. Every route is mapped in `Config/acl.php` and enforced by Bagisto's Bouncer middleware.

## Tests

```bash
vendor/bin/pest --testsuite="FreshCart Operations Feature Test"
```

Tests run inside database transactions on the configured database and roll back.

## Pain point traceability

| ID | Addressed by |
|---|---|
| P1 | Electronic PR approval with thresholds, approval queue (Inbox), notifications, append-only history |
| P2 | Goods receipts against POs with inspection, batch/expiry, partial receipts, over-receipt control, duplicate protection, barcode scan-to-count |
| P3 | Branch requests and rule-based allocation suggestions with recorded overrides and backorders |
| P4 | Transfers moving stock only at dispatch/receipt through the locked ledger; discrepancies and transit-loss adjustments |
| P5 | Delivery schedule, responsible staff, driver/vehicle, expected arrival, overdue monitoring, dispatch note with QR |
| P6 | Incident reporting with photos, immediate quarantine, inspection checklist, approval-controlled adjustments |
| P7 | Promotion proposals that become native catalog rules with scheduled activation/expiry; validated bulk price changes |
| P8 | Reorder policies, hourly low-stock digests with cooldown, low-stock report and dashboards |
| P9 | Cross-branch sales by fulfilling location, management dashboard trends (online orders only; POS not in Bagisto) |
| P10 | Configurable expiry and slow-moving criteria producing a review list; human-approved disposal workflow |
| P11 | Held-stock buckets, supplier returns, quarantine/disposal tracking and held-stock reports |

## Known limitations

* **Integrations not implemented:** LDAP/Active Directory, OrangeHRM, ERPNext Procurement, Microsoft 365. There are no
  connectors for them in this repository. Bagisto admin users and FreshCart suppliers are maintained locally.
* **E-mail:** in-system notifications are always stored. E-mail is sent only when enabled under Configure → Operations
  and Bagisto mail is configured. Delivery was not tested against a real mail server.
* **Branch sales** only cover online orders, attributed by the shipment's inventory source. In-store POS sales do not
  exist in Bagisto.
* **Quantities are whole units** (Bagisto stores integer stock); weighed goods are not supported.
* **Bulk product imports** through Bagisto's DataTransfer write stock without model events, so those changes do not
  appear in the FreshCart ledger (admin product edits and shipments do).
* **Promotion performance** is an estimate (sales of the promoted products during vs. before the campaign) because
  Bagisto order lines do not record which catalog rule priced them.
* **Catalog rules** created by approved promotions can still be edited in Bagisto's own Marketing screens by users with
  that Bagisto permission. Restrict that permission to keep the proposal workflow authoritative.
* **Warehouse capacity** is not measured; held-stock reports show quantities occupying space, not cubic capacity.
* A rejected **transit-loss adjustment** leaves the units in the source's in-transit bucket for investigation; resolve
  them with a new adjustment (found stock / count correction).
* Language files are English only (other locales fall back to English).
