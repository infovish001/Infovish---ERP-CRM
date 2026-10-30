<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->string('number', 30);
            $table->string('name', 160);
            $table->string('type', 10);
            $table->string('unit', 30);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('revision')->default(1);
            $table->timestampsTz();
            $table->unique(['company_id', 'number']);
            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'name']);
        });
        Schema::create('catalog_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->foreignUuid('company_id')->constrained();
            $table->string('sku', 50);
            $table->string('name', 160);
            $table->jsonb('attributes');
            $table->decimal('list_price', 20, 6)->nullable();
            $table->string('currency', 3);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('revision')->default(1);
            $table->timestampsTz();
            $table->unique(['company_id', 'sku']);
            $table->foreign(['product_id', 'company_id'])->references(['id', 'company_id'])->on('catalog_products');
        });
        DB::statement("ALTER TABLE catalog_products ADD CONSTRAINT catalog_type_valid CHECK (type IN ('product','service'))");
        DB::statement('ALTER TABLE catalog_variants ADD CONSTRAINT catalog_price_positive CHECK (list_price IS NULL OR list_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_variants');
        Schema::dropIfExists('catalog_products');
    }
};
