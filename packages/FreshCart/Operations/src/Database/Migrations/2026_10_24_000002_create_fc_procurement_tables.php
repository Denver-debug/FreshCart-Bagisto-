<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fc_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedInteger('inventory_source_id');
            $table->unsignedInteger('requested_by')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->date('needed_by')->nullable();
            $table->text('justification')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedTinyInteger('approval_level_required')->default(1);
            $table->unsignedTinyInteger('approvals_received')->default(0);
            $table->decimal('estimated_total', 14, 4)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedInteger('qty');
            $table->decimal('estimated_unit_cost', 12, 4)->nullable();
            $table->unsignedBigInteger('preferred_supplier_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->foreign('purchase_request_id')->references('id')->on('fc_purchase_requests')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('preferred_supplier_id')->references('id')->on('fc_suppliers')->nullOnDelete();
        });

        Schema::create('fc_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedInteger('inventory_source_id');
            $table->string('status', 25)->default('draft')->index();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable()->index();
            $table->string('currency', 3)->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('fc_suppliers')->restrictOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_request_item_id')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedInteger('qty_ordered');
            $table->unsignedInteger('qty_received')->default(0);
            $table->unsignedInteger('qty_rejected')->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('line_total', 14, 4)->default(0);
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('fc_purchase_orders')->cascadeOnDelete();
            $table->foreign('purchase_request_item_id')->references('id')->on('fc_purchase_request_items')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('fc_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedInteger('inventory_source_id');
            $table->unsignedInteger('received_by')->nullable();
            $table->string('delivery_note_no', 100)->nullable();
            $table->dateTime('received_at');
            $table->boolean('has_discrepancy')->default(false)->index();
            $table->text('remarks')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('fc_purchase_orders')->restrictOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('received_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->unsignedInteger('qty_received');
            $table->unsignedInteger('qty_accepted');
            $table->unsignedInteger('qty_rejected')->default(0);
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('storage_location', 100)->nullable();
            $table->string('inspection_result', 20)->default('passed');
            $table->text('discrepancy_note')->nullable();
            $table->text('over_receipt_reason')->nullable();
            $table->unsignedInteger('over_receipt_approved_by')->nullable();
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->timestamps();

            $table->foreign('goods_receipt_id')->references('id')->on('fc_goods_receipts')->cascadeOnDelete();
            $table->foreign('purchase_order_item_id')->references('id')->on('fc_purchase_order_items')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('over_receipt_approved_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_goods_receipt_items');
        Schema::dropIfExists('fc_goods_receipts');
        Schema::dropIfExists('fc_purchase_order_items');
        Schema::dropIfExists('fc_purchase_orders');
        Schema::dropIfExists('fc_purchase_request_items');
        Schema::dropIfExists('fc_purchase_requests');
    }
};
