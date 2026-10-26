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
        Schema::create('fc_promotion_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('department', 100)->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->string('discount_type', 20);
            $table->decimal('discount_amount', 12, 4);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('product_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedTinyInteger('approval_level_required')->default(1);
            $table->unsignedTinyInteger('approvals_received')->default(0);
            $table->unsignedInteger('catalog_rule_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('expiry_warned_at')->nullable();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
            $table->foreign('catalog_rule_id')->references('id')->on('catalog_rules')->nullOnDelete();
        });

        Schema::create('fc_price_change_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('name');
            $table->unsignedInteger('requested_by')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('effective_at');
            $table->unsignedTinyInteger('approval_level_required')->default(1);
            $table->unsignedTinyInteger('approvals_received')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->foreign('requested_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('fc_price_change_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('price_change_batch_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->decimal('old_price', 12, 4)->nullable();
            $table->decimal('new_price', 12, 4);
            $table->string('status', 20)->default('pending');
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->foreign('price_change_batch_id')->references('id')->on('fc_price_change_batches')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('fc_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku');
            $table->decimal('old_price', 12, 4)->nullable();
            $table->decimal('new_price', 12, 4);
            $table->nullableMorphs('source');
            $table->unsignedInteger('admin_id')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['product_id', 'changed_at']);
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fc_price_history');
        Schema::dropIfExists('fc_price_change_items');
        Schema::dropIfExists('fc_price_change_batches');
        Schema::dropIfExists('fc_promotion_proposals');
    }
};
