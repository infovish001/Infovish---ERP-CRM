<?php

namespace Tests\Integration;

use App\Modules\CRM\Application\CrmAccess;
use App\Modules\CRM\Application\CrmQueries;
use App\Modules\CRM\Application\LeadService;
use App\Modules\CRM\Application\OpportunityService;
use App\Modules\CRM\Application\PipelineService;
use App\Modules\CRM\Domain\Contracts\Opportunities;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Parties\Domain\Contracts\NewCustomer;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\ModuleRegistry\TenantSchema;
use App\Platform\Tenancy\TenantExecution;
use App\Shared\Events\Outbox;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Support\TenantIntegrationTest;

class M04CrmTest extends TenantIntegrationTest
{
    protected string $companyA;

    protected string $pipeline;

    protected string $stage;

    protected string $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyA = $this->company($this->a);
        $this->execute($this->a, function () {
            app(ModuleAccess::class)->setEnabled('Parties', true);
            app(ModuleAccess::class)->setEnabled('CRM', true);
            $this->pipeline = app(PipelineService::class)->save($this->companyA, ['name' => 'Test pipeline', 'active' => true, 'stages' => [['name' => 'Qualified', 'probability' => 25], ['name' => 'Negotiation', 'probability' => 75]]], key: $this->key());
            $this->stage = DB::connection('tenant')->table('crm_stages')->where('pipeline_id', $this->pipeline)->orderBy('position')->value('id');
            $this->customer = app(Contacts::class)->createCustomer($this->companyA, new NewCustomer('Test customer', null, null), $this->key())->id;
        });
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function lead(array $extra = []): array
    {
        return $extra + ['title' => 'Test inquiry', 'contact_name' => 'Test prospect', 'email' => null, 'phone' => null, 'source' => 'Manual'];
    }

    private function opportunity(array $extra = []): array
    {
        return $extra + ['title' => 'Test opportunity', 'contact_id' => $this->customer, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage, 'expected_amount' => '10.123456', 'expected_close' => '2026-12-31'];
    }

    private function conversion(array $extra = []): array
    {
        return $extra + ['revision' => 2, 'customer_mode' => 'new', 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage, 'expected_amount' => '123.456789'];
    }

    private function qualified(): string
    {
        return $this->execute($this->a, function () {
            $id = app(LeadService::class)->save($this->companyA, $this->lead(), key: $this->key());
            app(LeadService::class)->transition($this->companyA, $id, ['action' => 'qualify', 'revision' => 1]);

            return $id;
        });
    }

    private function member(): void
    {
        DB::connection('pgsql')->table('memberships')->insert(['tenant_id' => $this->a, 'user_id' => $this->reader->id, 'role' => 'member', 'active' => true, 'company_ids' => json_encode([$this->companyA]), 'branch_ids' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_lead_api_lifecycle_duplicates_revision_and_closed_state(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/crm/leads';
        $data = $this->lead(['company_id' => $this->companyA, 'email' => 'PROSPECT@EXAMPLE.TEST']);
        $key = $this->key();
        $id = $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $key])->assertCreated()->json('id');
        $this->postJson($url, $data, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('id', $id);
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->getJson($url.'?company_id='.$this->companyA)->assertOk()->assertJsonPath('data.0.number', 'LED-000000001')->assertJsonPath('data.0.email', 'prospect@example.test');
        $this->putJson($url.'/'.$id, $data + ['revision' => 1])->assertOk();
        $this->putJson($url.'/'.$id, $data + ['revision' => 1])->assertConflict();
        $this->postJson($url.'/'.$id.'/transition', ['company_id' => $this->companyA, 'action' => 'qualify', 'revision' => 2])->assertOk();
        $this->postJson($url.'/'.$id.'/transition', ['company_id' => $this->companyA, 'action' => 'lost', 'revision' => 3])->assertUnprocessable();
        $this->postJson($url.'/'.$id.'/transition', ['company_id' => $this->companyA, 'action' => 'lost', 'revision' => 3, 'reason' => 'No longer needed'])->assertOk();
        $this->putJson($url.'/'.$id, $data + ['revision' => 4])->assertConflict();
        $this->postJson($url.'/'.$id.'/transition', ['company_id' => $this->companyA, 'action' => 'reopen', 'revision' => 4, 'reason' => 'Prospect returned'])->assertOk();
        $this->getJson($url.'/'.$id.'?company_id='.$this->companyA)->assertOk()->assertJsonPath('record.status', 'new')->assertJsonCount(5, 'history');
    }

    public function test_members_only_write_assigned_company_records_and_cannot_convert_or_reassign(): void
    {
        $this->member();
        $adminLead = $this->qualified();
        $url = 'http://'.$this->hostA.'/api/v1/crm/leads';
        $data = $this->lead(['company_id' => $this->companyA]);
        $memberLead = $this->requestAs($this->reader, $this->a)->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $this->getJson($url.'?company_id='.$this->companyA)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $memberLead);
        $this->getJson($url.'/'.$adminLead.'?company_id='.$this->companyA)->assertNotFound();
        $this->putJson($url.'/'.$memberLead, $data + ['revision' => 1, 'assigned_to' => $this->owner->id])->assertForbidden();
        $this->postJson($url.'/'.$memberLead.'/convert', $this->conversion(['company_id' => $this->companyA]), ['Idempotency-Key' => $this->key()])->assertForbidden();
        $this->postJson('http://'.$this->hostA.'/api/v1/crm/pipelines', ['company_id' => $this->companyA])->assertForbidden();
        $other = $this->company($this->a);
        $this->getJson($url.'?company_id='.$other)->assertForbidden();
        DB::connection('pgsql')->table('memberships')->where('user_id', $this->reader->id)->where('tenant_id', $this->a)->update(['active' => false]);
        $this->getJson($url.'?company_id='.$this->companyA)->assertForbidden();
    }

    public function test_atomic_conversion_new_customer_matching_retries_and_immutable_lead(): void
    {
        $id = $this->qualified();
        $url = 'http://'.$this->hostA.'/api/v1/crm/leads/'.$id.'/convert';
        $data = $this->conversion(['company_id' => $this->companyA]);
        $key = $this->key();
        $opp = $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $key])->assertOk()->json('id');
        $this->postJson($url, $data, ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('id', $opp);
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertOk()->assertJsonPath('id', $opp);
        $this->postJson($url, array_replace($data, ['expected_amount' => '999']), ['Idempotency-Key' => $this->key()])->assertConflict();
        $this->execute($this->a, function () use ($id, $opp) {
            $row = app(CrmAccess::class)->record('lead', $this->companyA, $id);
            $this->assertSame('converted', $row->status);
            $this->assertSame($opp, $row->opportunity_id);
            $dto = app(Opportunities::class)->find($this->companyA, $opp);
            $this->assertSame('123.456789', $dto->expectedAmount);
            $this->assertTrue(app(Contacts::class)->find($this->companyA, $dto->contactId)->customer);
            $this->assertSame(1, DB::connection('tenant')->table('crm_opportunities')->where('lead_id', $id)->count());
        });
        $this->putJson('http://'.$this->hostA.'/api/v1/crm/leads/'.$id, $this->lead(['company_id' => $this->companyA, 'revision' => 3]))->assertConflict();
    }

    public function test_existing_customer_requires_explicit_selection_and_scope(): void
    {
        $existing = $this->execute($this->a, fn () => app(Contacts::class)->createCustomer($this->companyA, new NewCustomer('Existing', 'same@example.test', null), $this->key())->id);
        $id = $this->execute($this->a, function () {
            $id = app(LeadService::class)->save($this->companyA, $this->lead(['email' => 'same@example.test']), key: $this->key());
            app(LeadService::class)->transition($this->companyA, $id, ['action' => 'qualify', 'revision' => 1]);

            return $id;
        });
        $url = 'http://'.$this->hostA.'/api/v1/crm/leads/'.$id.'/convert';
        $this->requestAs($this->owner, $this->a)->postJson($url, $this->conversion(['company_id' => $this->companyA]), ['Idempotency-Key' => $this->key()])->assertUnprocessable()->assertJsonValidationErrors('contact_id');
        $this->postJson($url, $this->conversion(['company_id' => $this->companyA, 'customer_mode' => 'existing', 'contact_id' => $existing]), ['Idempotency-Key' => $this->key()])->assertOk();
    }

    public function test_conversion_failure_after_customer_creation_rolls_back_every_owned_state(): void
    {
        $id = $this->qualified();
        $tenant = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $config = config('database.connections.provisioner');
        $config['database'] = $tenant->database_name;
        config(['database.connections.crm_failure' => $config]);
        DB::connection('crm_failure')->unprepared("CREATE FUNCTION fail_test_opportunity() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Injected opportunity failure'; END $$; CREATE TRIGGER fail_test_opportunity BEFORE INSERT ON crm_opportunities FOR EACH ROW EXECUTE FUNCTION fail_test_opportunity();");
        $this->execute($this->a, function () use ($id) {
            $before = DB::connection('tenant')->table('event_outbox')->count();
            try {
                app(LeadService::class)->convert($this->companyA, $id, $this->conversion(), $this->key());
                $this->fail('Injected failure did not fail.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
            $this->assertSame(1, DB::connection('tenant')->table('parties_contacts')->count());
            $this->assertSame(0, DB::connection('tenant')->table('crm_opportunities')->count());
            $this->assertSame('qualified', DB::connection('tenant')->table('crm_leads')->value('status'));
            $this->assertSame($before, DB::connection('tenant')->table('event_outbox')->count());
            $this->assertSame(2, DB::connection('tenant')->table('document_counters')->where('series', 'CON')->value('next_value'));
        });
        DB::purge('crm_failure');
    }

    public function test_opportunity_transitions_probabilities_history_and_exact_forecast(): void
    {
        $this->execute($this->a, function () {
            $service = app(OpportunityService::class);
            $id = $service->save($this->companyA, $this->opportunity(['expected_amount' => '99999999999999.123456']), key: $this->key());
            $service->save($this->companyA, $this->opportunity(['expected_amount' => null]), key: $this->key());
            $service->save($this->companyA, $this->opportunity(['expected_amount' => '0.000002']), key: $this->key());
            $forecast = app(CrmQueries::class)->forecast($this->companyA);
            $this->assertSame('24999999999999.780865', $forecast['weighted']);
            $this->assertSame('99999999999999.123458', $forecast['unweighted']);
            $this->assertSame(1, $forecast['unknown_count']);
            $second = DB::connection('tenant')->table('crm_stages')->where('pipeline_id', $this->pipeline)->where('position', 2)->value('id');
            $service->transition($this->companyA, $id, ['action' => 'move', 'stage_id' => $second, 'revision' => 1]);
            $service->transition($this->companyA, $id, ['action' => 'won', 'revision' => 2]);
            try {
                $service->save($this->companyA, $this->opportunity(['revision' => 3]), $id);
                $this->fail('Closed opportunity changed.');
            } catch (HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
            $service->transition($this->companyA, $id, ['action' => 'reopen', 'stage_id' => $this->stage, 'reason' => 'Correction reviewed', 'revision' => 3]);
            $this->assertSame(4, count(app(CrmQueries::class)->history('opportunity', $this->companyA, $id)));
            try {
                DB::connection('tenant')->table('crm_history')->delete();
                $this->fail('History erased.');
            } catch (QueryException $e) {
                $this->assertSame('42501', $e->errorInfo[0]);
            }
        });
    }

    public function test_activity_parent_scope_utc_due_time_and_terminal_retention(): void
    {
        $id = $this->qualified();
        $url = 'http://'.$this->hostA.'/api/v1/crm/lead/'.$id.'/activities';
        $data = ['company_id' => $this->companyA, 'subject' => 'Follow up', 'type' => 'call', 'note' => 'Synthetic note', 'due_at' => '2026-12-01T12:00:00+05:30'];
        $activity = $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertCreated()->json('id');
        $this->getJson('http://'.$this->hostA.'/api/v1/crm/leads/'.$id.'?company_id='.$this->companyA)->assertOk()->assertJsonCount(1, 'activities');
        $this->postJson('http://'.$this->hostA.'/api/v1/crm/activities/'.$activity.'/transition', ['company_id' => $this->companyA, 'status' => 'done', 'revision' => 1])->assertOk();
        $this->putJson($url.'/'.$activity, $data + ['revision' => 2])->assertConflict();
        $this->execute($this->a, function () use ($activity) {
            $row = DB::connection('tenant')->table('crm_activities')->where('id', $activity)->first();
            $this->assertSame('done', $row->status);
            $this->assertSame('2026-12-01 06:30:00', CarbonImmutable::parse($row->due_at)->utc()->format('Y-m-d H:i:s'));
        });
    }

    public function test_member_authored_crm_events_use_real_redis_and_remain_id_only(): void
    {
        $this->member();
        app(TenantExecution::class)->run($this->a, $this->reader->id, fn () => app(LeadService::class)->save($this->companyA, $this->lead(['email' => 'private@example.test']), key: $this->key()));
        $queue = 'crm-test-'.Str::uuid();
        $this->execute($this->a, fn () => app(Outbox::class)->dispatch($queue));
        $this->artisan('queue:work', ['connection' => 'redis', '--queue' => $queue, '--stop-when-empty' => true, '--tries' => 1, '--timeout' => 20])->assertSuccessful();
        $this->execute($this->a, function () {
            $row = DB::connection('tenant')->table('event_outbox')->where('event_type', 'crm.lead.created')->first();
            $this->assertSame('delivered', $row->state);
            $this->assertStringNotContainsString('private@example.test', json_encode($row));
        });
        $this->assertNull(config('database.connections.tenant'));
    }

    public function test_concurrent_conversion_creates_one_customer_and_opportunity(): void
    {
        $lead = $this->qualified();
        $config = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'infovish_test', 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $payload = ['tenant' => $this->a, 'actor' => $this->owner->id, 'company' => $this->companyA, 'lead' => $lead, 'payload' => $this->conversion(), 'key' => $this->key()];
            $process = new Process([PHP_BINARY, 'tests/Support/convert.php'], base_path(), $env, json_encode($payload), 30);
            $process->start();
            $processes[] = $process;
        }
        $ids = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Concurrent conversion failed.');
            $ids[] = trim($process->getOutput());
        }
        $this->assertCount(1, array_unique($ids), json_encode($ids));
        $this->execute($this->a, function () {
            $this->assertSame(1, DB::connection('tenant')->table('crm_opportunities')->count());
            $this->assertSame(2, DB::connection('tenant')->table('parties_contacts')->count());
        });
    }

    public function test_cross_company_stage_customer_and_activity_ids_fail_closed(): void
    {
        $other = $this->company($this->a);
        $customer = $this->execute($this->a, fn () => app(Contacts::class)->createCustomer($other, new NewCustomer('Other company', null, null), $this->key())->id);
        $url = 'http://'.$this->hostA.'/api/v1/crm/opportunities';
        $this->requestAs($this->owner, $this->a)->postJson($url, $this->opportunity(['company_id' => $this->companyA, 'contact_id' => $customer]), ['Idempotency-Key' => $this->key()])->assertNotFound();
        $this->postJson($url, $this->opportunity(['company_id' => $other]), ['Idempotency-Key' => $this->key()])->assertNotFound();
        $lead = $this->qualified();
        $this->postJson('http://'.$this->hostA.'/api/v1/crm/lead/'.$lead.'/activities', ['company_id' => $other, 'subject' => 'Wrong scope', 'type' => 'task', 'due_at' => '2026-12-01T12:00:00Z'], ['Idempotency-Key' => $this->key()])->assertNotFound();
        $this->postJson($url, $this->opportunity(['company_id' => $this->companyA, 'stage_id' => $this->key()]), ['Idempotency-Key' => $this->key()])->assertUnprocessable();
    }

    public function test_same_lead_ids_in_two_tenants_are_isolated_and_hints_are_ignored(): void
    {
        $id = $this->qualified();
        $companyB = $this->company($this->b);
        $this->execute($this->b, function () use ($id, $companyB) {
            app(ModuleAccess::class)->setEnabled('Parties', true);
            app(ModuleAccess::class)->setEnabled('CRM', true);
            DB::connection('tenant')->table('crm_leads')->insert(['id' => $id, 'company_id' => $companyB, 'number' => 'LED-000000001', 'title' => 'Tenant B only', 'contact_name' => 'B prospect', 'assigned_to' => $this->owner->id, 'status' => 'new', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->requestAs($this->owner, $this->a)->putJson('http://'.$this->hostA.'/api/v1/crm/leads/'.$id, $this->lead(['company_id' => $this->companyA, 'title' => 'Tenant A only', 'revision' => 2, 'tenant_id' => $this->b]), ['X-Tenant-ID' => $this->b])->assertOk();
        $this->execute($this->b, fn () => $this->assertSame('Tenant B only', app(CrmAccess::class)->record('lead', $companyB, $id)->title));
        $this->assertNull(config('database.connections.tenant'));
    }

    public function test_auditor_is_company_scoped_and_member_forecasts_are_assignment_scoped(): void
    {
        $this->member();
        $this->execute($this->a, fn () => app(OpportunityService::class)->save($this->companyA, $this->opportunity(['expected_amount' => '100']), key: $this->key()));
        app(TenantExecution::class)->run($this->a, $this->reader->id, fn () => app(OpportunityService::class)->save($this->companyA, $this->opportunity(['expected_amount' => '40']), key: $this->key()));
        $url = 'http://'.$this->hostA.'/api/v1/crm';
        $this->requestAs($this->reader, $this->a)->getJson($url.'/forecast?company_id='.$this->companyA)->assertOk()->assertJsonPath('unweighted', '40.000000')->assertJsonPath('weighted', '10.000000');
        DB::connection('pgsql')->table('memberships')->where('tenant_id', $this->a)->where('user_id', $this->reader->id)->update(['role' => 'auditor']);
        $this->getJson($url.'/opportunities?company_id='.$this->companyA)->assertOk()->assertJsonCount(2, 'data');
        $this->postJson($url.'/leads', $this->lead(['company_id' => $this->companyA]), ['Idempotency-Key' => $this->key()])->assertForbidden();
    }

    public function test_pipeline_validation_and_module_entitlement_revocation(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/crm';
        $this->requestAs($this->owner, $this->a)->postJson($url.'/pipelines', ['company_id' => $this->companyA, 'name' => 'Invalid', 'active' => true, 'stages' => [['name' => 'Stage', 'probability' => 101]]], ['Idempotency-Key' => $this->key()])->assertUnprocessable();
        $this->execute($this->a, fn () => app(ModuleAccess::class)->setEnabled('CRM', false));
        $this->getJson($url.'/leads?company_id='.$this->companyA)->assertForbidden();
        $this->execute($this->a, fn () => app(ModuleAccess::class)->setEnabled('CRM', true));
        DB::connection('provisioner')->table('tenant_module_entitlements')->where('tenant_id', $this->a)->where('module', 'CRM')->update(['allowed' => false]);
        $this->getJson($url.'/leads?company_id='.$this->companyA)->assertForbidden();
    }

    public function test_schema_two_upgrade_preserves_master_data_and_installs_inactive_crm(): void
    {
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->b)->first();
        $company = $this->company($this->b);
        $config = config('database.connections.provisioner');
        $config['database'] = $row->database_name;
        config(['database.connections.crm_upgrade' => $config]);
        $db = DB::connection('crm_upgrade');
        $this->dropFinanceForUpgrade($db);
        $db->statement('ALTER TABLE sales_quotes DROP CONSTRAINT sales_quotes_order_fk');
        foreach (['sales_history', 'sales_orders', 'sales_quotes', 'sales_tax_profiles', 'crm_history', 'crm_activities', 'crm_opportunities', 'crm_leads', 'crm_stages', 'crm_pipelines'] as $table) {
            $db->getSchemaBuilder()->drop($table);
        }
        $db->statement('DROP FUNCTION guard_sales_quote()');
        $db->table('migrations')->where('migration', '2026_10_30_100004_create_sales')->delete();
        $db->table('migrations')->where('migration', '2026_10_30_100003_create_crm')->delete();
        $db->table('module_installations')->where('module', 'CRM')->delete();
        DB::purge('crm_upgrade');
        DB::connection('provisioner')->table('tenants')->where('id', $this->b)->update(['schema_version' => 2]);
        app(TenantSchema::class)->upgrade($row->slug);
        $this->execute($this->b, function () use ($company) {
            $this->assertSame($company, DB::connection('tenant')->table('companies')->value('id'));
            $this->assertFalse(app(ModuleAccess::class)->allowed('CRM'));
            $this->assertSame(0, DB::connection('tenant')->table('crm_leads')->count());
        });
    }

    public function test_crm_csrf_and_privileged_mfa_are_enforced(): void
    {
        $url = 'http://'.$this->hostA.'/api/v1/crm/leads';
        $data = $this->lead(['company_id' => $this->companyA]);
        $this->owner->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->requestAs($this->owner, $this->a)->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertForbidden();
        $this->owner->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->app->instance('env', 'local');
        $token = Str::random(40);
        $this->requestAs($this->owner, $this->a)->withSession(['_token' => $token])->postJson($url, $data, ['Idempotency-Key' => $this->key()])->assertStatus(419);
        $this->postJson($url, $data, ['Idempotency-Key' => $this->key(), 'X-CSRF-TOKEN' => $token])->assertCreated();
    }

    public function test_mutation_rechecks_membership_after_context_and_lock_acquisition(): void
    {
        $this->member();
        app(TenantExecution::class)->run($this->a, $this->reader->id, function () {
            // Simulate revocation after HTTP context resolution but before the write lock is acquired.
            DB::connection('pgsql')->table('memberships')->where('tenant_id', $this->a)->where('user_id', $this->reader->id)->update(['company_ids' => '[]']);
            try {
                app(LeadService::class)->save($this->companyA, $this->lead(), key: $this->key());
                $this->fail('Stale context retained a revoked company grant.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
            $this->assertSame(0, DB::connection('tenant')->table('crm_leads')->count());
        });
    }
}
