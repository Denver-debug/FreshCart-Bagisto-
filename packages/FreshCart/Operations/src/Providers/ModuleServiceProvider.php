<?php

namespace FreshCart\Operations\Providers;

use FreshCart\Operations\Models\Approval;
use FreshCart\Operations\Models\Attachment;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\GoodsReceiptItem;
use FreshCart\Operations\Models\Location;
use FreshCart\Operations\Models\OperationsNotification;
use FreshCart\Operations\Models\PurchaseOrder;
use FreshCart\Operations\Models\PurchaseOrderItem;
use FreshCart\Operations\Models\PurchaseRequest;
use FreshCart\Operations\Models\PurchaseRequestItem;
use FreshCart\Operations\Models\Sequence;
use FreshCart\Operations\Models\StockHold;
use FreshCart\Operations\Models\StockLot;
use FreshCart\Operations\Models\StockMovement;
use FreshCart\Operations\Models\StockPolicy;
use FreshCart\Operations\Models\Supplier;
use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Define the models provided by this module.
     */
    protected $models = [
        Approval::class,
        Attachment::class,
        GoodsReceipt::class,
        GoodsReceiptItem::class,
        Location::class,
        OperationsNotification::class,
        PurchaseOrder::class,
        PurchaseOrderItem::class,
        PurchaseRequest::class,
        PurchaseRequestItem::class,
        Sequence::class,
        StockHold::class,
        StockLot::class,
        StockMovement::class,
        StockPolicy::class,
        Supplier::class,
    ];
}
