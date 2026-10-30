<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_installations', function (Blueprint $table) {
            $table->string('module', 40)->primary();
            $table->string('version', 20);
            $table->boolean('enabled')->default(false);
            $table->timestampsTz();
        });
        Schema::create('document_counters', function (Blueprint $table) {
            $table->foreignUuid('company_id')->constrained();
            $table->string('series', 30);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->primary(['company_id', 'series']);
        });
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->string('scope', 64)->primary();
            $table->string('request_hash', 64);
            $table->uuid('resource_id');
            $table->timestampTz('created_at')->useCurrent();
        });
        Schema::create('event_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('actor_id');
            $table->foreignUuid('company_id')->constrained();
            $table->string('module', 40);
            $table->string('event_type', 80);
            $table->unsignedInteger('event_version')->default(1);
            $table->uuid('aggregate_id');
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->string('state', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at')->useCurrent();
            $table->string('error_code', 40)->nullable();
            $table->timestampTz('delivered_at')->nullable();
        });
        Schema::create('event_receipts', function (Blueprint $table) {
            $table->foreignUuid('event_id')->primary()->constrained('event_outbox');
            $table->string('consumer', 40);
            $table->timestampTz('processed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['event_receipts', 'event_outbox', 'idempotency_records', 'document_counters', 'module_installations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
