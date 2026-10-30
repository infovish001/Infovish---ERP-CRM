<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipelines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('company_id')->constrained();
            $t->string('name', 120);
            $t->boolean('active')->default(true);
            $t->integer('revision')->default(1);
            $t->timestampsTz();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'name']);
        });
        Schema::create('crm_stages', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('pipeline_id');
            $t->uuid('company_id');
            $t->string('name', 80);
            $t->integer('position');
            $t->integer('probability');
            $t->boolean('active')->default(true);
            $t->integer('revision')->default(1);
            $t->timestampsTz();
            $t->foreign(['pipeline_id', 'company_id'])->references(['id', 'company_id'])->on('crm_pipelines');
            $t->unique(['id', 'pipeline_id', 'company_id']);
            $t->unique(['pipeline_id', 'position']);
            $t->unique(['pipeline_id', 'name']);
        });
        Schema::create('crm_leads', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('company_id')->constrained();
            $t->string('number', 30);
            $t->string('title', 160);
            $t->string('contact_name', 160);
            $t->string('email', 254)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('source', 80)->nullable();
            $t->uuid('assigned_to');
            $t->string('status', 20)->default('new');
            $t->string('reason', 500)->nullable();
            $t->uuid('contact_id')->nullable();
            $t->uuid('opportunity_id')->nullable();
            $t->string('conversion_hash', 64)->nullable();
            $t->integer('revision')->default(1);
            $t->timestampsTz();
            $t->unique(['company_id', 'number']);
            $t->unique(['id', 'company_id']);
            $t->index(['company_id', 'assigned_to', 'status']);
        });
        Schema::create('crm_opportunities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('company_id')->constrained();
            $t->string('number', 30);
            $t->string('title', 160);
            $t->uuid('contact_id');
            $t->uuid('lead_id')->nullable()->unique();
            $t->uuid('pipeline_id');
            $t->uuid('stage_id');
            $t->uuid('assigned_to');
            $t->decimal('expected_amount', 20, 6)->nullable();
            $t->string('currency', 3);
            $t->date('expected_close')->nullable();
            $t->string('status', 20)->default('open');
            $t->string('reason', 500)->nullable();
            $t->integer('revision')->default(1);
            $t->timestampsTz();
            $t->unique(['company_id', 'number']);
            $t->unique(['id', 'company_id']);
            $t->foreign(['lead_id', 'company_id'])->references(['id', 'company_id'])->on('crm_leads');
            $t->foreign(['stage_id', 'pipeline_id', 'company_id'])->references(['id', 'pipeline_id', 'company_id'])->on('crm_stages');
            $t->index(['company_id', 'assigned_to', 'status']);
        });
        Schema::create('crm_activities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('company_id')->constrained();
            $t->uuid('lead_id')->nullable();
            $t->uuid('opportunity_id')->nullable();
            $t->string('subject', 160);
            $t->string('type', 20);
            $t->text('note')->nullable();
            $t->timestampTz('due_at');
            $t->string('status', 20)->default('planned');
            $t->timestampTz('completed_at')->nullable();
            $t->integer('revision')->default(1);
            $t->timestampsTz();
            $t->foreign(['lead_id', 'company_id'])->references(['id', 'company_id'])->on('crm_leads');
            $t->foreign(['opportunity_id', 'company_id'])->references(['id', 'company_id'])->on('crm_opportunities');
            $t->index(['company_id', 'due_at']);
        });
        Schema::create('crm_history', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('company_id')->constrained();
            $t->uuid('resource_id');
            $t->string('kind', 20);
            $t->uuid('actor_id');
            $t->string('action', 40);
            $t->uuid('from_stage')->nullable();
            $t->uuid('to_stage')->nullable();
            $t->string('reason', 500)->nullable();
            $t->timestampTz('occurred_at')->useCurrent();
            $t->index(['company_id', 'resource_id']);
        });
        DB::unprepared("CREATE UNIQUE INDEX crm_active_lead_email ON crm_leads (company_id,email) WHERE email IS NOT NULL AND status IN ('new','qualified');
            ALTER TABLE crm_stages ADD CHECK (probability BETWEEN 0 AND 100 AND position > 0);
            ALTER TABLE crm_leads ADD CHECK (status IN ('new','qualified','lost','converted'));
            ALTER TABLE crm_opportunities ADD CHECK (status IN ('open','won','lost') AND (expected_amount IS NULL OR expected_amount >= 0));
            ALTER TABLE crm_activities ADD CHECK ((lead_id IS NULL) <> (opportunity_id IS NULL));
            ALTER TABLE crm_activities ADD CHECK (type IN ('call','meeting','task') AND status IN ('planned','done','cancelled'));
            CREATE TRIGGER crm_history_immutable BEFORE UPDATE OR DELETE ON crm_history FOR EACH ROW EXECUTE FUNCTION deny_audit_change();");
    }

    public function down(): void
    {
        foreach (['crm_history', 'crm_activities', 'crm_opportunities', 'crm_leads', 'crm_stages', 'crm_pipelines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
