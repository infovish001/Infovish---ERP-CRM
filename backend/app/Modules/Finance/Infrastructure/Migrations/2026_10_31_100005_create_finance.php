<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies');
            $t->string('code', 20);
            $t->string('name', 160);
            $t->string('type', 20);
            $t->boolean('bank')->default(false);
            $t->boolean('active')->default(true);
            $t->integer('revision');
            $t->timestampsTz();
            $t->unique(['company_id', 'id']);
            $t->unique(['company_id', 'code']);
        });
        Schema::create('finance_periods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies');
            $t->string('name', 100);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('locked')->default(false);
            $t->string('reason', 500)->nullable();
            $t->integer('revision');
            $t->timestampsTz();
            $t->unique(['company_id', 'id']);
        });
        Schema::create('finance_settings', function (Blueprint $t) {
            $t->uuid('company_id')->primary();
            $t->foreign('company_id')->references('id')->on('companies');
            $t->jsonb('settings');
            $t->integer('revision');
            $t->timestampsTz();
        });
        Schema::create('finance_journals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('period_id');
            $t->foreign(['company_id', 'period_id'])->references(['company_id', 'id'])->on('finance_periods');
            $t->string('number', 30);
            $t->date('date');
            $t->string('narration', 500);
            $t->bigInteger('posting_xid')->default(DB::raw('txid_current()'));
            $t->string('source_kind', 30);
            $t->uuid('source_id');
            $t->uuid('reversal_of')->nullable()->unique();

            $t->uuid('actor_id');
            $t->timestampTz('posted_at');
            $t->unique(['company_id', 'id']);
            $t->unique(['company_id', 'number']);
            $t->unique(['company_id', 'source_kind', 'source_id']);
            $t->index(['company_id', 'date']);
        });
        Schema::create('finance_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('journal_id');
            $t->uuid('account_id');
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('finance_journals');
            $t->foreign(['company_id', 'account_id'])->references(['company_id', 'id'])->on('finance_accounts');
            $t->string('account_code', 20);
            $t->string('account_name', 160);
            $t->string('account_type', 20);
            $t->boolean('bank');
            $t->string('description', 300)->nullable();
            $t->decimal('debit', 24, 2);
            $t->decimal('credit', 24, 2);
            $t->unique(['company_id', 'id']);
            $t->index(['company_id', 'account_id']);
        });
        Schema::create('finance_drafts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies');
            $t->string('number', 30);
            $t->date('date');
            $t->string('narration', 500);
            $t->jsonb('lines');
            $t->string('state', 20)->default('draft');
            $t->uuid('journal_id')->nullable()->unique();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('finance_journals');
            $t->integer('revision');
            $t->timestampsTz();
            $t->unique(['company_id', 'id']);
        });
        Schema::create('finance_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies');
            $t->string('kind', 20);
            $t->string('number', 40);
            $t->uuid('party_id');
            $t->uuid('order_id')->nullable()->unique();
            $t->string('supplier_reference', 100)->nullable();
            $t->date('date');
            $t->date('due_date');
            $t->jsonb('snapshot');
            $t->decimal('net', 24, 2);
            $t->decimal('vat', 24, 2);
            $t->decimal('total', 24, 2);
            $t->string('state', 20)->default('draft');
            $t->uuid('journal_id')->nullable()->unique();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('finance_journals');
            $t->integer('revision');
            $t->timestampsTz();
            $t->unique(['company_id', 'id']);
            $t->unique(['company_id', 'kind', 'party_id', 'supplier_reference']);
            $t->index(['company_id', 'kind', 'state']);
        });
        Schema::create('finance_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('document_id');
            $t->uuid('bank_account_id');
            $t->foreign(['company_id', 'document_id'])->references(['company_id', 'id'])->on('finance_documents');
            $t->foreign(['company_id', 'bank_account_id'])->references(['company_id', 'id'])->on('finance_accounts');
            $t->string('number', 30);
            $t->date('date');
            $t->string('reference', 100);
            $t->decimal('amount', 24, 2);
            $t->uuid('journal_id')->unique();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('finance_journals');
            $t->uuid('actor_id');
            $t->timestampTz('created_at');
            $t->unique(['company_id', 'id']);
        });
        Schema::create('finance_payment_voids', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('payment_id')->unique();
            $t->foreign(['company_id', 'payment_id'])->references(['company_id', 'id'])->on('finance_payments');
            $t->uuid('journal_id')->unique();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('finance_journals');
            $t->string('reason', 500);
            $t->uuid('actor_id');
            $t->timestampTz('created_at');
        });
        Schema::create('finance_statements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('bank_account_id');
            $t->foreign(['company_id', 'bank_account_id'])->references(['company_id', 'id'])->on('finance_accounts');
            $t->string('number', 30);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->decimal('opening', 24, 2);
            $t->decimal('closing', 24, 2);
            $t->jsonb('lines');
            $t->jsonb('summary')->nullable();
            $t->boolean('opening_cleared_confirmed');
            $t->string('state', 20)->default('draft');
            $t->integer('revision');
            $t->timestampsTz();
            $t->unique(['company_id', 'id']);
        });
        Schema::create('finance_matches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('statement_id');
            $t->foreign(['company_id', 'statement_id'])->references(['company_id', 'id'])->on('finance_statements');
            $t->uuid('line_id')->unique();
            $t->foreign(['company_id', 'line_id'])->references(['company_id', 'id'])->on('finance_lines');
            $t->integer('position');
            $t->unique(['statement_id', 'position']);
            $t->timestampTz('created_at');
        });
        Schema::create('finance_prints', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('document_id');
            $t->foreign(['company_id', 'document_id'])->references(['company_id', 'id'])->on('finance_documents');
            $t->string('kind', 20);
            $t->text('pdf_base64');
            $t->string('sha256', 64);
            $t->string('reason', 500)->nullable();
            $t->uuid('actor_id');
            $t->timestampTz('created_at');
        });
        DB::unprepared("ALTER TABLE finance_journals ADD FOREIGN KEY (company_id,reversal_of) REFERENCES finance_journals(company_id,id);
            ALTER TABLE finance_accounts ADD CHECK (type IN ('asset','liability','equity','income','expense') AND (NOT bank OR type='asset') AND revision>0);
            ALTER TABLE finance_periods ADD CHECK (starts_on<=ends_on AND revision>0);
            ALTER TABLE finance_lines ADD CHECK ((debit>0 AND credit=0) OR (credit>0 AND debit=0));
            ALTER TABLE finance_drafts ADD CHECK (state IN ('draft','posted') AND ((state='posted')=(journal_id IS NOT NULL)));
            ALTER TABLE finance_documents ADD CHECK (kind IN ('invoice','bill') AND state IN ('draft','posted','cancelled') AND net>=0 AND vat>=0 AND total=net+vat AND total>0 AND due_date>=date AND ((state='posted')=(journal_id IS NOT NULL)));
            ALTER TABLE finance_payments ADD CHECK (amount>0);
            ALTER TABLE finance_statements ADD CHECK (starts_on<=ends_on AND state IN ('draft','finalized'));
            CREATE UNIQUE INDEX finance_one_original ON finance_prints(document_id) WHERE kind='original';
            CREATE FUNCTION finance_freeze_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN IF OLD.state IN ('posted','cancelled','finalized') THEN RAISE EXCEPTION 'Posted finance record is immutable'; END IF;
            IF NEW.revision<>OLD.revision+1 THEN RAISE EXCEPTION 'Finance revision must advance'; END IF; RETURN NEW; END; \$\$;
            CREATE FUNCTION finance_match_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN IF NOT EXISTS(SELECT 1 FROM finance_statements s JOIN finance_lines l ON l.company_id=s.company_id AND l.account_id=s.bank_account_id WHERE s.id=NEW.statement_id AND s.company_id=NEW.company_id AND s.state='draft' AND l.id=NEW.line_id) THEN RAISE EXCEPTION 'Reconciliation matches require a draft statement and its bank account'; END IF; RETURN NEW; END; \$\$;
            CREATE TRIGGER finance_match_insert BEFORE INSERT ON finance_matches FOR EACH ROW EXECUTE FUNCTION finance_match_guard();
            CREATE FUNCTION finance_period_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN PERFORM pg_advisory_xact_lock(hashtext('module-lifecycle'));
            IF EXISTS(SELECT 1 FROM finance_periods p WHERE p.company_id=NEW.company_id AND p.id<>NEW.id AND daterange(p.starts_on,p.ends_on,'[]') && daterange(NEW.starts_on,NEW.ends_on,'[]')) THEN RAISE EXCEPTION 'Finance periods overlap'; END IF; RETURN NEW; END; \$\$;
            CREATE TRIGGER finance_period_ranges BEFORE INSERT OR UPDATE ON finance_periods FOR EACH ROW EXECUTE FUNCTION finance_period_guard();
            CREATE FUNCTION finance_balance_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$
            DECLARE jid uuid; j finance_journals%ROWTYPE; n bigint; d numeric; c numeric;
            BEGIN jid=(to_jsonb(NEW)->>(CASE WHEN TG_TABLE_NAME='finance_journals' THEN 'id' ELSE 'journal_id' END))::uuid;
            SELECT * INTO j FROM finance_journals WHERE id=jid;
            IF j.posting_xid <> txid_current() THEN RAISE EXCEPTION 'Posted journal lines cannot be extended'; END IF;
            PERFORM pg_advisory_xact_lock(hashtext('module-lifecycle'));
            IF NOT EXISTS(SELECT 1 FROM finance_periods p WHERE p.id=j.period_id AND p.company_id=j.company_id AND NOT p.locked AND j.date BETWEEN p.starts_on AND p.ends_on) THEN RAISE EXCEPTION 'Posting period is locked or invalid'; END IF;
            SELECT count(*),sum(debit),sum(credit) INTO n,d,c FROM finance_lines WHERE journal_id=jid;
            IF n<2 OR d<>c OR d<=0 THEN RAISE EXCEPTION 'Journal must balance with positive totals'; END IF;
            IF EXISTS(SELECT 1 FROM finance_lines l JOIN finance_statements s ON s.company_id=l.company_id AND s.bank_account_id=l.account_id WHERE l.journal_id=jid AND s.state='finalized' AND j.date<=s.ends_on) THEN RAISE EXCEPTION 'Bank window is finalized'; END IF;
            RETURN NEW; END; \$\$;
            CREATE CONSTRAINT TRIGGER finance_journal_balance AFTER INSERT ON finance_journals DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION finance_balance_guard();
            CREATE CONSTRAINT TRIGGER finance_line_balance AFTER INSERT ON finance_lines DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION finance_balance_guard();");
        foreach (['finance_journals', 'finance_lines', 'finance_payments', 'finance_payment_voids', 'finance_matches', 'finance_prints'] as $table) {
            DB::unprepared('CREATE TRIGGER '.$table.'_immutable BEFORE UPDATE OR DELETE ON '.$table.' FOR EACH ROW EXECUTE FUNCTION deny_audit_change()');
        }
        foreach (['finance_drafts', 'finance_documents', 'finance_statements'] as $table) {
            DB::unprepared('CREATE TRIGGER '.$table.'_frozen BEFORE UPDATE ON '.$table.' FOR EACH ROW EXECUTE FUNCTION finance_freeze_guard()');
        }
    }

    public function down(): void
    {
        foreach (['finance_prints', 'finance_matches', 'finance_statements', 'finance_payment_voids', 'finance_payments', 'finance_documents', 'finance_drafts', 'finance_lines', 'finance_journals', 'finance_settings', 'finance_periods', 'finance_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['finance_balance_guard', 'finance_freeze_guard', 'finance_period_guard', 'finance_match_guard'] as $function) {
            DB::statement('DROP FUNCTION IF EXISTS '.$function.'()');
        }
    }
};
