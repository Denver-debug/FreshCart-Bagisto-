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
        Schema::create('fc_disposal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->unsignedInteger('inventory_source_id');
            $table->string('status', 25)->default('draft')->index();
            $table->text('reason')->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('inspected_by')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->text('inspection_remarks')->nullable();
            $table->unsignedInteger('valued_by')->nullable();
            $table->timestamp('valued_at')->nullable();
            $table->text('valuation_remarks')->nullable();
            $table->decimal('total_value', 14, 4)->default(0);
            $table->unsignedTinyInteger('approval_level_required')->default(1);
            $table->unsignedTinyInteger('approvals_received')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('segregated_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('disposal_method', 100)->nullable();
            $table->text('completion_remarks')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('supplier_return_id')->nullable();
            $table->timestamps();

            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('inspected_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('valued_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('fc_suppliers')->nullOnDelete();
            $table->foreign('supplier_return_id')->references('id')->on('fc_supplier_returns')->nullOnDelete();
        });

        Schema::create('fc_disposal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('disposal_request_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('qty');
            $table->string('source_bucket', 30)->default('sellable');
            $table->string('reason', 30);
            $table->string('disposition', 30)->default('dispose');
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('line_value', 14, 4)->default(0);
            $table->timestamps();

            $table->foreign('disposal_request_id')->references('id')->on('fc_disposal_requests')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
        });

        Schema::create('fc_retirement_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 100)->unique();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('inventory_source_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->string('criterion', 30)->index();
            $table->unsignedInteger('qty');
            $table->decimal('estimated_value', 14, 4)->nullable();
            $table->json('details')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('disposal_request_id')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->cascadeOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('disposal_request_id')->references('id')->on('fc_disposal_requests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_retirement_candidates');
        Schema::dropIfExists('fc_disposal_items');
        Schema::dropIfExists('fc_disposal_requests');
    }
};
