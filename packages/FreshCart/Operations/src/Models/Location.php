<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Location as LocationContract;
use FreshCart\Operations\Models\Concerns\BelongsToLocation;
use Illuminate\Database\Eloquent\Model;

class Location extends Model implements LocationContract
{
    use BelongsToLocation;

    const TYPE_WAREHOUSE = 'warehouse';

    const TYPE_BRANCH = 'branch';

    protected $table = 'fc_locations';

    protected $fillable = [
        'inventory_source_id',
        'type',
        'is_central',
    ];

    protected $casts = [
        'is_central' => 'boolean',
    ];
}
