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
        Schema::create('fc_stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedInteger('inventory_source_id');
            $table->unsignedInteger('source_inventory_source_id')->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 25)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('allocated_by')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();

            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('source_inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('allocated_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_request_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedInteger('qty_requested');
            $table->unsignedInteger('qty_suggested')->default(0);
            $table->unsignedInteger('qty_allocated')->default(0);
            $table->unsignedInteger('qty_dispatched')->default(0);
            $table->unsignedInteger('qty_received')->default(0);
            $table->text('override_reason')->nullable();
            $table->timestamps();

            $table->foreign('stock_request_id')->references('id')->on('fc_stock_requests')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('fc_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('type', 20)->default('inter_branch')->index();
            $table->unsignedBigInteger('stock_request_id')->nullable();
            $table->unsignedBigInteger('return_of_transfer_id')->nullable();
            $table->unsignedInteger('source_inventory_source_id');
            $table->unsignedInteger('destination_inventory_source_id');
            $table->string('status', 25)->default('draft')->index();
            $table->string('priority', 10)->default('normal');
            $table->text('reason')->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->boolean('approval_required')->default(false);
            $table->unsignedTinyInteger('approval_level_required')->default(1);
            $table->unsignedTinyInteger('approvals_received')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('picked_by')->nullable();
            $table->timestamp('picking_started_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->dateTime('scheduled_dispatch_at')->nullable();
            $table->dateTime('expected_arrival_at')->nullable()->index();
            $table->unsignedInteger('assigned_staff_id')->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('vehicle', 100)->nullable();
            $table->unsignedInteger('dispatched_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->string('dispatch_idempotency_key', 64)->nullable()->unique();
            $table->timestamp('received_at')->nullable();
            $table->date('expected_return_date')->nullable()->index();
            $table->boolean('has_discrepancy')->default(false)->index();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_request_id')->references('id')->on('fc_stock_requests')->nullOnDelete();
            $table->foreign('return_of_transfer_id')->references('id')->on('fc_transfers')->nullOnDelete();
            $table->foreign('source_inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('destination_inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('picked_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('assigned_staff_id')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('dispatched_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('stock_request_item_id')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedInteger('qty_requested');
            $table->unsignedInteger('qty_picked')->default(0);
            $table->unsignedInteger('qty_dispatched')->default(0);
            $table->unsignedInteger('qty_received')->default(0);
            $table->unsignedInteger('qty_damaged')->default(0);
            $table->unsignedInteger('qty_written_off')->default(0);
            $table->string('remarks', 500)->nullable();
            $table->timestamps();

            $table->foreign('transfer_id')->references('id')->on('fc_transfers')->cascadeOnDelete();
            $table->foreign('stock_request_item_id')->references('id')->on('fc_stock_request_items')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('fc_transfer_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedInteger('received_by')->nullable();
            $table->string('receiver_name', 100)->nullable();
            $table->dateTime('received_at');
            $table->text('remarks')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->foreign('transfer_id')->references('id')->on('fc_transfers')->cascadeOnDelete();
            $table->foreign('received_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_transfer_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_receipt_id');
            $table->unsignedBigInteger('transfer_item_id');
            $table->unsignedInteger('qty_received')->default(0);
            $table->unsignedInteger('qty_damaged')->default(0);
            $table->string('remarks', 500)->nullable();

            $table->foreign('transfer_receipt_id')->references('id')->on('fc_transfer_receipts')->cascadeOnDelete();
            $table->foreign('transfer_item_id')->references('id')->on('fc_transfer_items')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_transfer_receipt_items');
        Schema::dropIfExists('fc_transfer_receipts');
        Schema::dropIfExists('fc_transfer_items');
        Schema::dropIfExists('fc_transfers');
        Schema::dropIfExists('fc_stock_request_items');
        Schema::dropIfExists('fc_stock_requests');
    }
};
