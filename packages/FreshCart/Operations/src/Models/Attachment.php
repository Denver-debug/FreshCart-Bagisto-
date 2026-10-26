<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Attachment as AttachmentContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\AdminProxy;

class Attachment extends Model implements AttachmentContract
{
    const UPDATED_AT = null;

    protected $table = 'fc_attachments';

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'collection',
        'path',
        'original_name',
        'mime_type',
        'size',
        'admin_id',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminProxy::modelClass());
    }
}
