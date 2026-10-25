<?php

namespace FreshCart\Operations\Http\Controllers\Admin;

use FreshCart\Operations\Models\OperationsNotification;
use FreshCart\Operations\Services\DashboardService;
use FreshCart\Operations\Support\DocumentRegistry;
use Illuminate\View\View;

/**
 * "Inbox": documents waiting for the current admin's decision plus recent notifications.
 */
class ApprovalQueueController extends Controller
{
    public function index(DashboardService $dashboard, DocumentRegistry $documents): View
    {
        $pending = $dashboard->pendingForMe()->map(fn ($document) => [
            'label' => $documents->label($document),
            'url' => $documents->url($document),
            'status' => $document->status,
            'submitted_at' => $document->submitted_at,
            'level' => $document->approvals_received + 1,
            'levels' => $document->approval_level_required,
        ]);

        $notifications = OperationsNotification::where('admin_id', $this->adminId())->latest('id')->limit(10)->get();

        return view('operations::admin.inbox.index', compact('pending', 'notifications'));
    }
}
