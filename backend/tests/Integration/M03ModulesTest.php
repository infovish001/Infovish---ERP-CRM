<?php

namespace Tests\Integration;

use App\Modules\Catalog\Domain\Contracts\Products;
use App\Modules\Parties\Application\ContactService;
use App\Modules\Parties\Domain\Contracts\ContactData;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Platform\Identity\User;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\ModuleRegistry\ModuleRegistry;
use App\Platform\ModuleRegistry\TenantSchema;
use App\Platform\Tenancy\TenantExecution;
use App\Shared\Events\DeliverEvent;
use App\Shared\Events\Outbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Support\TenantIntegrationTest;

class M03ModulesTest extends TenantIntegrationTest
{
    protected string $companyA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyA = $this->company($this->a);
        $this->execute($this->a, function () {
            app(ModuleAccess::class)->setEnabled('Parties', true);
            app(ModuleAccess::class)->setEnabled('Catalog', true);
        });
    }

    private function contact(array $extra = []): array
    {
        return $extra + ['name' => 'Test customer', 'email' => null, 'phone' => null, 'customer' => true, 'supplier' => false, 'active' => true];
    }

    private function product(array $extra = []): array
    {
        return $extra + ['name' => 'Test service', 'type' => 'service', 'unit' => 'hour', 'sku' => 'SKU-'.Str::upper(Str::random(8)), 'list_price' => '12345678901234.123456', 'active' => true];
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    public function test_host_cookie_is_consistent_when_fortify_resolves_session_early(): void
    {
        $session = app('session')->driver();
        $session->setName('early_session_cookie');
        $this->get('http://'.$this->hostA.'/login')->assertOk()->assertCookie('infovish_'.substr(hash('sha256', $this->hostA), 0, 16))->assertCookieMissing('early_session_cookie');
        $this->assertSame('early_session_cookie', $session->getName());
    }

    public function test_activation_entitlement_and_runtime_privileges_are_enforced(): void
    {
        $this->execute($this->a, fn () => app(ModuleAccess::class)->setEnabled('Parties', false));
        $url = 'http://'.$this->hostA.'/api/v1/contacts?company_id='.$this->companyA;
        $this->requestAs($this->owner, $this->a)->getJson($url)->assertForbidden();
        $this->putJson('http://'.$this->hostA.'/api/v1/modules/Parties', ['enabled' => true])->assertOk();
        DB::connection('provisioner')->table('tenant_module_entitlements')->where('tenant_id', $this->a)->where('module', 'Parties')->update(['allowed' => false]);
        $this->getJson($url)->assertForbidden();
        $this->putJson('http://'.$this->hostA.'/api/v1/modules/Parties', ['enabled' => true])->assertForbidden();
        $this->execute($this->a, function () {
            try {
                DB::connection('tenant')->table('module_installations')->where('module', 'Parties')->update(['version' => '9.0.0']);
                $this->fail('Runtime changed module schema version.');
            } catch (QueryException $error) {
                $this->assertSame('42501', $error->errorInfo[0]);
            }
        });
        try {
            DB::connection('pgsql')->table('tenant_module_entitlements')->update(['allowed' => true]);
            $this->fail('Runtime changed entitlement.');
        } catch (QueryException $error) {
            $this->assertSame('42501', $error->errorInfo[0]);
        }
    }

    public function test_contacts_are_idempotent_company_scoped_and_revision_checked(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/contacts';
        $data = $this->contact(['company_id' => $this->companyA, 'email' => 'OWNER@EXAMPLE.TEST']);
        $key = $this->key();
        $id = $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $key])->assertCreated()->json('id');
        $this->postJson($url, $data, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('id', $id);
        $this->postJson($url, array_replace($data, ['name' => 'Different']), ['Idempotency-Key' => $key])->assertConflict();
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->getJson($url.'?company_id='.$this->companyA.'&q=OWNER')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'owner@example.test')->assertJsonPath('data.0.number', 'CON-000000001');
        $this->putJson($url.'/'.$id, $this->contact(['company_id' => $this->companyA, 'name' => 'Edited', 'revision' => 1]))->assertOk();
        $this->putJson($url.'/'.$id, $this->contact(['company_id' => $this->companyA, 'revision' => 1]))->assertConflict();
        $this->putJson($url.'/'.$id, $this->contact(['company_id' => $this->companyA, 'revision' => 2, 'active' => false]))->assertOk();
        $this->execute($this->a, function () use ($id) {
            $this->assertSame(3, DB::connection('tenant')->table('parties_contacts')->where('id', $id)->value('revision'));
            $this->assertSame(3, DB::connection('tenant')->table('event_outbox')->count());
            $this->assertSame(1, DB::connection('tenant')->table('idempotency_records')->count());
        });
    }

    public function test_company_grants_and_read_only_roles_protect_api_and_contracts(): void
    {
        $other = $this->company($this->a);
        $id = $this->execute($this->a, fn () => app(ContactService::class)->save($this->companyA, $this->contact(), key: $this->key()));
        DB::connection('pgsql')->table('memberships')->insert(['tenant_id' => $this->a, 'user_id' => $this->reader->id, 'role' => 'member', 'active' => true, 'company_ids' => json_encode([$this->companyA]), 'branch_ids' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $url = 'http://'.$this->hostA.'/api/v1/contacts';
        $this->requestAs($this->reader, $this->a)->getJson($url.'?company_id='.$this->companyA)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($url.'?company_id='.$other)->assertForbidden();
        $this->postJson($url, $this->contact(['company_id' => $this->companyA]), ['Idempotency-Key' => $this->key()])->assertForbidden();
        $this->putJson('http://'.$this->hostA.'/api/v1/modules/Parties', ['enabled' => false])->assertForbidden();
        $this->getJson('http://'.$this->hostA.'/api/v1/events')->assertForbidden();
        app(TenantExecution::class)->run($this->a, $this->reader->id, function () use ($id) {
            $dto = app(Contacts::class)->find($this->companyA, $id);
            $this->assertSame($id, $dto->id);
            $this->assertInstanceOf(ContactData::class, $dto);
        });
    }

    public function test_catalog_decimal_variants_contracts_and_sku_uniqueness(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/products';
        $data = $this->product(['company_id' => $this->companyA, 'sku' => 'CONSULT']);
        $id = $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $listing = $this->getJson($url.'?company_id='.$this->companyA.'&q=CONSULT')->assertOk()->assertJsonPath('data.0.variants.0.list_price', '12345678901234.123456');
        $variant = $listing->json('data.0.variants.0.id');
        $this->execute($this->a, function () use ($variant) {
            $dto = app(Products::class)->find($this->companyA, $variant);
            $this->assertSame('12345678901234.123456', $dto->listPrice);
            $this->assertSame('LKR', $dto->currency);
        });
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertUnprocessable()->assertJsonValidationErrors('sku');
        foreach (['1e3', '-1', '0.0000001', 12.4] as $price) {
            $this->postJson($url, $this->product(['company_id' => $this->companyA, 'list_price' => $price]), ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        }
        $variantData = ['company_id' => $this->companyA, 'sku' => 'CONSULT-PREMIUM', 'name' => 'Premium', 'attributes' => ['Tier' => 'Premium'], 'list_price' => '300.125', 'active' => true];
        $variantId = $this->postJson($url.'/'.$id.'/variants', $variantData, ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $this->putJson($url.'/'.$id.'/variants/'.$variantId, $variantData + ['revision' => 1])->assertOk();
        $this->getJson($url.'?company_id='.$this->companyA.'&q=PREMIUM')->assertOk()->assertJsonCount(2, 'data.0.variants')->assertJsonPath('data.0.variants.1.list_price', '300.125000');
        $this->putJson($url.'/'.$id, ['company_id' => $this->companyA, 'name' => 'Retired', 'active' => false, 'revision' => 1])->assertOk();
        $this->postJson($url.'/'.$id.'/variants', $variantData, ['Idempotency-Key' => $this->key()])->assertNotFound();
    }

    public function test_duplicate_ids_and_payload_tenant_hints_cannot_cross_tenants(): void
    {
        $id = $this->execute($this->a, fn () => app(ContactService::class)->save($this->companyA, $this->contact(), key: $this->key()));
        $companyB = $this->company($this->b);
        $this->execute($this->b, function () use ($id, $companyB) {
            app(ModuleAccess::class)->setEnabled('Parties', true);
            DB::connection('tenant')->table('parties_contacts')->insert(['id' => $id, 'company_id' => $companyB, 'number' => 'CON-000000001', 'name' => 'Only B', 'customer' => false, 'supplier' => true, 'active' => true, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->requestAs($this->owner, $this->a)->putJson('http://'.$this->hostA.'/api/v1/contacts/'.$id, $this->contact(['company_id' => $this->companyA, 'name' => 'Only A', 'revision' => 1, 'tenant_id' => $this->b]), ['X-Tenant-ID' => $this->b])->assertOk();
        $this->execute($this->b, fn () => $this->assertSame('Only B', app(Contacts::class)->find($companyB, $id)->name));
        $this->getJson('http://'.$this->hostA.'/api/v1/contacts?company_id='.$companyB)->assertForbidden();
        $this->assertNull(config('database.connections.tenant'));
    }

    public function test_transaction_rollback_preserves_numbering_idempotency_and_outbox(): void
    {
        $this->execute($this->a, function () {
            try {
                DB::connection('tenant')->transaction(function () {
                    app(ContactService::class)->save($this->companyA, $this->contact(), key: $this->key());
                    throw new \RuntimeException('Expected rollback');
                });
            } catch (\RuntimeException) {
            }
            foreach (['parties_contacts', 'event_outbox', 'idempotency_records', 'document_counters'] as $table) {
                $this->assertSame(0, DB::connection('tenant')->table($table)->count());
            }
            $id = app(ContactService::class)->save($this->companyA, $this->contact(), key: $this->key());
            $this->assertSame('CON-000000001', app(Contacts::class)->find($this->companyA, $id)->number);
        });
    }

    public function test_parallel_matching_retries_create_one_contact_and_unique_numbers(): void
    {
        $config = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'infovish_test', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $key = $this->key();
        $payload = ['tenant' => $this->a, 'actor' => $this->owner->id, 'company' => $this->companyA, 'key' => $key, 'payload' => $this->contact()];
        $processes = [];
        for ($i = 0; $i < 3; $i++) {
            $process = new Process([PHP_BINARY, 'tests/Support/mutate.php'], base_path(), $env, json_encode($payload), 30);
            $process->start();
            $processes[] = $process;
        }
        $ids = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Concurrent mutation failed');
            $ids[] = trim($process->getOutput());
        }
        $this->assertCount(1, array_unique($ids), json_encode($ids));
        $processes = [];
        for ($i = 0; $i < 3; $i++) {
            $payload['key'] = $this->key();
            $process = new Process([PHP_BINARY, 'tests/Support/mutate.php'], base_path(), $env, json_encode($payload), 30);
            $process->start();
            $processes[] = $process;
        }
        $ids = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Concurrent numbering failed');
            $ids[] = trim($process->getOutput());
        }
        $this->assertCount(3, array_unique($ids));
        $this->execute($this->a, function () {
            $this->assertSame(4, DB::connection('tenant')->table('parties_contacts')->count());
            $this->assertSame(5, DB::connection('tenant')->table('document_counters')->value('next_value'));
            $this->assertSame(['CON-000000001', 'CON-000000002', 'CON-000000003', 'CON-000000004'], DB::connection('tenant')->table('parties_contacts')->orderBy('number')->pluck('number')->all());
        });
    }

    public function test_database_delivery_failure_uses_savepoint_and_keeps_durable_retry(): void
    {
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $config = config('database.connections.provisioner');
        $config['database'] = $row->database_name;
        config(['database.connections.delivery_fixture' => $config]);
        DB::connection('delivery_fixture')->unprepared("CREATE FUNCTION fail_test_receipt() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Injected receipt failure'; END $$; CREATE TRIGGER fail_test_receipt BEFORE INSERT ON event_receipts FOR EACH ROW EXECUTE FUNCTION fail_test_receipt();");
        try {
            $this->execute($this->a, function () {
                app(ContactService::class)->save($this->companyA, $this->contact(), key: $this->key());
                $event = DB::connection('tenant')->table('event_outbox')->value('id');
                app(Outbox::class)->consume($event);
                $this->assertSame('pending', DB::connection('tenant')->table('event_outbox')->value('state'));
                $this->assertSame(1, DB::connection('tenant')->table('event_outbox')->value('attempts'));
                $this->assertSame('delivery_failed', DB::connection('tenant')->table('event_outbox')->value('error_code'));
                $this->assertSame(1, DB::connection('tenant')->table('parties_contacts')->count());
            });
        } finally {
            DB::purge('delivery_fixture');
        }
    }

    public function test_real_redis_outbox_delivery_is_deduplicated_and_redacts_payload(): void
    {
        $id = $this->execute($this->a, fn () => app(ContactService::class)->save($this->companyA, $this->contact(['email' => 'private@example.test']), key: $this->key()));
        $queue = 'm03-'.Str::uuid();
        $event = $this->execute($this->a, function () use ($queue) {
            $event = DB::connection('tenant')->table('event_outbox')->first();
            $this->assertSame(1, app(Outbox::class)->dispatch($queue));

            return $event->id;
        });
        DeliverEvent::dispatch($this->a, $this->owner->id, $event)->onConnection('redis')->onQueue($queue);
        $this->artisan('queue:work', ['connection' => 'redis', '--queue' => $queue, '--stop-when-empty' => true, '--tries' => 1, '--timeout' => 20])->assertSuccessful();
        $this->execute($this->a, function () use ($event, $id) {
            $row = DB::connection('tenant')->table('event_outbox')->where('id', $event)->first();
            $this->assertSame('delivered', $row->state);
            $this->assertSame($id, $row->aggregate_id);
            $this->assertSame(1, DB::connection('tenant')->table('event_receipts')->count());
            $this->assertStringNotContainsString('private@example.test', json_encode($row));
        });
        $this->assertNull(config('database.connections.tenant'));
    }

    public function test_revoked_module_dead_letters_and_authorized_replay_are_durable(): void
    {
        $this->execute($this->a, function () {
            app(ContactService::class)->save($this->companyA, $this->contact(), key: $this->key());
            $event = DB::connection('tenant')->table('event_outbox')->value('id');
            app(ModuleAccess::class)->setEnabled('Parties', false);
            for ($i = 0; $i < 5; $i++) {
                app(Outbox::class)->consume($event);
            }
            $this->assertSame('dead', DB::connection('tenant')->table('event_outbox')->value('state'));
            $this->assertSame(0, DB::connection('tenant')->table('event_receipts')->count());
            app(ModuleAccess::class)->setEnabled('Parties', true);
            app(Outbox::class)->replay($event);
            app(Outbox::class)->consume($event);
            $this->assertSame('delivered', DB::connection('tenant')->table('event_outbox')->value('state'));
            $this->assertSame(1, DB::connection('tenant')->table('audit_events')->where('action', 'event.replayed')->count());
        });
    }

    public function test_existing_schema_upgrade_preserves_data_and_runtime_ddl_denial(): void
    {
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $connection = config('database.connections.provisioner');
        $connection['database'] = $row->database_name;
        config(['database.connections.upgrade_fixture' => $connection]);
        $db = DB::connection('upgrade_fixture');
        $this->dropFinanceForUpgrade($db);
        $db->statement('ALTER TABLE sales_quotes DROP CONSTRAINT sales_quotes_order_fk');
        foreach (['sales_history', 'sales_orders', 'sales_quotes', 'sales_tax_profiles', 'crm_history', 'crm_activities', 'crm_opportunities', 'crm_leads', 'crm_stages', 'crm_pipelines', 'catalog_variants', 'catalog_products', 'parties_contacts', 'event_receipts', 'event_outbox', 'idempotency_records', 'document_counters', 'module_installations'] as $table) {
            $db->getSchemaBuilder()->drop($table);
        }
        $db->statement('DROP FUNCTION guard_sales_quote()');
        $db->table('migrations')->where('migration', '2026_10_30_100004_create_sales')->delete();
        $db->table('migrations')->whereIn('migration', ['2026_10_30_100003_create_crm', '2026_10_30_000002_create_shared_module_services', '2026_10_30_100001_create_parties', '2026_10_30_100002_create_catalog'])->delete();
        DB::purge('upgrade_fixture');
        DB::connection('provisioner')->table('tenants')->where('id', $this->a)->update(['schema_version' => 1]);
        app(TenantSchema::class)->upgrade($row->slug);
        app(TenantSchema::class)->upgrade($row->slug);
        $this->assertSame(TenantSchema::VERSION, DB::connection('pgsql')->table('tenants')->where('id', $this->a)->value('schema_version'));
        $this->execute($this->a, function () {
            $this->assertSame($this->companyA, DB::connection('tenant')->table('companies')->value('id'));
            $this->assertFalse(app(ModuleAccess::class)->allowed('Parties'));
            app(ModuleAccess::class)->setEnabled('Parties', true);
            $this->assertTrue(app(ModuleAccess::class)->allowed('Parties'));
        });
    }

    public function test_identity_mismatch_upgrade_preserves_data_and_remains_suspended(): void
    {
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $connection = config('database.connections.provisioner');
        $connection['database'] = $row->database_name;
        config(['database.connections.upgrade_fixture' => $connection]);
        DB::connection('upgrade_fixture')->table('tenant_identity')->update(['id' => $this->b]);
        DB::purge('upgrade_fixture');
        try {
            app(TenantSchema::class)->upgrade($row->slug);
            $this->fail('Mismatched database upgraded.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame('suspended', DB::connection('pgsql')->table('tenants')->where('id', $this->a)->value('status'));
        $this->assertSame($this->companyA, DB::connection('upgrade_fixture')->table('companies')->value('id'));
        DB::purge('upgrade_fixture');
    }

    public function test_dependency_activation_and_disable_order_are_enforced(): void
    {
        $root = sys_get_temp_dir().'/infovish-lifecycle-'.bin2hex(random_bytes(8));
        mkdir($root.'/Parties', 0777, true);
        mkdir($root.'/Catalog', 0777, true);
        $base = ['version' => '1.0.0', 'framework_major' => 1, 'dependencies' => [], 'contracts' => [], 'migrations' => 'Infrastructure/Migrations'];
        file_put_contents($root.'/Parties/module.json', json_encode(['name' => 'Parties'] + $base));
        file_put_contents($root.'/Catalog/module.json', json_encode(['name' => 'Catalog', 'dependencies' => ['Parties' => '^1.0']] + $base));
        app()->instance(ModuleRegistry::class, new ModuleRegistry($root, ['Parties', 'Catalog']));
        try {
            $this->execute($this->a, function () {
                $access = app(ModuleAccess::class);
                $access->setEnabled('Catalog', false);
                $access->setEnabled('Parties', false);
                try {
                    $access->setEnabled('Catalog', true);
                    $this->fail('Activated without dependency.');
                } catch (HttpException $e) {
                    $this->assertSame(409, $e->getStatusCode());
                }
                $access->setEnabled('Parties', true);
                $access->setEnabled('Catalog', true);
                try {
                    $access->setEnabled('Parties', false);
                    $this->fail('Disabled active dependency.');
                } catch (HttpException $e) {
                    $this->assertSame(409, $e->getStatusCode());
                }
            });
        } finally {
            unlink($root.'/Parties/module.json');
            unlink($root.'/Catalog/module.json');
            rmdir($root.'/Parties');
            rmdir($root.'/Catalog');
            rmdir($root);
        }
    }

    public function test_original_actor_revocation_and_unknown_event_version_fail_closed(): void
    {
        $event = $this->execute($this->a, function () {
            app(ContactService::class)->save($this->companyA, $this->contact(), key: $this->key());

            return DB::connection('tenant')->table('event_outbox')->value('id');
        });
        // A second authorized dispatcher remains active while the original author is revoked.
        $dispatcher = User::create(['name' => 'Test dispatcher', 'email' => Str::uuid().'@example.test', 'password' => 'T!'.Str::random(30), 'two_factor_confirmed_at' => now()]);
        $this->users[] = $dispatcher->id;
        DB::connection('pgsql')->table('memberships')->insert(['tenant_id' => $this->a, 'user_id' => $dispatcher->id, 'role' => 'administrator', 'active' => true, 'company_ids' => '[]', 'branch_ids' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('pgsql')->table('memberships')->where('tenant_id', $this->a)->where('user_id', $this->owner->id)->update(['active' => false]);
        app(TenantExecution::class)->run($this->a, $dispatcher->id, function () use ($event) {
            app(Outbox::class)->consume($event);
            $this->assertSame('pending', DB::connection('tenant')->table('event_outbox')->value('state'));
            $this->assertSame('authorization_or_schema', DB::connection('tenant')->table('event_outbox')->value('error_code'));
            DB::connection('tenant')->table('event_outbox')->where('id', $event)->update(['event_version' => 99]);
            app(Outbox::class)->consume($event);
            $this->assertSame(0, DB::connection('tenant')->table('event_receipts')->count());
        });
        $this->assertNull(config('database.connections.tenant'));
    }

    public function test_create_requires_idempotency_and_csrf_and_invalid_company_is_denied(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/contacts';
        $data = $this->contact(['company_id' => $this->companyA]);
        $this->requestAs($this->owner, $this->a)->postJson($url, $data)->assertUnprocessable();
        $this->app->instance('env', 'local');
        $token = Str::random(40);
        $this->withSession(['_token' => $token])->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertStatus(419);
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key(), 'X-CSRF-TOKEN' => $token])->assertCreated();
    }
}
