<?php

namespace FreshCart\Integrations\Services;

use FreshCart\Integrations\Models\IntegrationLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Webkul\User\Models\Admin;
use Webkul\User\Models\Role;

/**
 * NovaCore HR employee (API v1 shape: employee_id, full_name, work_email, …)
 * => Bagisto admin user.
 *
 * - Employees with status Active whose employment stage is not Suspended or
 *   Separated (NovaCore's own is_active_employee rule) get an enabled admin
 *   with the role mapped from their designation/department; everyone else is
 *   disabled (never deleted, so the approval history keeps its names).
 * - New admins get a random password and set their own through
 *   "Forgot password" on the admin login page.
 * - Admins with a full-access role are linked but never modified, so a Frappe
 *   record can not lock out the system administrator.
 */
class EmployeeSync
{
    const SYSTEM = 'frappe';

    /**
     * @return string one of created, updated, linked, skipped:<reason>
     */
    public function sync(array $employee): string
    {
        $employeeId = $employee['employee_id'] ?? null;

        if (! $employeeId) {
            return 'skipped:missing employee id';
        }

        $email = $this->email($employee);
        $adminId = IntegrationLink::localId(self::SYSTEM, 'admin', $employeeId);
        $admin = $adminId ? Admin::find($adminId) : null;

        if (! $admin && $email) {
            $admin = Admin::where('email', $email)->first();
        }

        if (! $admin && ! $email) {
            return 'skipped:no e-mail address in Frappe HR';
        }

        if ($admin && $admin->role?->permission_type === 'all') {
            IntegrationLink::put(self::SYSTEM, 'admin', $admin->id, $employeeId);

            return 'linked';
        }

        $role = $this->role($employee);

        if (! $role) {
            return 'skipped:no Bagisto role matches "'.($employee['designation'] ?? '').'" and the default role does not exist';
        }

        $active = ($employee['status'] ?? null) === 'Active'
            && ! in_array($employee['employment_stage'] ?? null, ['Suspended', 'Separated'], true);
        $created = ! $admin;

        DB::transaction(function () use (&$admin, $employee, $employeeId, $email, $role, $active) {
            $attributes = [
                'name' => $employee['full_name'] ?? trim(($employee['first_name'] ?? '').' '.($employee['last_name'] ?? '')) ?: $employeeId,
                'role_id' => $role->id,
                'status' => (int) $active,
            ];

            if ($email) {
                $attributes['email'] = $email;
            }

            if ($admin) {
                $admin->update($attributes);
            } else {
                $admin = Admin::create($attributes + [
                    'password' => Hash::make(Str::random(40)),
                    'api_token' => Str::random(80),
                ]);
            }

            IntegrationLink::put(self::SYSTEM, 'admin', $admin->id, $employeeId);

            $this->assignLocation($admin, $employee['branch'] ?? null);
        });

        return $created ? 'created' : 'updated';
    }

    protected function email(array $employee): ?string
    {
        foreach (['work_email', 'user_id'] as $field) {
            $value = Str::lower(trim((string) ($employee[$field] ?? '')));

            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }

    protected function role(array $employee): ?Role
    {
        $map = config('freshcart-integrations.frappe.role_map');

        $name = $map[$employee['designation'] ?? ''] ?? $map[$employee['department'] ?? ''] ?? null;

        return Role::where('name', $name ?? config('freshcart-integrations.frappe.default_role'))->first();
    }

    /**
     * Only mapped branches change location assignments; unmapped ones leave
     * whatever was set manually under Operations → Setup → Locations.
     */
    protected function assignLocation(Admin $admin, ?string $branch): void
    {
        $code = config('freshcart-integrations.frappe.branch_map')[$branch ?? ''] ?? null;

        if (! $code) {
            return;
        }

        $sourceId = DB::table('inventory_sources')->where('code', $code)->value('id');

        if (! $sourceId) {
            return;
        }

        DB::table('fc_admin_locations')->where('admin_id', $admin->id)->delete();

        DB::table('fc_admin_locations')->insert(['admin_id' => $admin->id, 'inventory_source_id' => $sourceId]);
    }
}
