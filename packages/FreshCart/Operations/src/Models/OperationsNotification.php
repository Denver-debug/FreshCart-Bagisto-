<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\OperationsNotification as OperationsNotificationContract;
use Illuminate\Database\Eloquent\Model;

class OperationsNotification extends Model implements OperationsNotificationContract
{
    const UPDATED_AT = null;

    protected $table = 'fc_notifications';

    protected $fillable = [
        'admin_id',
        'type',
        'title',
        'body',
        'url',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}
