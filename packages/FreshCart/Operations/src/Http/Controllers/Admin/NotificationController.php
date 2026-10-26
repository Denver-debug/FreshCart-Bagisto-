<?php

namespace FreshCart\Operations\Http\Controllers\Admin;

use FreshCart\Operations\Models\OperationsNotification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = OperationsNotification::where('admin_id', $this->adminId())
            ->latest('id')
            ->paginate(30);

        return view('operations::admin.inbox.notifications', compact('notifications'));
    }

    /**
     * Mark one notification read and follow its link (own notifications only).
     */
    public function open(int $id)
    {
        $notification = OperationsNotification::where('admin_id', $this->adminId())->findOrFail($id);

        $notification->update(['read_at' => $notification->read_at ?? now()]);

        $url = $notification->url;

        // Only follow links back into this application.
        if (! $url || ! Str::startsWith($url, url('/'))) {
            return redirect()->route('admin.operations.notifications.index');
        }

        return redirect()->to($url);
    }

    public function readAll()
    {
        OperationsNotification::where('admin_id', $this->adminId())->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('admin.operations.notifications.index');
    }
}
