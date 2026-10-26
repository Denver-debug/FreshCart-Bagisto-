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
        Schema::create('fc_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedInteger('inventory_source_id');
            $table->string('direction', 5)->default('out');
            $table->string('source_bucket', 30)->nullable();
            $table->string('reason_code', 30);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->nullableMorphs('origin');
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedTinyInteger('approval_level_required')->default(1);
            $table->unsignedTinyInteger('approvals_received')->default(0);
            $table->decimal('total_value', 14, 4)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adjustment_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->unsignedInteger('qty');
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('line_value', 14, 4)->default(0);
            $table->timestamps();

            $table->foreign('adjustment_id')->references('id')->on('fc_adjustments')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
        });

        Schema::create('fc_supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedInteger('inventory_source_id');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->decimal('credit_amount', 14, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('fc_suppliers')->restrictOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_return_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('qty_replaced')->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->nullableMorphs('origin');
            $table->timestamps();

            $table->foreign('supplier_return_id')->references('id')->on('fc_supplier_returns')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
        });

        Schema::create('fc_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedInteger('inventory_source_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('qty');
            $table->string('incident_type', 30)->index();
            $table->boolean('from_stock')->default(true);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('quarantined')->index();
            $table->unsignedInteger('reported_by')->nullable();
            $table->json('checklist')->nullable();
            $table->text('inspection_remarks')->nullable();
            $table->unsignedInteger('inspected_by')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->string('classification', 30)->nullable()->index();
            $table->unsignedInteger('classified_by')->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('adjustment_id')->nullable();
            $table->unsignedBigInteger('supplier_return_id')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
            $table->foreign('reported_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('inspected_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('classified_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('fc_suppliers')->nullOnDelete();
            $table->foreign('adjustment_id')->references('id')->on('fc_adjustments')->nullOnDelete();
            $table->foreign('supplier_return_id')->references('id')->on('fc_supplier_returns')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_incidents');
        Schema::dropIfExists('fc_supplier_return_items');
        Schema::dropIfExists('fc_supplier_returns');
        Schema::dropIfExists('fc_adjustment_items');
        Schema::dropIfExists('fc_adjustments');
    }
};
