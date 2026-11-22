<?php

namespace FreshCart\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_DONE = 'done';

    const STATUS_FAILED = 'failed';

    protected $table = 'fc_integration_outbox';

    protected $fillable = [
        'action',
        'subject_type',
        'subject_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
