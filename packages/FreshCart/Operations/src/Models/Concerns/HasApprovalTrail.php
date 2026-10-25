<?php

namespace FreshCart\Operations\Models\Concerns;

use FreshCart\Operations\Models\ApprovalProxy;
use FreshCart\Operations\Models\AttachmentProxy;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovalTrail
{
    /**
     * Approval history, oldest first.
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(ApprovalProxy::modelClass(), 'approvable')->orderBy('id');
    }

    /**
     * Files attached to the document.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(AttachmentProxy::modelClass(), 'attachable')->orderBy('id');
    }

    /**
     * Whether the document has collected every approval level it needs.
     */
    public function isFullyApproved(): bool
    {
        return $this->approvals_received >= $this->approval_level_required;
    }

    /**
     * The admin who raised the document (used for self-approval checks).
     */
    public function requesterId(): ?int
    {
        return $this->requested_by;
    }
}
