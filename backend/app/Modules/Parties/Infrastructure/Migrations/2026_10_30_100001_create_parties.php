<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->string('number', 30);
            $table->string('name', 160);
            $table->string('email', 254)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('customer')->default(false);
            $table->boolean('supplier')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('revision')->default(1);
            $table->timestampsTz();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'email']);
            $table->index(['company_id', 'name']);
        });
        DB::statement('ALTER TABLE parties_contacts ADD CONSTRAINT parties_revision_positive CHECK (revision > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parties_contacts');
    }
};
