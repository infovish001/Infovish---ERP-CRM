<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\ProductService;
use App\Modules\Finance\Application\FinanceDocuments;
use App\Modules\Finance\Application\FinanceLedger;
use App\Modules\Finance\Application\FinancePayments;
use App\Modules\Finance\Application\FinancePrints;
use App\Modules\Finance\Application\FinanceReconciliation;
use App\Modules\Finance\Application\FinanceReports;
use App\Modules\Finance\Application\FinanceSetup;
use App\Modules\Parties\Application\ContactService;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Parties\Domain\Contracts\NewCustomer;
use App\Modules\Sales\Application\SalesService;
use App\Modules\Sales\Application\TaxProfile;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\ModuleRegistry\TenantSchema;
use App\Shared\Events\Outbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Support\TenantIntegrationTest;

class M06FinanceTest extends TenantIntegrationTest
{
    private string $companyA;

    private array $accounts = [];

    private string $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyA = $this->company($this->a);
        $this->execute($this->a, function () {
            foreach (['Parties', 'Catalog', 'CRM', 'Sales', 'Finance'] as $module) {
                app(ModuleAccess::class)->setEnabled($module, true);
            }
            foreach (['receivable' => 'asset', 'payable' => 'liability', 'revenue' => 'income', 'expense' => 'expense', 'vat_input' => 'asset', 'vat_output' => 'liability', 'bank' => 'asset', 'equity' => 'equity'] as $code => $type) {
                $this->accounts[$code] = app(FinanceSetup::class)->account($this->companyA, ['code' => strtoupper($code), 'name' => 'Test '.$code, 'type' => $type, 'bank' => $code === 'bank', 'active' => true], null, $this->key());
            }
            $this->period = app(FinanceSetup::class)->period($this->companyA, ['name' => 'Test year', 'starts_on' => substr($this->today(), 0, 4).'-01-01', 'ends_on' => substr($this->today(), 0, 4).'-12-31'], $this->key());
            app(FinanceSetup::class)->configure($this->companyA, $this->settings());
        });
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function today(): string
    {
        return CarbonImmutable::now('Asia/Colombo')->toDateString();
    }

    private function settings(array $extra = []): array
    {
        return $extra + array_intersect_key($this->accounts, array_flip(['receivable', 'payable', 'revenue', 'expense', 'vat_input', 'vat_output'])) + ['revision' => 0, 'seller_name' => 'Synthetic Finance seller', 'seller_address' => 'Synthetic Sri Lanka address', 'vat_registered' => true, 'tin' => '123456789', 'vat_registration' => '123456789-7000', 'vat_effective_from' => '2024-01-01', 'issuer_code' => 'TEST01'];
    }

    private function lines(string $amount = '100.25'): array
    {
        return [['account_id' => $this->accounts['bank'], 'debit' => $amount, 'credit' => '0'], ['account_id' => $this->accounts['equity'], 'debit' => '0', 'credit' => $amount]];
    }

    private function order(): string
    {
        return $this->execute($this->a, function () {
            app(TaxProfile::class)->save($this->companyA, $this->settings() + ['country' => 'LK']);
            $party = app(Contacts::class)->createCustomer($this->companyA, new NewCustomer('Synthetic buyer', null, null), $this->key())->id;
            app(ProductService::class)->save($this->companyA, ['name' => 'Synthetic consulting', 'active' => true, 'type' => 'service', 'unit' => 'hour', 'sku' => 'FIN-TEST', 'list_price' => '100.00'], key: $this->key());
            $variant = DB::connection('tenant')->table('catalog_variants')->value('id');
            $quote = app(SalesService::class)->save($this->companyA, ['customer_id' => $party, 'title' => 'Finance source order', 'issue_date' => $this->today(), 'valid_until' => CarbonImmutable::now('Asia/Colombo')->addDays(30)->toDateString(), 'lines' => [['variant_id' => $variant, 'quantity' => '2.5', 'unit_price' => '123.456789', 'discount_percent' => '10', 'tax_code' => 'standard']]], key: $this->key());
            app(SalesService::class)->transition($this->companyA, $quote, ['action' => 'issue', 'revision' => 1]);

            return app(SalesService::class)->convert($this->companyA, $quote, ['revision' => 2], $this->key());
        });
    }

    private function invoice(string $order): array
    {
        return ['order_id' => $order, 'tax_invoice' => true, 'buyer_name' => 'Synthetic Buyer Legal Name', 'buyer_address' => 'Synthetic buyer address', 'buyer_tin' => '987654321', 'supply_date' => $this->today(), 'request_evidence' => 'Synthetic timely written request by registered purchaser', 'date' => $this->today(), 'due_date' => CarbonImmutable::now('Asia/Colombo')->addDays(30)->toDateString()];
    }

    public function test_balanced_posting_reports_reversal_and_exact_retry(): void
    {
        $this->execute($this->a, function () {
            $draft = app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Synthetic contribution', 'lines' => $this->lines()], null, $this->key());
            $journal = app(FinanceLedger::class)->postDraft($this->companyA, $draft, 1, $this->key());
            $this->assertSame($journal, app(FinanceLedger::class)->postDraft($this->companyA, $draft, 1, $this->key()));
            $balance = app(FinanceReports::class)->balance($this->companyA, $this->accounts['bank'], $this->today());
            $this->assertSame('100.25', $balance->net);
            $trial = app(FinanceReports::class)->trial($this->companyA, substr($this->today(), 0, 4).'-01-01', $this->today());
            $this->assertSame('0.00', $trial['period_difference']);
            $this->assertSame('0.00', $trial['position_difference']);
            $reverse = app(FinanceLedger::class)->reverse($this->companyA, $journal, $this->today(), 'Synthetic correction', $this->key());
            $this->assertNotSame($reverse, $journal);
            $this->assertSame('0.00', app(FinanceReports::class)->balance($this->companyA, $this->accounts['bank'], $this->today())->net);
            $this->assertSame(2, DB::connection('tenant')->table('finance_journals')->count());
        });
    }

    public function test_unbalanced_posting_period_locks_and_control_accounts_fail(): void
    {
        $this->execute($this->a, function () {
            $lines = $this->lines();
            $lines[1]['credit'] = '99';
            $draft = app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Unbalanced test', 'lines' => $lines], null, $this->key());
            try {
                app(FinanceLedger::class)->postDraft($this->companyA, $draft, 1, $this->key());
                $this->fail('Unbalanced posted');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
            app(FinanceSetup::class)->lock($this->companyA, $this->period, ['locked' => true, 'reason' => 'Test lock', 'revision' => 1]);
            $valid = app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Locked test', 'lines' => $this->lines()], null, $this->key());
            try {
                app(FinanceLedger::class)->postDraft($this->companyA, $valid, 1, $this->key());
                $this->fail('Locked posted');
            } catch (HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
            $this->assertSame(0, DB::connection('tenant')->table('finance_journals')->count());
            $bad = $this->lines();
            $bad[0]['account_id'] = $this->accounts['receivable'];
            try {
                app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Control test', 'lines' => $bad], null, $this->key());
                $this->fail('Control draft accepted');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        });
    }

    public function test_runtime_cannot_edit_posted_rows_and_database_rejects_unbalanced_commit(): void
    {
        $this->execute($this->a, function () {
            $draft = app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Immutable test', 'lines' => $this->lines()], null, $this->key());
            $journal = app(FinanceLedger::class)->postDraft($this->companyA, $draft, 1, $this->key());
            try {
                DB::connection('tenant')->table('finance_lines')->where('journal_id', $journal)->update(['debit' => '123']);
                $this->fail('Ledger edited');
            } catch (\PDOException) {
                $this->assertTrue(true);
            }
            try {
                DB::connection('tenant')->transaction(function () {
                    DB::connection('tenant')->table('finance_journals')->insert(['id' => (string) Str::uuid(), 'company_id' => $this->companyA, 'period_id' => $this->period, 'number' => 'TEST-UNBALANCED', 'date' => $this->today(), 'narration' => 'Synthetic constraint test', 'source_kind' => 'manual', 'source_id' => (string) Str::uuid(), 'actor_id' => $this->owner->id, 'posted_at' => now()]);
                });
                $this->fail('Unbalanced committed');
            } catch (\PDOException) {
                $this->assertTrue(true);
            }
            $this->assertSame(1, DB::connection('tenant')->table('finance_journals')->count());
            try {
                DB::connection('tenant')->transaction(function () use ($journal) {
                    $rows = DB::connection('tenant')->table('finance_lines')->where('journal_id', $journal)->get()->map(function ($row) {
                        $data = (array) $row;
                        $data['id'] = (string) Str::uuid();

                        return $data;
                    })->all();
                    DB::connection('tenant')->table('finance_lines')->insert($rows);
                });
                $this->fail('Posted journal accepted additional balanced lines.');
            } catch (\PDOException) {
                $this->assertSame(2, DB::connection('tenant')->table('finance_lines')->where('journal_id', $journal)->count());
            }

        });
    }

    public function test_invoice_post_payment_void_aging_and_pdf_issuance(): void
    {
        $order = $this->order();
        $this->execute($this->a, function () use ($order) {
            $invoice = app(FinanceDocuments::class)->save($this->companyA, 'invoice', $this->invoice($order), null, $this->key());
            app(FinanceDocuments::class)->post($this->companyA, $invoice, 1, $this->key());
            $row = DB::connection('tenant')->table('finance_documents')->where('id', $invoice)->first();
            $this->assertMatchesRegularExpression('/^[0-9]{2}[A-Z]{3}_TEST01_[0-9]{9}$/', $row->number);
            $this->assertSame('327.78', $row->total);
            $this->assertSame('327.78', app(FinanceReports::class)->balance($this->companyA, $this->accounts['receivable'], $this->today())->net);
            $payment = app(FinancePayments::class)->record($this->companyA, ['document_id' => $invoice, 'bank_account_id' => $this->accounts['bank'], 'date' => $this->today(), 'reference' => 'Test receipt', 'amount' => '100.00'], $this->key());
            $this->assertSame('227.78', app(FinanceDocuments::class)->outstanding($this->companyA, $invoice));
            app(FinancePayments::class)->void($this->companyA, $payment, $this->today(), 'Synthetic bank entry correction', $this->key());
            $this->assertSame('327.78', app(FinanceDocuments::class)->outstanding($this->companyA, $invoice));
            $aging = app(FinanceReports::class)->aging($this->companyA, $this->today());
            $this->assertSame('327.78', $aging['buckets']['invoice']['current']);
            $key = $this->key();
            $print = app(FinancePrints::class)->issue($this->companyA, $invoice, 'original', null, $key);
            $this->assertSame($print, app(FinancePrints::class)->issue($this->companyA, $invoice, 'original', null, $key));
            $pdf = app(FinancePrints::class)->render($this->companyA, $print);
            $this->assertStringStartsWith('%PDF-', $pdf['bytes']);
            $this->assertSame($pdf['bytes'], app(FinancePrints::class)->render($this->companyA, $print)['bytes']);
            $this->assertSame(hash('sha256', $pdf['bytes']), DB::connection('tenant')->table('finance_prints')->where('id', $print)->value('sha256'));
            $copy = app(FinancePrints::class)->issue($this->companyA, $invoice, 'copy', 'Original lost', $this->key());
            $this->assertNotSame($print, $copy);
            $this->assertSame(2, DB::connection('tenant')->table('finance_prints')->count());
            try {
                app(FinanceLedger::class)->reverse($this->companyA, $row->journal_id, $this->today(), 'Invalid tax correction', $this->key());
                $this->fail('Invoice journal reversed manually');
            } catch (HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
            try {
                app(FinancePayments::class)->record($this->companyA, ['document_id' => $invoice, 'bank_account_id' => $this->accounts['bank'], 'date' => $this->today(), 'reference' => 'Overpayment', 'amount' => '400.00'], $this->key());
                $this->fail('Overpayment accepted');
            } catch (HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
        });
    }

    public function test_expense_bill_payment_and_trial_balance_reconcile(): void
    {
        $this->execute($this->a, function () {
            $supplier = app(ContactService::class)->save($this->companyA, ['name' => 'Synthetic supplier', 'customer' => false, 'supplier' => true, 'active' => true], key: $this->key());
            $data = ['party_id' => $supplier, 'supplier_reference' => 'SUP-TEST-1', 'description' => 'Reviewed synthetic expense', 'date' => $this->today(), 'due_date' => $this->today(), 'net' => '80.00', 'vat' => '14.40', 'vat_evidence' => 'Synthetic eligible input tax invoice verification'];
            $bill = app(FinanceDocuments::class)->save($this->companyA, 'bill', $data, null, $this->key());
            app(FinanceDocuments::class)->post($this->companyA, $bill, 1, $this->key());
            app(FinancePayments::class)->record($this->companyA, ['document_id' => $bill, 'bank_account_id' => $this->accounts['bank'], 'date' => $this->today(), 'reference' => 'Synthetic payment', 'amount' => '94.40'], $this->key());
            $this->assertSame('0.00', app(FinanceDocuments::class)->outstanding($this->companyA, $bill));
            $trial = app(FinanceReports::class)->trial($this->companyA, substr($this->today(), 0, 4).'-01-01', $this->today());
            $this->assertSame('80.00', $trial['expense']);
            $this->assertSame('0.00', $trial['position_difference']);
            $this->assertSame('0.00', $trial['period_difference']);
        });
    }

    public function test_finance_api_roles_company_scope_mfa_and_csrf(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/finance';
        DB::connection('pgsql')->table('memberships')->insert(['tenant_id' => $this->a, 'user_id' => $this->reader->id, 'role' => 'auditor', 'active' => true, 'company_ids' => json_encode([$this->companyA]), 'branch_ids' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $this->requestAs($this->reader, $this->a)->getJson($url.'/setup?company_id='.$this->companyA)->assertOk();
        $data = ['company_id' => $this->companyA, 'date' => $this->today(), 'narration' => 'API journal', 'lines' => $this->lines()];
        $this->postJson($url.'/drafts', $data, ['Idempotency-Key' => $this->key()])->assertForbidden();
        DB::connection('pgsql')->table('memberships')->where('tenant_id', $this->a)->where('user_id', $this->reader->id)->update(['role' => 'member']);
        $this->requestAs($this->reader, $this->a)->getJson($url.'/reports?company_id='.$this->companyA.'&from='.$this->today().'&to='.$this->today())->assertForbidden();
        $this->owner->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->requestAs($this->owner, $this->a)->getJson($url.'/setup?company_id='.$this->companyA)->assertForbidden();
        $this->owner->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->app->instance('env', 'local');
        $token = Str::random(40);
        $this->requestAs($this->owner, $this->a)->withSession(['_token' => $token])->postJson($url.'/drafts', $data, ['Idempotency-Key' => $this->key()])->assertStatus(419);
        $id = $this->postJson($url.'/drafts', $data, ['Idempotency-Key' => $this->key(), 'X-CSRF-TOKEN' => $token])->assertCreated()->json('id');
        $other = $this->company($this->a);
        $this->getJson($url.'/drafts/'.$id.'?company_id='.$other)->assertNotFound();
        $companyB = $this->company($this->b);
        $this->execute($this->b, function () {
            foreach (['Parties', 'Catalog', 'CRM', 'Sales', 'Finance'] as $m) {
                app(ModuleAccess::class)->setEnabled($m, true);
            }
        });
        $this->requestAs($this->owner, $this->b)->getJson('http://'.$this->hostB.'/api/v1/finance/drafts/'.$id.'?company_id='.$companyB)->assertNotFound();
    }

    public function test_tax_invoice_validation_source_uniqueness_and_print_guards(): void
    {
        $order = $this->order();
        $url = 'http://'.$this->hostA.'/api/v1/finance/documents/invoice';
        $data = $this->invoice($order) + ['company_id' => $this->companyA];
        $this->requestAs($this->owner, $this->a)->postJson($url, array_replace($data, ['buyer_tin' => null]), ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        $this->postJson($url, array_replace($data, ['tax_invoice' => false]), ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        $this->postJson($url, array_replace($data, ['supply_date' => CarbonImmutable::now('Asia/Colombo')->subDays(29)->toDateString()]), ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        $id = $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertConflict();
        $base = 'http://'.$this->hostA.'/api/v1/finance/documents/'.$id;
        $this->postJson($base.'/post', ['company_id' => $this->companyA, 'revision' => 2], ['Idempotency-Key' => $this->key()])->assertConflict();
        $this->postJson($base.'/post', ['company_id' => $this->companyA, 'revision' => 1], ['Idempotency-Key' => $this->key()])->assertOk();
        $this->postJson($base.'/print', ['company_id' => $this->companyA, 'kind' => 'copy', 'reason' => 'Lost'], ['Idempotency-Key' => $this->key()])->assertConflict();
        $this->postJson($base.'/print', ['company_id' => $this->companyA, 'kind' => 'original'], ['Idempotency-Key' => $this->key()])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson($base.'/print', ['company_id' => $this->companyA, 'kind' => 'original'], ['Idempotency-Key' => $this->key()])->assertConflict();
        $this->postJson($base.'/cancel', ['company_id' => $this->companyA, 'revision' => 2, 'reason' => 'Invalid direct cancellation'])->assertConflict();
    }

    public function test_concurrent_posting_and_overpayment_preserve_single_effects(): void
    {
        $draft = $this->execute($this->a, fn () => app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Concurrent journal', 'lines' => $this->lines()], null, $this->key()));
        $results = $this->concurrent(['operation' => 'draft', 'draft' => $draft]);
        $this->assertCount(1, array_unique($results), json_encode($results));
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $results[0]);
        $order = $this->order();
        $invoice = $this->execute($this->a, function () use ($order) {
            $id = app(FinanceDocuments::class)->save($this->companyA, 'invoice', $this->invoice($order), null, $this->key());
            app(FinanceDocuments::class)->post($this->companyA, $id, 1, $this->key());

            return $id;
        });
        $results = $this->concurrent(['operation' => 'payment', 'payload' => ['document_id' => $invoice, 'bank_account_id' => $this->accounts['bank'], 'date' => $this->today(), 'reference' => 'Concurrent receipt', 'amount' => '200.00']]);
        sort($results);
        $this->assertContains('HTTP:409', $results);
        $this->execute($this->a, function () use ($invoice) {
            $this->assertSame(1, DB::connection('tenant')->table('finance_payments')->count());
            $this->assertSame('127.78', app(FinanceDocuments::class)->outstanding($this->companyA, $invoice));
        });
    }

    private function concurrent(array $payload): array
    {
        $c = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'infovish_test', 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'], 'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $p = new Process([PHP_BINARY, 'tests/Support/post-finance.php'], base_path(), $env, json_encode($payload + ['company' => $this->companyA, 'tenant' => $this->a, 'actor' => $this->owner->id, 'key' => $this->key()]), 60);
            $p->start();
            $processes[] = $p;
        }$results = [];
        foreach ($processes as $p) {
            $p->wait();
            $this->assertTrue($p->isSuccessful(), 'Finance subprocess failed.');
            $results[] = trim($p->getOutput());
        }

        return $results;
    }

    public function test_posting_failure_rolls_back_document_ledger_counter_and_events(): void
    {
        $queue = 'finance-test-'.Str::uuid();
        $order = $this->order();
        $this->execute($this->a, function () use ($order, $queue) {
            $id = app(FinanceDocuments::class)->save($this->companyA, 'invoice', $this->invoice($order), null, $this->key());
            $before = DB::connection('tenant')->table('event_outbox')->count();
            $config = config('database.connections.provisioner');
            $config['database'] = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->value('database_name');
            config(['database.connections.finance_fault' => $config]);
            DB::connection('finance_fault')->unprepared("CREATE FUNCTION fail_finance_test() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'synthetic finance failure'; END; \$\$; CREATE TRIGGER finance_fault BEFORE INSERT ON finance_lines FOR EACH ROW EXECUTE FUNCTION fail_finance_test();");
            try {
                app(FinanceDocuments::class)->post($this->companyA, $id, 1, $this->key());
                $this->fail('Injected failure committed');
            } catch (\PDOException) {
            }
            $this->assertSame('draft', DB::connection('tenant')->table('finance_documents')->where('id', $id)->value('state'));
            $this->assertSame(0, DB::connection('tenant')->table('finance_journals')->count());
            $this->assertSame($before, DB::connection('tenant')->table('event_outbox')->count());
            $this->assertSame(0, DB::connection('tenant')->table('document_counters')->whereIn('series', ['TAX', 'JRN'])->count());
            DB::connection('finance_fault')->unprepared('DROP TRIGGER finance_fault ON finance_lines; DROP FUNCTION fail_finance_test();');
            DB::purge('finance_fault');
            app(FinanceDocuments::class)->post($this->companyA, $id, 1, $this->key());
            $this->assertStringEndsWith('_000000001', DB::connection('tenant')->table('finance_documents')->where('id', $id)->value('number'));
            app(Outbox::class)->dispatch($queue);
        });
        $this->artisan('queue:work', ['connection' => 'redis', '--queue' => $queue, '--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();
        $this->execute($this->a, function () {
            $this->assertSame(0, DB::connection('tenant')->table('event_outbox')->where('state', '!=', 'delivered')->count());
        });
    }

    public function test_schema_four_upgrade_preserves_records_and_finance_entitlement(): void
    {
        $company = $this->company($this->b);
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->b)->first();
        $config = config('database.connections.provisioner');
        $config['database'] = $row->database_name;
        config(['database.connections.finance_upgrade' => $config]);
        $db = DB::connection('finance_upgrade');
        $this->dropFinanceForUpgrade($db);
        $db->table('module_installations')->where('module', 'Finance')->delete();
        DB::purge('finance_upgrade');
        DB::connection('provisioner')->table('tenants')->where('id', $this->b)->update(['schema_version' => 4]);
        DB::connection('provisioner')->table('tenant_module_entitlements')->where('tenant_id', $this->b)->where('module', 'Finance')->update(['allowed' => false]);
        app(TenantSchema::class)->upgrade($row->slug);
        app(TenantSchema::class)->upgrade($row->slug);
        $this->execute($this->b, function () use ($company) {
            $this->assertSame($company, DB::connection('tenant')->table('companies')->value('id'));
            $this->assertSame(0, DB::connection('tenant')->table('finance_journals')->count());
            $this->assertFalse(app(ModuleAccess::class)->allowed('Finance'));
        });
        $this->assertSame(TenantSchema::VERSION, DB::connection('pgsql')->table('tenants')->where('id', $this->b)->value('schema_version'));
        $this->assertFalse(DB::connection('pgsql')->table('tenant_module_entitlements')->where('tenant_id', $this->b)->where('module', 'Finance')->value('allowed'));
    }

    public function test_statement_matches_outstanding_items_and_blocks_backdating(): void
    {
        $this->execute($this->a, function () {
            $draft = app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Synthetic deposit', 'lines' => $this->lines('100.00')], null, $this->key());
            $journal = app(FinanceLedger::class)->postDraft($this->companyA, $draft, 1, $this->key());
            $line = DB::connection('tenant')->table('finance_lines')->where('journal_id', $journal)->where('bank', true)->value('id');
            $other = app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Unpresented cheque', 'lines' => [['account_id' => $this->accounts['expense'], 'debit' => '25', 'credit' => '0'], ['account_id' => $this->accounts['bank'], 'debit' => '0', 'credit' => '25']]], null, $this->key());
            app(FinanceLedger::class)->postDraft($this->companyA, $other, 1, $this->key());
            $statement = app(FinanceReconciliation::class)->save($this->companyA, ['bank_account_id' => $this->accounts['bank'], 'starts_on' => $this->today(), 'ends_on' => $this->today(), 'opening' => '0', 'closing' => '100', 'opening_cleared_confirmed' => true, 'lines' => [['date' => $this->today(), 'reference' => 'Synthetic deposit', 'amount' => '100', 'line_id' => $line]]], null, $this->key());
            app(FinanceReconciliation::class)->finalize($this->companyA, $statement, 1, $this->key());
            $summary = json_decode(DB::connection('tenant')->table('finance_statements')->where('id', $statement)->value('summary'), true);
            $this->assertSame('75.00', $summary['book_balance']);
            $this->assertSame('-25.00', $summary['outstanding_net']);
            $this->assertSame('100.00', $summary['adjusted_book']);
            $late = app(FinanceLedger::class)->draft($this->companyA, ['date' => $this->today(), 'narration' => 'Late bank entry', 'lines' => $this->lines()], null, $this->key());
            try {
                app(FinanceLedger::class)->postDraft($this->companyA, $late, 1, $this->key());
                $this->fail('Reconciled bank backdating accepted');
            } catch (HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }$this->assertSame(2, DB::connection('tenant')->table('finance_journals')->count());
        });
    }
}
