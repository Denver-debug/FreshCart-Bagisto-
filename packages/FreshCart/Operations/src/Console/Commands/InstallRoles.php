<?php

namespace FreshCart\Operations\Console\Commands;

use Illuminate\Console\Command;
use Webkul\User\Models\Role;

/**
 * Creates the FreshCart functional roles as Bagisto roles with least-privilege
 * permission sets. Existing roles with the same name are left untouched unless
 * --force is given. Roles remain editable under Settings → Roles.
 */
class InstallRoles extends Command
{
    protected $signature = 'fc:install-roles {--force : Overwrite permissions of existing FreshCart roles}';

    protected $description = 'Create the FreshCart Operations roles (Management, Purchasing, Inventory, Warehouse, Store Manager, Logistics, Marketing, Finance).';

    const BASE = ['operations', 'operations.inbox', 'operations.stock'];

    public static function roles(): array
    {
        return [
            'FreshCart System Administrator' => [
                'description' => 'Full access to Bagisto and FreshCart Operations.',
                'permission_type' => 'all',
                'permissions' => [],
            ],
            'FreshCart Management' => [
                'description' => 'Approvals (including second level), oversight and management reports.',
                'permissions' => [
                    'operations.purchase_requests', 'operations.purchase_requests.approve',
                    'operations.purchase_orders', 'operations.receiving',
                    'operations.stock_requests', 'operations.transfers', 'operations.transfers.approve',
                    'operations.incidents', 'operations.adjustments', 'operations.adjustments.approve',
                    'operations.supplier_returns', 'operations.disposals', 'operations.disposals.approve',
                    'operations.promotions', 'operations.promotions.approve',
                    'operations.price_changes', 'operations.price_changes.approve',
                    'operations.reports', 'operations.reports.inventory', 'operations.reports.procurement',
                    'operations.reports.quality', 'operations.reports.finance', 'operations.reports.sales',
                    'operations.approvals', 'operations.approvals.level2',
                    'operations.scope', 'operations.scope.all_locations',
                ],
            ],
            'FreshCart Purchasing Officer' => [
                'description' => 'Purchase requests, purchase orders, suppliers and supplier returns.',
                'permissions' => [
                    'operations.setup', 'operations.setup.suppliers', 'operations.setup.suppliers.create', 'operations.setup.suppliers.edit',
                    'operations.purchase_requests', 'operations.purchase_requests.create',
                    'operations.purchase_orders', 'operations.purchase_orders.create',
                    'operations.receiving', 'operations.supplier_returns', 'operations.supplier_returns.manage',
                    'operations.reports', 'operations.reports.procurement', 'operations.reports.inventory',
                    'operations.scope', 'operations.scope.all_locations',
                ],
            ],
            'FreshCart Inventory Manager' => [
                'description' => 'Inventory oversight, allocation, inspections and authorized adjustments.',
                'permissions' => [
                    'operations.setup', 'operations.setup.locations', 'operations.setup.stock_policies',
                    'operations.purchase_requests', 'operations.purchase_requests.create',
                    'operations.stock_requests', 'operations.stock_requests.allocate',
                    'operations.transfers', 'operations.transfers.create', 'operations.transfers.approve', 'operations.transfers.close',
                    'operations.incidents', 'operations.incidents.inspect', 'operations.incidents.classify',
                    'operations.adjustments', 'operations.adjustments.create', 'operations.adjustments.approve', 'operations.adjustments.post',
                    'operations.supplier_returns', 'operations.supplier_returns.manage',
                    'operations.disposals', 'operations.disposals.create', 'operations.disposals.inspect',
                    'operations.reports', 'operations.reports.inventory', 'operations.reports.quality',
                    'operations.scope', 'operations.scope.all_locations',
                ],
            ],
            'FreshCart Warehouse Personnel' => [
                'description' => 'Receiving, inspection, picking, packing, dispatch and disposal execution at assigned locations.',
                'permissions' => [
                    'operations.purchase_orders', 'operations.receiving', 'operations.receiving.create',
                    'operations.stock_requests', 'operations.transfers', 'operations.transfers.pick',
                    'operations.transfers.dispatch', 'operations.transfers.receive',
                    'operations.incidents', 'operations.incidents.create', 'operations.incidents.inspect',
                    'operations.disposals', 'operations.disposals.execute',
                ],
            ],
            'FreshCart Store Manager' => [
                'description' => 'Branch replenishment and transfer requests, receipts and branch monitoring.',
                'permissions' => [
                    'operations.purchase_requests', 'operations.purchase_requests.create',
                    'operations.stock_requests', 'operations.stock_requests.create',
                    'operations.transfers', 'operations.transfers.create', 'operations.transfers.receive',
                    'operations.incidents', 'operations.incidents.create',
                    'operations.adjustments', 'operations.adjustments.create',
                    'operations.disposals', 'operations.disposals.create',
                    'operations.reports', 'operations.reports.inventory', 'operations.reports.sales',
                ],
            ],
            'FreshCart Logistics' => [
                'description' => 'Delivery scheduling, dispatch coordination and transport records.',
                'permissions' => [
                    'operations.transfers', 'operations.transfers.schedule', 'operations.transfers.dispatch',
                    'operations.reports', 'operations.reports.inventory',
                    'operations.scope', 'operations.scope.all_locations',
                ],
            ],
            'FreshCart Marketing' => [
                'description' => 'Promotion proposals, bulk price change requests and campaign performance.',
                'permissions' => [
                    'operations.promotions', 'operations.promotions.create',
                    'operations.price_changes', 'operations.price_changes.create',
                    'operations.reports', 'operations.reports.sales',
                    'operations.scope', 'operations.scope.all_locations',
                ],
            ],
            'FreshCart Finance' => [
                'description' => 'Valuation, adjustment reports and disposal documentation.',
                'permissions' => [
                    'operations.purchase_orders', 'operations.receiving', 'operations.adjustments',
                    'operations.supplier_returns', 'operations.disposals', 'operations.disposals.value',
                    'operations.reports', 'operations.reports.finance', 'operations.reports.sales', 'operations.reports.procurement',
                    'operations.scope', 'operations.scope.all_locations',
                ],
            ],
        ];
    }

    public function handle(): int
    {
        foreach (self::roles() as $name => $definition) {
            $role = Role::where('name', $name)->first();

            if ($role && ! $this->option('force')) {
                $this->line("Skipped existing role: $name");

                continue;
            }

            $type = $definition['permission_type'] ?? 'custom';

            $attributes = [
                'description' => $definition['description'],
                'permission_type' => $type,
                'permissions' => $type === 'all' ? null : array_values(array_unique(array_merge(self::BASE, $definition['permissions']))),
            ];

            $role ? $role->update($attributes) : Role::create(['name' => $name] + $attributes);

            $this->info(($role ? 'Updated' : 'Created')." role: $name");
        }

        $this->newLine();
        $this->line('Assign users to roles under Settings → Users, and to locations under Operations → Setup → Locations.');

        return self::SUCCESS;
    }
}
