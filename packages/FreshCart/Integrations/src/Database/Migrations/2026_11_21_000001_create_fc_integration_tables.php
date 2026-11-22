<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Work waiting to be pushed to an external system. Rows are written in
         * the same transaction as the business record, so nothing is lost when
         * Akaunting is down.
         */
        Schema::create('fc_integration_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50);
            $table->string('subject_type', 50);
            $table->string('subject_id', 150);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['action', 'subject_type', 'subject_id']);
        });

        /**
         * Local record => remote record id, per external system.
         */
        Schema::create('fc_integration_links', function (Blueprint $table) {
            $table->id();
            $table->string('system', 20);
            $table->string('local_type', 50);
            $table->string('local_id', 150);
            $table->string('remote_id', 150);
            $table->timestamps();

            $table->unique(['system', 'local_type', 'local_id']);
            $table->index(['system', 'local_type', 'remote_id']);
        });

        Schema::create('fc_integration_cursors', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fc_integration_cursors');
        Schema::dropIfExists('fc_integration_links');
        Schema::dropIfExists('fc_integration_outbox');
    }
};
