<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Models\Approval;
use FreshCart\Operations\Support\DocumentRegistry;
use FreshCart\Operations\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Submit / approve / reject workflow shared by every approvable document.
 *
 * Rules enforced on the server for every decision:
 *  - the document is re-read under a row lock and must be in the expected status
 *    (stops double submissions and two people deciding at once);
 *  - level 1 needs `operations.<document>.approve`, level 2 needs
 *    `operations.approvals.level2`;
 *  - the requester cannot approve their own document unless the
 *    "allow self approval" setting is on;
 *  - one person cannot provide both approval levels;
 *  - every decision is written to the append-only `fc_approvals` table.
 */
class ApprovalService
{
    public function __construct(
        protected Settings $settings,
        protected DocumentRegistry $documents,
        protected NotificationService $notifications,
    ) {}

    /**
     * Move a draft into the approval queue. `$amount` is compared to the
     * document's level-2 threshold to decide how many approvals are needed.
     */
    public function submit(Model $document, ?float $amount = null, array $allowedFrom = ['draft'], string $pendingStatus = 'submitted'): Model
    {
        $key = $this->documents->keyFor($document);

        $document = DB::transaction(function () use ($document, $key, $amount, $allowedFrom, $pendingStatus) {
            $document = $this->lock($document);

            $this->assertStatus($document, $allowedFrom);

            $levels = 1;

            if (($threshold = $this->documents->get($key)['threshold']) && $amount !== null) {
                $limit = $this->settings->levelTwoThreshold($threshold);

                if ($limit > 0 && $amount >= $limit) {
                    $levels = 2;
                }
            }

            $from = $document->status;

            $document->forceFill([
                'status' => $pendingStatus,
                'approval_level_required' => $levels,
                'approvals_received' => 0,
                'submitted_at' => now(),
            ])->save();

            $this->record($document, 'submitted', $from, $pendingStatus);

            return $document;
        });

        $this->notifications->notifyPermission(
            $this->documents->approvePermission($key),
            'approval_pending',
            trans('operations::app.notifications.approval-pending', ['document' => $this->documents->label($document)]),
            null,
            $this->documents->url($document),
            $document->inventory_source_id ?? $document->source_inventory_source_id ?? null,
            [$document->requesterId()],
        );

        return $document;
    }

    /**
     * Record one approval level. Returns the fresh document; callers check
     * `isFullyApproved()` to run the follow-up action in the same transaction.
     */
    public function approve(Model $document, ?string $remarks, string $approvedStatus, string $pendingStatus = 'submitted'): Model
    {
        $key = $this->documents->keyFor($document);

        $document = DB::transaction(function () use ($document, $key, $remarks, $approvedStatus, $pendingStatus) {
            $document = $this->lock($document);

            $this->assertStatus($document, [$pendingStatus]);

            $level = $document->approvals_received + 1;

            $this->assertCanDecide($document, $key, $level);

            $approved = $level >= $document->approval_level_required;

            $document->forceFill([
                'approvals_received' => $level,
                'status' => $approved ? $approvedStatus : $pendingStatus,
                'approved_at' => $approved ? now() : null,
            ])->save();

            $this->record($document, 'approved', $pendingStatus, $document->status, $remarks, $level);

            return $document;
        });

        if ($document->isFullyApproved()) {
            $this->notifyRequester($document, 'approval_granted', 'operations::app.notifications.approved');
        } else {
            $this->notifications->notifyPermission(
                'operations.approvals.level2',
                'approval_pending',
                trans('operations::app.notifications.approval-pending-level2', ['document' => $this->documents->label($document)]),
                null,
                $this->documents->url($document),
                null,
                [$document->requesterId(), auth()->guard('admin')->id()],
            );
        }

        return $document;
    }

    public function reject(Model $document, string $remarks, string $rejectedStatus = 'rejected', string $pendingStatus = 'submitted'): Model
    {
        if (trim($remarks) === '') {
            throw OperationsException::trans('remarks-required');
        }

        $key = $this->documents->keyFor($document);

        $document = DB::transaction(function () use ($document, $key, $remarks, $rejectedStatus, $pendingStatus) {
            $document = $this->lock($document);

            $this->assertStatus($document, [$pendingStatus]);

            $level = $document->approvals_received + 1;

            $this->assertCanDecide($document, $key, $level);

            $document->forceFill(['status' => $rejectedStatus])->save();

            $this->record($document, 'rejected', $pendingStatus, $rejectedStatus, $remarks, $level);

            return $document;
        });

        $this->notifyRequester($document, 'approval_rejected', 'operations::app.notifications.rejected');

        return $document;
    }

    /**
     * Whether the current admin could take the next approval decision.
     */
    public function canDecide(Model $document, string $pendingStatus = 'submitted'): bool
    {
        if ($document->status !== $pendingStatus) {
            return false;
        }

        try {
            $this->assertCanDecide($document, $this->documents->keyFor($document), $document->approvals_received + 1);

            return true;
        } catch (OperationsException) {
            return false;
        }
    }

    /**
     * Write a workflow event to the document's audit trail.
     */
    public function record(Model $document, string $action, ?string $from, ?string $to, ?string $remarks = null, int $level = 1): Approval
    {
        return Approval::create([
            'approvable_type' => $document->getMorphClass(),
            'approvable_id' => $document->getKey(),
            'level' => $level,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'admin_id' => auth()->guard('admin')->id(),
        ]);
    }

    /**
     * Re-read the document under a row lock.
     */
    public function lock(Model $document): Model
    {
        return $document->newQuery()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
    }

    public function assertStatus(Model $document, array $allowed): void
    {
        if (! in_array($document->status, $allowed, true)) {
            throw OperationsException::trans('invalid-status', [
                'status' => trans('operations::app.statuses.'.$document->status),
            ]);
        }
    }

    protected function assertCanDecide(Model $document, string $key, int $level): void
    {
        $adminId = auth()->guard('admin')->id();

        $permission = $level >= 2 ? 'operations.approvals.level2' : $this->documents->approvePermission($key);

        if (! $adminId || ! bouncer()->hasPermission($permission)) {
            throw OperationsException::trans('approval-not-permitted');
        }

        if ($document->requesterId() === $adminId && ! $this->settings->allowSelfApproval()) {
            throw OperationsException::trans('self-approval');
        }

        $alreadyApproved = Approval::where('approvable_type', $document->getMorphClass())
            ->where('approvable_id', $document->getKey())
            ->where('action', 'approved')
            ->where('admin_id', $adminId)
            ->where('created_at', '>=', $document->submitted_at ?? '1970-01-01')
            ->exists();

        if ($alreadyApproved) {
            throw OperationsException::trans('already-approved');
        }
    }

    protected function notifyRequester(Model $document, string $type, string $titleKey): void
    {
        if (! $requesterId = $document->requesterId()) {
            return;
        }

        $this->notifications->notifyAdmin(
            $requesterId,
            $type,
            trans($titleKey, ['document' => $this->documents->label($document)]),
            null,
            $this->documents->url($document),
        );
    }
}
