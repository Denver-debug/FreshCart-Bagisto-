<?php

namespace FreshCart\Operations\Providers;

use FreshCart\Operations\Models\Location;
use FreshCart\Operations\Models\Sequence;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockLot;
use FreshCart\Operations\Models\StockMovement;
use FreshCart\Operations\Models\StockPolicy;
use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Define the models provided by this module.
     */
    protected $models = [
        Location::class,
        Sequence::class,
        StockHold::class,
        StockLot::class,
        StockMovement::class,
        StockPolicy::class,
    ];
}
