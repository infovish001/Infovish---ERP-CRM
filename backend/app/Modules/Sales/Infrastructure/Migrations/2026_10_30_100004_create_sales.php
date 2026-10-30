<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_tax_profiles', function (Blueprint $t) {
            $t->uuid('company_id')->primary();
            $t->foreign('company_id')->references('id')->on('companies');
            $t->jsonb('settings');
            $t->integer('revision');
            $t->timestampsTz();
        });
        Schema::create('sales_quotes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies');
            $t->string('number', 30);
            $t->uuid('customer_id');
            $t->uuid('opportunity_id')->nullable();
            $t->uuid('created_by');
            $t->string('title', 160);
            $t->date('issue_date');
            $t->date('valid_until');
            $t->string('currency', 3);
            $t->string('status', 20)->default('draft');
            $t->text('terms')->nullable();
            $t->jsonb('snapshot');
            $t->decimal('net', 24, 2);
            $t->decimal('vat', 24, 2);
            $t->decimal('total', 24, 2);
            $t->integer('revision');
            $t->uuid('order_id')->nullable()->unique();
            $t->string('reason', 500)->nullable();
            $t->timestampsTz();
            $t->unique(['company_id', 'id']);
            $t->unique(['company_id', 'number']);
            $t->index(['company_id', 'status', 'updated_at']);
        });
        Schema::create('sales_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('quote_id')->unique();
            $t->foreign(['company_id', 'quote_id'])->references(['company_id', 'id'])->on('sales_quotes');
            $t->uuid('customer_id');
            $t->string('number', 30);
            $t->string('title', 160);
            $t->string('currency', 3);
            $t->string('status', 20)->default('confirmed');
            $t->jsonb('snapshot');
            $t->decimal('net', 24, 2);
            $t->decimal('vat', 24, 2);
            $t->decimal('total', 24, 2);
            $t->uuid('confirmed_by');
            $t->timestampTz('confirmed_at');
            $t->unique(['company_id', 'id']);
            $t->unique(['company_id', 'number']);
        });
        Schema::create('sales_history', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->uuid('quote_id');
            $t->foreign(['company_id', 'quote_id'])->references(['company_id', 'id'])->on('sales_quotes');
            $t->uuid('actor_id');
            $t->string('action', 40);
            $t->string('reason', 500)->nullable();
            $t->timestampTz('occurred_at');
            $t->index(['company_id', 'quote_id', 'occurred_at']);
        });
        DB::unprepared("ALTER TABLE sales_quotes ADD CONSTRAINT sales_quotes_order_fk FOREIGN KEY (company_id,order_id) REFERENCES sales_orders(company_id,id);
            ALTER TABLE sales_quotes ADD CHECK (status IN ('draft','issued','cancelled','converted') AND revision > 0 AND net >= 0 AND vat >= 0 AND total = net + vat AND valid_until >= issue_date AND currency = 'LKR' AND ((status = 'converted') = (order_id IS NOT NULL)));
            ALTER TABLE sales_orders ADD CHECK (status = 'confirmed' AND net >= 0 AND vat >= 0 AND total = net + vat AND currency = 'LKR');
            CREATE TRIGGER sales_order_immutable BEFORE UPDATE OR DELETE ON sales_orders FOR EACH ROW EXECUTE FUNCTION deny_audit_change();
            CREATE TRIGGER sales_history_immutable BEFORE UPDATE OR DELETE ON sales_history FOR EACH ROW EXECUTE FUNCTION deny_audit_change();
            CREATE FUNCTION guard_sales_quote() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Sales documents cannot be deleted'; END IF;
                IF OLD.status IN ('cancelled','converted') THEN RAISE EXCEPTION 'Terminal quotation is immutable'; END IF;
                IF NEW.revision <> OLD.revision + 1 THEN RAISE EXCEPTION 'Quotation revision must advance'; END IF;
                IF OLD.status = 'issued' THEN
                    IF NEW.status NOT IN ('cancelled','converted') OR (to_jsonb(NEW) - ARRAY['status','order_id','revision','updated_at','reason']) <> (to_jsonb(OLD) - ARRAY['status','order_id','revision','updated_at','reason']) THEN
                        RAISE EXCEPTION 'Issued quotation content is immutable';
                    END IF;
                ELSIF NEW.status NOT IN ('draft','issued','cancelled') THEN RAISE EXCEPTION 'Invalid quotation transition';
                END IF;
                RETURN NEW;
            END; \$\$;
            CREATE TRIGGER sales_quote_guard BEFORE UPDATE OR DELETE ON sales_quotes FOR EACH ROW EXECUTE FUNCTION guard_sales_quote();");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales_quotes DROP CONSTRAINT sales_quotes_order_fk');
        foreach (['sales_history', 'sales_orders', 'sales_quotes', 'sales_tax_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS guard_sales_quote()');
    }
};
