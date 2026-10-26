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
        Schema::create('fc_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20);
            $table->string('period', 10);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['key', 'period']);
        });

        Schema::create('fc_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('inventory_source_id')->unique();
            $table->string('type', 20)->default('branch');
            $table->boolean('is_central')->default(false);
            $table->timestamps();

            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->cascadeOnDelete();
        });

        Schema::create('fc_admin_locations', function (Blueprint $table) {
            $table->unsignedInteger('admin_id');
            $table->unsignedInteger('inventory_source_id');

            $table->primary(['admin_id', 'inventory_source_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->cascadeOnDelete();
        });

        Schema::create('fc_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fc_stock_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('inventory_source_id');
            $table->unsignedInteger('reorder_level')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->unsignedInteger('max_stock')->nullable();
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'inventory_source_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->cascadeOnDelete();
        });

        Schema::create('fc_stock_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('inventory_source_id');
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->unsignedInteger('qty_on_hand')->default(0);
            $table->nullableMorphs('origin');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'inventory_source_id', 'expiry_date']);
            $table->index('expiry_date');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->cascadeOnDelete();
        });

        Schema::create('fc_stock_holds', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('inventory_source_id');
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->string('bucket', 30);
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'inventory_source_id', 'bucket']);
            $table->index('bucket');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->cascadeOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
        });

        Schema::create('fc_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku')->nullable();
            $table->unsignedInteger('inventory_source_id')->nullable();
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->string('movement_type', 40);
            $table->string('bucket_from', 30)->nullable();
            $table->string('bucket_to', 30)->nullable();
            $table->unsignedInteger('qty');
            $table->integer('sellable_delta')->default(0);
            $table->integer('sellable_after')->nullable();
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->nullableMorphs('reference');
            $table->string('reference_no', 50)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'inventory_source_id', 'created_at'], 'fc_stock_movements_product_location_created_index');
            $table->index(['inventory_source_id', 'created_at'], 'fc_stock_movements_location_created_index');
            $table->index('movement_type');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources')->nullOnDelete();
            $table->foreign('lot_id')->references('id')->on('fc_stock_lots')->nullOnDelete();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('action', 20);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_id', 'created_at']);
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_id');
            $table->string('type', 60);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url', 500)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_id', 'read_at']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });

        Schema::create('fc_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('collection', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->unsignedInteger('admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_attachments');
        Schema::dropIfExists('fc_notifications');
        Schema::dropIfExists('fc_approvals');
        Schema::dropIfExists('fc_stock_movements');
        Schema::dropIfExists('fc_stock_holds');
        Schema::dropIfExists('fc_stock_lots');
        Schema::dropIfExists('fc_stock_policies');
        Schema::dropIfExists('fc_suppliers');
        Schema::dropIfExists('fc_admin_locations');
        Schema::dropIfExists('fc_locations');
        Schema::dropIfExists('fc_sequences');
    }
};
