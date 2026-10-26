<?php

namespace FreshCart\Operations\Providers;

use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\AdjustmentItem;
use FreshCart\Operations\Models\Approval;
use FreshCart\Operations\Models\Attachment;
use FreshCart\Operations\Models\GoodsReceipt;
use FreshCart\Operations\Models\GoodsReceiptItem;
use FreshCart\Operations\Models\Incident;
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
use FreshCart\Operations\Models\SupplierReturn;
use FreshCart\Operations\Models\SupplierReturnItem;
use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Define the models provided by this module.
     */
    protected $models = [
        Adjustment::class,
        AdjustmentItem::class,
        Approval::class,
        Attachment::class,
        GoodsReceipt::class,
        GoodsReceiptItem::class,
        Incident::class,
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
        SupplierReturn::class,
        SupplierReturnItem::class,
    ];
}
