<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_probe', fn (Blueprint $table) => $table->uuid('id')->primary());
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_probe');
    }
};
