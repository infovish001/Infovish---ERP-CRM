<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('session_version')->default(1);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 63)->unique();
            $table->string('name', 120);
            $table->string('status', 20)->default('provisioning');
            $table->string('database_name', 63)->unique();
            $table->string('database_user', 63)->unique();
            $table->text('database_password');
            $table->unsignedInteger('schema_version')->default(0);
            $table->timestampsTz();
        });
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->string('host', 253)->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('role', 20);
            $table->boolean('active')->default(true);
            $table->jsonb('company_ids')->default('[]');
            $table->jsonb('branch_ids')->default('[]');
            $table->timestampsTz();
            $table->primary(['tenant_id', 'user_id']);
        });
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status CHECK (status IN ('provisioning','active','suspended','failed'))");
        DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_role CHECK (role IN ('owner','administrator','auditor','member'))");
        Schema::create('platform_audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('actor_id')->nullable();
            $table->string('action', 80);
            $table->uuid('resource_id')->nullable();
            $table->jsonb('metadata');
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
        });
        DB::unprepared("CREATE FUNCTION deny_platform_audit_change() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit history is append-only'; END $$;
            CREATE TRIGGER platform_audit_immutable BEFORE UPDATE OR DELETE ON platform_audit_events FOR EACH ROW EXECUTE FUNCTION deny_platform_audit_change();");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_events');
        DB::statement('DROP FUNCTION IF EXISTS deny_platform_audit_change()');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('users');
    }
};
