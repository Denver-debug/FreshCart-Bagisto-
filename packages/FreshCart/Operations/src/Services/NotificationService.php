<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Mail\OperationsAlert;
use FreshCart\Operations\Models\OperationsNotification;
use FreshCart\Operations\Support\LocationScope;
use FreshCart\Operations\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Webkul\User\Models\Admin;

/**
 * In-system notifications (always) plus optional e-mail (when enabled in
 * Configure → Operations → Notifications and Bagisto mail is configured).
 *
 * Notification writes happen after the business transaction; a notification
 * failure never rolls back an inventory or approval operation.
 */
class NotificationService
{
    public function __construct(
        protected Settings $settings,
        protected LocationScope $locationScope,
    ) {}

    /**
     * Notify every active admin holding the permission. When a location is given,
     * admins restricted to other locations are skipped.
     */
    public function notifyPermission(
        string $permission,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?int $locationId = null,
        array $excludeAdminIds = [],
    ): int {
        $recipients = $this->recipients($permission, $locationId)
            ->reject(fn (Admin $admin) => in_array($admin->id, array_filter($excludeAdminIds), true));

        foreach ($recipients as $admin) {
            $this->deliver($admin, $type, $title, $body, $url);
        }

        return $recipients->count();
    }

    public function notifyAdmin(int $adminId, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        $admin = Admin::where('status', 1)->find($adminId);

        if ($admin) {
            $this->deliver($admin, $type, $title, $body, $url);
        }
    }

    public function unreadCount(int $adminId): int
    {
        return OperationsNotification::where('admin_id', $adminId)->whereNull('read_at')->count();
    }

    /**
     * Active admins whose role grants the permission (full-access roles included).
     */
    public function recipients(string $permission, ?int $locationId = null): Collection
    {
        return Admin::with('role')
            ->where('status', 1)
            ->get()
            ->filter(function (Admin $admin) use ($permission, $locationId) {
                if (! $admin->role) {
                    return false;
                }

                $permissions = (array) ($admin->role->permissions ?? []);

                $isAll = $admin->role->permission_type === 'all';

                if (! $isAll && ! in_array($permission, $permissions, true)) {
                    return false;
                }

                if ($locationId === null || $isAll || in_array('operations.scope.all_locations', $permissions, true)) {
                    return true;
                }

                return in_array($locationId, $this->locationScope->assignedIds($admin->id), true);
            })
            ->values();
    }

    protected function deliver(Admin $admin, string $type, string $title, ?string $body, ?string $url): void
    {
        try {
            OperationsNotification::create([
                'admin_id' => $admin->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ]);
        } catch (Throwable $e) {
            Log::warning('FreshCart notification could not be stored', ['error' => $e->getMessage()]);

            return;
        }

        if (! $this->settings->emailNotificationsEnabled() || ! $admin->email) {
            return;
        }

        DB::afterCommit(function () use ($admin, $title, $body, $url) {
            try {
                Mail::to($admin->email)->queue(new OperationsAlert($title, $body, $url));
            } catch (Throwable $e) {
                Log::warning('FreshCart notification e-mail failed', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
            }
        });
    }
}
