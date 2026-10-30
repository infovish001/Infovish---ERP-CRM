<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_identity', fn (Blueprint $table) => $table->uuid('id')->primary());
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('base_currency', 3);
            $table->string('timezone', 64);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained();
            $table->uuid('branch_id')->nullable();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['company_id', 'code']);
        });
        DB::statement('ALTER TABLE branches ADD CONSTRAINT branches_company_id_unique UNIQUE (id, company_id)');
        DB::statement('ALTER TABLE departments ADD CONSTRAINT departments_branch_company_fk FOREIGN KEY (branch_id, company_id) REFERENCES branches(id, company_id)');
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id');
            $table->string('action', 80);
            $table->uuid('resource_id');
            $table->jsonb('metadata');
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
        });
        DB::unprepared("CREATE FUNCTION deny_audit_change() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit history is append-only'; END $$;
            CREATE TRIGGER tenant_audit_immutable BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION deny_audit_change();");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        DB::statement('DROP FUNCTION IF EXISTS deny_audit_change()');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('tenant_identity');
    }
};
