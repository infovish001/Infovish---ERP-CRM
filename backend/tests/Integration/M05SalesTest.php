<?php

namespace Tests\Integration;

use App\Modules\Catalog\Application\ProductService;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Parties\Domain\Contracts\NewCustomer;
use App\Modules\Sales\Application\SalesService;
use App\Modules\Sales\Application\TaxProfile;
use App\Modules\Sales\Domain\Contracts\Orders;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\ModuleRegistry\TenantSchema;
use App\Shared\Events\Outbox;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\TenantIntegrationTest;

class M05SalesTest extends TenantIntegrationTest
{
    private string $companyA;

    private string $customer;

    private string $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyA = $this->company($this->a);
        $this->execute($this->a, function () {
            foreach (['Parties', 'Catalog', 'CRM', 'Sales'] as $module) {
                app(ModuleAccess::class)->setEnabled($module, true);
            }
            $this->customer = app(Contacts::class)->createCustomer($this->companyA, new NewCustomer('Test customer', null, null), $this->key())->id;
            app(ProductService::class)->save($this->companyA, ['name' => 'Test service', 'active' => true, 'type' => 'service', 'unit' => 'hour', 'sku' => 'TEST-SERVICE', 'list_price' => '100.000000'], key: $this->key());
            $this->variant = DB::connection('tenant')->table('catalog_variants')->value('id');
            app(TaxProfile::class)->save($this->companyA, $this->profile());
        });
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function profile(array $extra = []): array
    {
        return $extra + ['country' => 'LK', 'seller_name' => 'Synthetic supplier', 'seller_address' => 'Synthetic Sri Lanka address', 'vat_registered' => true, 'tin' => '123456789', 'vat_registration' => '123456789-7000', 'vat_effective_from' => '2024-01-01', 'revision' => 0];
    }

    private function quote(array $extra = []): array
    {
        return $extra + ['customer_id' => $this->customer, 'title' => 'Test proposal', 'issue_date' => CarbonImmutable::now('Asia/Colombo')->toDateString(), 'valid_until' => CarbonImmutable::now('Asia/Colombo')->addDays(30)->toDateString(), 'terms' => 'Test terms', 'lines' => [['variant_id' => $this->variant, 'quantity' => '2.500000', 'unit_price' => '123.456789', 'discount_percent' => '10', 'tax_code' => 'standard']]];
    }

    private function create(): string
    {
        return $this->execute($this->a, fn () => app(SalesService::class)->save($this->companyA, $this->quote(), key: $this->key()));
    }

    public function test_quote_to_order_api_exact_totals_retry_and_immutable_content(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations';
        $data = $this->quote(['company_id' => $this->companyA]);
        $key = $this->key();
        $id = $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $key])->assertCreated()->json('id');
        $this->postJson($url, $data, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('id', $id);
        $this->postJson($url, array_replace($data, ['title' => 'Different']), ['Idempotency-Key' => $key])->assertConflict();
        $this->getJson($url.'/'.$id.'?company_id='.$this->companyA)->assertOk()->assertJsonPath('record.net', '277.78')->assertJsonPath('record.vat', '50.00')->assertJsonPath('record.total', '327.78')->assertJsonPath('record.snapshot.pricing.lines.0.unit_price', '123.456789');
        $this->postJson($url.'/'.$id.'/convert', ['company_id' => $this->companyA, 'revision' => 1], ['Idempotency-Key' => $this->key()])->assertConflict();
        $this->postJson($url.'/'.$id.'/transition', ['company_id' => $this->companyA, 'revision' => 1, 'action' => 'issue'])->assertOk();
        $this->putJson($url.'/'.$id, $data + ['revision' => 2])->assertConflict();
        $order = $this->postJson($url.'/'.$id.'/convert', ['company_id' => $this->companyA, 'revision' => 2], ['Idempotency-Key' => $this->key()])->assertOk()->json('id');
        $this->postJson($url.'/'.$id.'/convert', ['company_id' => $this->companyA, 'revision' => 2], ['Idempotency-Key' => $this->key()])->assertOk()->assertJsonPath('id', $order);
        $this->postJson($url.'/'.$id.'/convert', ['company_id' => $this->companyA, 'revision' => 1], ['Idempotency-Key' => $this->key()])->assertConflict();
        $this->getJson('http://'.$this->hostA.'/api/v1/sales/orders/'.$order.'?company_id='.$this->companyA)->assertOk()->assertJsonPath('record.number', 'SO-000000001')->assertJsonPath('record.total', '327.78')->assertJsonCount(3, 'history');
        $this->execute($this->a, function () use ($order, $id) {
            $this->assertSame('327.78', app(Orders::class)->find($this->companyA, $order)->total);
            $this->assertSame(1, DB::connection('tenant')->table('sales_orders')->count());
            $this->assertSame(0, DB::connection('tenant')->table('crm_opportunities')->count());
            $this->assertSame($order, DB::connection('tenant')->table('sales_quotes')->where('id', $id)->value('order_id'));
        });
    }

    public function test_tax_profile_classification_effective_date_and_rounding(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations';
        $base = $this->quote(['company_id' => $this->companyA]);
        $line = $base['lines'][0];
        foreach ([['quantity' => '0'], ['quantity' => 1.2], ['unit_price' => '1e2'], ['discount_percent' => '100.000001'], ['tax_code' => 'unregistered'], ['tax_code' => 'zero']] as $bad) {
            $this->requestAs($this->owner, $this->a)->postJson($url, array_replace($base, ['lines' => [array_replace($line, $bad)]]), ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        }
        $tiny = array_replace($line, ['quantity' => '1', 'unit_price' => '0.005', 'discount_percent' => '0']);
        $zero = array_replace($line, ['quantity' => '1', 'unit_price' => '100', 'discount_percent' => '0', 'tax_code' => 'zero', 'tax_basis' => 'Synthetic export qualification for test only']);
        $id = $this->postJson($url, array_replace($base, ['lines' => [$tiny, $tiny, $tiny, $zero]]), ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $this->getJson($url.'/'.$id.'?company_id='.$this->companyA)->assertOk()->assertJsonPath('record.net', '100.03')->assertJsonPath('record.vat', '0.01')->assertJsonPath('record.total', '100.04');
        $profile = 'http://'.$this->hostA.'/api/v1/sales/tax-profile';
        $this->putJson($profile, $this->profile(['company_id' => $this->companyA, 'revision' => 1, 'vat_effective_from' => '2026-11-01']))->assertOk();
        $this->postJson($url, $base, ['Idempotency-Key' => $this->key()])->assertUnprocessable()->assertJsonValidationErrors('issue_date');
        $this->putJson($profile, $this->profile(['company_id' => $this->companyA, 'revision' => 2, 'vat_registered' => false]))->assertOk();
        $this->postJson($url, $base, ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        $this->postJson($url, array_replace($base, ['lines' => [array_replace($line, ['tax_code' => 'unregistered'])]]), ['Idempotency-Key' => $this->key()])->assertCreated();
    }

    public function test_cancel_expiry_profile_revision_and_stale_edits(): void
    {
        $id = $this->create();
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations/'.$id;
        $this->requestAs($this->owner, $this->a)->putJson($url, $this->quote(['company_id' => $this->companyA, 'revision' => 2]))->assertConflict();
        $this->postJson($url.'/transition', ['company_id' => $this->companyA, 'revision' => 1, 'action' => 'cancel'])->assertUnprocessable();
        $this->postJson($url.'/transition', ['company_id' => $this->companyA, 'revision' => 1, 'action' => 'issue'])->assertOk();
        $this->execute($this->a, fn () => app(TaxProfile::class)->save($this->companyA, $this->profile(['revision' => 1])));
        $this->postJson($url.'/convert', ['company_id' => $this->companyA, 'revision' => 2], ['Idempotency-Key' => $this->key()])->assertConflict();
        $this->postJson($url.'/transition', ['company_id' => $this->companyA, 'revision' => 2, 'action' => 'cancel', 'reason' => 'Tax policy revised'])->assertOk();
        $this->postJson($url.'/transition', ['company_id' => $this->companyA, 'revision' => 3, 'action' => 'issue'])->assertConflict();
        $expired = $this->execute($this->a, fn () => app(SalesService::class)->save($this->companyA, $this->quote(['issue_date' => '2024-01-01', 'valid_until' => '2024-01-02']), key: $this->key()));
        $this->postJson('http://'.$this->hostA.'/api/v1/sales/quotations/'.$expired.'/transition', ['company_id' => $this->companyA, 'revision' => 1, 'action' => 'issue'])->assertConflict();
    }

    public function test_company_tenant_read_only_roles_mfa_and_activation_boundaries(): void
    {
        $id = $this->create();
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations';
        DB::connection('pgsql')->table('memberships')->insert(['tenant_id' => $this->a, 'user_id' => $this->reader->id, 'role' => 'member', 'active' => true, 'company_ids' => json_encode([$this->companyA]), 'branch_ids' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $this->requestAs($this->reader, $this->a)->getJson($url.'?company_id='.$this->companyA)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($url, $this->quote(['company_id' => $this->companyA]), ['Idempotency-Key' => $this->key()])->assertForbidden();
        $other = $this->company($this->a, 'Other company');
        $this->requestAs($this->reader, $this->a)->getJson($url.'/'.$id.'?company_id='.$other)->assertForbidden();
        $this->requestAs($this->owner, $this->a)->getJson($url.'/'.$id.'?company_id='.$other)->assertNotFound();
        $this->getJson($url.'/'.Str::uuid().'?company_id='.$this->companyA)->assertNotFound();
        $this->owner->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->requestAs($this->owner, $this->a)->getJson($url.'?company_id='.$this->companyA)->assertForbidden();
        $this->owner->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->execute($this->a, fn () => app(ModuleAccess::class)->setEnabled('Sales', false));
        $this->requestAs($this->owner, $this->a)->getJson($url.'?company_id='.$this->companyA)->assertForbidden();
        $this->execute($this->a, fn () => app(ModuleAccess::class)->setEnabled('Sales', true));
        DB::connection('provisioner')->table('tenant_module_entitlements')->where('tenant_id', $this->a)->where('module', 'Sales')->update(['allowed' => false]);
        $this->getJson($url.'?company_id='.$this->companyA)->assertForbidden();
    }

    public function test_pdf_is_authenticated_escaped_and_snapshot_based(): void
    {
        $id = $this->execute($this->a, fn () => app(SalesService::class)->save($this->companyA, $this->quote(['terms' => '<script>alert(1)</script> & synthetic terms']), key: $this->key()));
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations/'.$id.'/pdf?company_id='.$this->companyA;
        $response = $this->requestAs($this->owner, $this->a)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $record = $this->execute($this->a, fn () => app(SalesService::class)->detail($this->companyA, $id)['record']);
        $html = view('sales.document', ['record' => $record, 'order' => false])->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Not a tax invoice.', $html);
        $this->assertStringContainsString('DRAFT', $html);
    }

    public function test_failed_order_creation_rolls_back_and_history_is_append_only(): void
    {
        $id = $this->create();
        $this->execute($this->a, function () use ($id) {
            app(SalesService::class)->transition($this->companyA, $id, ['action' => 'issue', 'revision' => 1]);
            $before = DB::connection('tenant')->table('event_outbox')->count();
            $admin = config('database.connections.provisioner');
            $admin['database'] = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->value('database_name');
            config(['database.connections.sales_fault' => $admin]);
            DB::connection('sales_fault')->unprepared("CREATE FUNCTION fail_sales_test() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'synthetic failure'; END; \$\$; CREATE TRIGGER sales_fault BEFORE INSERT ON sales_orders FOR EACH ROW EXECUTE FUNCTION fail_sales_test();");
            try {
                app(SalesService::class)->convert($this->companyA, $id, ['revision' => 2], $this->key());
                $this->fail('Expected failure');
            } catch (QueryException) {
            }
            $this->assertSame('issued', DB::connection('tenant')->table('sales_quotes')->where('id', $id)->value('status'));
            $this->assertSame(0, DB::connection('tenant')->table('sales_orders')->count());
            $this->assertSame($before, DB::connection('tenant')->table('event_outbox')->count());
            $this->assertSame(0, DB::connection('tenant')->table('document_counters')->where('series', 'SO')->count());
            DB::connection('sales_fault')->unprepared('DROP TRIGGER sales_fault ON sales_orders; DROP FUNCTION fail_sales_test();');
            DB::purge('sales_fault');
            $order = app(SalesService::class)->convert($this->companyA, $id, ['revision' => 2], $this->key());
            foreach (['sales_orders', 'sales_history'] as $table) {
                try {
                    DB::connection('tenant')->table($table)->delete();
                    $this->fail('Expected immutable rows');
                } catch (QueryException) {
                    $this->assertTrue(true);
                }
            }
            $this->assertSame('SO-000000001', app(Orders::class)->find($this->companyA, $order)->number);
            app(Outbox::class)->dispatch('sales-test');
        });
        $this->artisan('queue:work', ['connection' => 'redis', '--queue' => 'sales-test', '--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();
        $this->execute($this->a, function () {
            $this->assertSame(0, DB::connection('tenant')->table('event_outbox')->where('state', '!=', 'delivered')->count());
        });
    }

    public function test_concurrent_matching_conversions_preserve_one_order_and_counter(): void
    {
        $id = $this->create();
        $this->execute($this->a, fn () => app(SalesService::class)->transition($this->companyA, $id, ['action' => 'issue', 'revision' => 1]));
        $config = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'infovish_test', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, 'tests/Support/confirm-order.php'], base_path(), $env, json_encode(['tenant' => $this->a, 'actor' => $this->owner->id, 'company' => $this->companyA, 'quote' => $id, 'key' => $this->key()]), 30);
            $process->start();
            $processes[] = $process;
        }
        $ids = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Concurrent Sales conversion failed.');
            $ids[] = trim($process->getOutput());
        }
        $this->assertCount(1, array_unique($ids), json_encode($ids));
        $this->execute($this->a, function () {
            $this->assertSame(1, DB::connection('tenant')->table('sales_orders')->count());
            $this->assertSame(2, DB::connection('tenant')->table('document_counters')->where('series', 'SO')->value('next_value'));
            $this->assertSame(1, DB::connection('tenant')->table('event_outbox')->where('event_type', 'sales.order.confirmed')->count());
        });
    }

    public function test_cross_company_customer_variant_and_cross_tenant_matching_ids_are_isolated(): void
    {
        $other = $this->company($this->a);
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations';
        $this->requestAs($this->owner, $this->a)->postJson($url, $this->quote(['company_id' => $other]), ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        $this->execute($this->a, fn () => app(TaxProfile::class)->save($other, $this->profile()));
        $this->postJson($url, $this->quote(['company_id' => $other]), ['Idempotency-Key' => $this->key()])->assertNotFound();
        $customer = $this->execute($this->a, fn () => app(Contacts::class)->createCustomer($other, new NewCustomer('Other customer', null, null), $this->key())->id);
        $this->postJson($url, $this->quote(['company_id' => $other, 'customer_id' => $customer]), ['Idempotency-Key' => $this->key()])->assertNotFound();
        $id = $this->create();
        $companyB = $this->company($this->b);
        $this->execute($this->b, function () use ($id) {
            foreach (['Parties', 'Catalog', 'CRM', 'Sales'] as $module) {
                app(ModuleAccess::class)->setEnabled($module, true);
            }
            $this->assertSame(0, DB::connection('tenant')->table('sales_quotes')->where('id', $id)->count());
        });
        $this->requestAs($this->owner, $this->b)->getJson('http://'.$this->hostB.'/api/v1/sales/quotations/'.$id.'?company_id='.$companyB)->assertNotFound();
        $this->getJson('http://'.$this->hostB.'/api/v1/sales/quotations/'.$id.'/pdf?company_id='.$this->companyA)->assertForbidden();
    }

    public function test_catalog_changes_do_not_reprice_issued_quotes_or_order_snapshots(): void
    {
        $id = $this->create();
        $order = $this->execute($this->a, function () use ($id) {
            app(SalesService::class)->transition($this->companyA, $id, ['action' => 'issue', 'revision' => 1]);
            $variant = DB::connection('tenant')->table('catalog_variants')->where('id', $this->variant)->first();
            app(ProductService::class)->saveVariant($this->companyA, $variant->product_id, ['name' => 'Renamed service', 'sku' => $variant->sku, 'active' => true, 'attributes' => [], 'list_price' => '999.999999', 'revision' => 1], $variant->id);

            return app(SalesService::class)->convert($this->companyA, $id, ['revision' => 2], $this->key());
        });
        $this->execute($this->a, function () use ($order) {
            $r = app(SalesService::class)->detail($this->companyA, $order, true)['record'];
            $this->assertSame('Test service', $r->snapshot['pricing']['lines'][0]['name']);
            $this->assertSame('123.456789', $r->snapshot['pricing']['lines'][0]['unit_price']);
            $this->assertSame('327.78', $r->total);
        });
    }

    public function test_database_guard_rejects_frozen_quote_changes_even_for_operator(): void
    {
        $id = $this->create();
        $this->execute($this->a, fn () => app(SalesService::class)->transition($this->companyA, $id, ['action' => 'issue', 'revision' => 1]));
        $config = config('database.connections.provisioner');
        $config['database'] = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->value('database_name');
        config(['database.connections.sales_guard' => $config]);
        try {
            DB::connection('sales_guard')->table('sales_quotes')->where('id', $id)->update(['title' => 'Tampered', 'revision' => 3]);
            $this->fail('Expected database guard');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        DB::purge('sales_guard');
        $this->execute($this->a, function () use ($id) {
            $this->assertSame('Test proposal', DB::connection('tenant')->table('sales_quotes')->where('id', $id)->value('title'));
        });
    }

    public function test_sales_csrf_protection_and_decimal_overflow_fail_without_partial_documents(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations';
        $this->app->instance('env', 'local');
        $token = Str::random(40);
        $this->requestAs($this->owner, $this->a)->withSession(['_token' => $token])->postJson($url, $this->quote(['company_id' => $this->companyA]), ['Idempotency-Key' => $this->key()])->assertStatus(419);
        $bad = $this->quote(['company_id' => $this->companyA]);
        $bad['lines'][0]['quantity'] = '99999999999999.999999';
        $bad['lines'][0]['unit_price'] = '99999999999999.999999';
        $this->postJson($url, $bad, ['Idempotency-Key' => $this->key(), 'X-CSRF-TOKEN' => $token])->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->execute($this->a, function () {
            $this->assertSame(0, DB::connection('tenant')->table('sales_quotes')->count());
            $this->assertSame(0, DB::connection('tenant')->table('document_counters')->where('series', 'QTN')->count());
        });
        $this->postJson($url, $this->quote(['company_id' => $this->companyA]), ['Idempotency-Key' => $this->key(), 'X-CSRF-TOKEN' => $token])->assertCreated();
    }

    public function test_fifty_line_document_vat_rounds_once_and_discount_can_reduce_to_zero(): void
    {
        $line = ['variant_id' => $this->variant, 'quantity' => '1', 'unit_price' => '0.005', 'discount_percent' => '0', 'tax_code' => 'standard'];
        $data = $this->quote(['company_id' => $this->companyA, 'lines' => array_fill(0, 50, $line)]);
        $url = 'http://'.$this->hostA.'/api/v1/sales/quotations';
        $id = $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $this->getJson($url.'/'.$id.'?company_id='.$this->companyA)->assertOk()->assertJsonPath('record.net', '0.50')->assertJsonPath('record.vat', '0.09')->assertJsonPath('record.total', '0.59')->assertJsonCount(50, 'record.snapshot.pricing.lines');
        $data['lines'][] = $line;
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        $data['lines'] = [array_replace($line, ['unit_price' => '99999999999999.999999', 'discount_percent' => '100'])];
        $zero = $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $this->getJson($url.'/'.$zero.'?company_id='.$this->companyA)->assertOk()->assertJsonPath('record.total', '0.00');
    }

    public function test_schema_three_upgrade_preserves_master_and_crm_data_and_inactive_sales(): void
    {
        $company = $this->company($this->b);
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->b)->first();
        $config = config('database.connections.provisioner');
        $config['database'] = $row->database_name;
        config(['database.connections.sales_upgrade' => $config]);
        $db = DB::connection('sales_upgrade');
        $this->dropFinanceForUpgrade($db);
        $db->statement('ALTER TABLE sales_quotes DROP CONSTRAINT sales_quotes_order_fk');
        foreach (['sales_history', 'sales_orders', 'sales_quotes', 'sales_tax_profiles'] as $table) {
            $db->getSchemaBuilder()->drop($table);
        }
        $db->statement('DROP FUNCTION guard_sales_quote()');
        $db->table('migrations')->where('migration', '2026_10_30_100004_create_sales')->delete();
        $db->table('module_installations')->where('module', 'Sales')->delete();
        DB::purge('sales_upgrade');
        DB::connection('provisioner')->table('tenants')->where('id', $this->b)->update(['schema_version' => 3]);
        app(TenantSchema::class)->upgrade($row->slug);
        app(TenantSchema::class)->upgrade($row->slug);
        $this->execute($this->b, function () use ($company) {
            $this->assertSame($company, DB::connection('tenant')->table('companies')->value('id'));
            $this->assertSame(0, DB::connection('tenant')->table('sales_quotes')->count());
            $this->assertFalse(app(ModuleAccess::class)->allowed('Sales'));
            try {
                DB::connection('tenant')->statement('CREATE TABLE unsafe_sales_table(id int)');
                $this->fail('Expected runtime DDL denial');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        });
        $this->assertSame(TenantSchema::VERSION, DB::connection('pgsql')->table('tenants')->where('id', $this->b)->value('schema_version'));
    }
}
