<?php

namespace Tests\Integration;

use App\Platform\Identity\User;
use App\Platform\ModuleRegistry\TenantSchema;
use App\Platform\Organization\OrganizationService;
use App\Platform\Tenancy\TenantDomains;
use App\Platform\Tenancy\TenantProvisioner;
use App\Shared\Audit\Audit;
use App\Shared\Tenancy\TenantCache;
use App\Shared\Tenancy\TenantContext;
use App\Shared\Tenancy\TenantFiles;
use App\Shared\Tenancy\TenantJobMiddleware;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TenantContextProbeJob;
use Tests\Support\TenantIntegrationTest;

class M02TenancyTest extends TenantIntegrationTest
{
    public function test_provisioning_is_idempotent_and_runtime_cannot_create_databases(): void
    {
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $this->assertSame($this->a, app(TenantProvisioner::class)->provision($row->slug, $row->name, $this->hostA, $this->owner->id));
        $this->assertSame(TenantSchema::VERSION, $row->schema_version);
        $this->assertNotSame(Crypt::decryptString($row->database_password), $row->database_password);
        try {
            DB::connection('pgsql')->statement('CREATE DATABASE forbidden_runtime_database');
            $this->fail('Runtime created a database.');
        } catch (QueryException $error) {
            $this->assertSame('42501', $error->errorInfo[0]);
        }
    }

    public function test_cross_database_and_platform_connections_are_denied(): void
    {
        try {
            DB::connection('pgsql')->table('tenants')->where('id', $this->a)->update(['status' => 'suspended']);
            $this->fail('Runtime changed tenant routing/lifecycle metadata.');
        } catch (QueryException $error) {
            $this->assertSame('42501', $error->errorInfo[0]);
        }
        $a = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $b = DB::connection('pgsql')->table('tenants')->where('id', $this->b)->first();
        foreach ([$b->database_name, 'infovish_test'] as $database) {
            config(['database.connections.attack' => config('database.connections.pgsql') + []]);
            config(['database.connections.attack.database' => $database, 'database.connections.attack.username' => $a->database_user, 'database.connections.attack.password' => Crypt::decryptString($a->database_password)]);
            DB::purge('attack');
            try {
                DB::connection('attack')->select('SELECT 1');
                $this->fail('Tenant credential crossed database boundary.');
            } catch (QueryException $error) {
                $this->assertStringContainsString('permission denied', $error->getMessage());
            } finally {
                DB::purge('attack');
            }
        }
    }

    public function test_host_membership_session_and_payload_boundaries(): void
    {
        $id = $this->company($this->a, 'Only tenant A');
        $this->company($this->b, 'Only tenant B');
        $this->requestAs($this->owner, $this->a)->get('http://'.$this->hostA.'/workspace', ['X-Tenant-ID' => $this->b])->assertOk()->assertInertia(fn (Assert $page) => $page->where('tenant.id', $this->a)->where('companies.0.id', $id));
        $this->assertNull(config('database.connections.tenant'));
        $this->requestAs($this->owner, $this->a)->get('http://'.$this->hostB.'/workspace')->assertStatus(401);
        $this->requestAs($this->reader, $this->a)->get('http://'.$this->hostA.'/workspace')->assertForbidden();
        $this->get('http://unknown.localhost/workspace')->assertNotFound();
    }

    public function test_company_branch_permissions_and_last_owner_are_enforced(): void
    {
        $company = $this->company($this->a);
        $other = $this->company($this->a);
        $branch = $this->execute($this->a, fn () => app(OrganizationService::class)->save('branch', ['company_id' => $company, 'name' => 'Test branch', 'code' => 'MAIN']));
        DB::connection('pgsql')->table('memberships')->insert(['tenant_id' => $this->a, 'user_id' => $this->reader->id, 'role' => 'member', 'active' => true, 'company_ids' => json_encode([$company]), 'branch_ids' => json_encode([$branch]), 'created_at' => now(), 'updated_at' => now()]);
        $this->requestAs($this->reader, $this->a)->get('http://'.$this->hostA.'/workspace')->assertOk()->assertInertia(fn (Assert $page) => $page->has('companies', 1)->where('permissions.manageOrganization', false));
        $this->postJson('http://'.$this->hostA.'/workspace/scope', ['company_id' => $other])->assertForbidden();
        $this->postJson('http://'.$this->hostA.'/organization/company', ['name' => 'Unauthorized', 'code' => 'NO', 'base_currency' => 'LKR', 'timezone' => 'UTC'])->assertForbidden();
        $this->getJson('http://'.$this->hostA.'/workspace/audit')->assertForbidden();
        $this->requestAs($this->owner, $this->a)->postJson('http://'.$this->hostA.'/workspace/memberships', ['email' => $this->owner->email, 'role' => 'member', 'active' => true, 'company_ids' => [], 'branch_ids' => []])->assertStatus(422);
        $this->requestAs($this->owner, $this->a)->postJson('http://'.$this->hostA.'/organization/department', ['name' => 'Wrong branch', 'code' => 'WRONG', 'company_id' => $other, 'branch_id' => $branch])->assertForbidden();
    }

    public function test_lifecycle_revocation_and_mfa_gate(): void
    {
        $this->owner->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->requestAs($this->owner, $this->a)->get('http://'.$this->hostA.'/workspace')->assertRedirect('/account/security');
        $this->get('http://'.$this->hostA.'/account/security')->assertOk();
        $this->owner->forceFill(['two_factor_confirmed_at' => now()])->save();
        DB::connection('provisioner')->table('tenants')->where('id', $this->a)->update(['status' => 'suspended']);
        $this->get('http://'.$this->hostA.'/workspace')->assertNotFound();
        DB::connection('provisioner')->table('tenants')->where('id', $this->a)->update(['status' => 'active']);
        $this->owner->increment('session_version');
        $this->get('http://'.$this->hostA.'/workspace')->assertStatus(401);
    }

    public function test_cache_storage_and_job_exception_cleanup(): void
    {
        $this->execute($this->a, function () {
            app(TenantCache::class)->put('same-key', 'A');
            app(TenantFiles::class)->put('shared.txt', 'A');
        });
        $this->execute($this->b, function () {
            $this->assertNull(app(TenantCache::class)->get('same-key'));
            $this->assertNull(app(TenantFiles::class)->get('shared.txt'));
            app(TenantCache::class)->put('same-key', 'B');
        });
        $this->execute($this->a, fn () => $this->assertSame('A', app(TenantCache::class)->get('same-key')));
        try {
            (new TenantJobMiddleware($this->a, $this->owner->id))->handle(new \stdClass, function () {
                throw new \RuntimeException('Expected failure');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('Expected failure', $error->getMessage());
        }
        $this->assertNull(config('database.connections.tenant'));
        $this->expectException(\LogicException::class);
        app(TenantContext::class)->requireTenantId();
    }

    public function test_audit_is_immutable_and_hostname_cannot_be_reassigned(): void
    {
        $company = $this->company($this->a);
        $this->execute($this->a, function () use ($company) {
            $this->assertSame(1, DB::connection('tenant')->table('audit_events')->where('resource_id', $company)->count());
            try {
                DB::connection('tenant')->table('audit_events')->delete();
                $this->fail('Tenant deleted audit history.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
            try {
                app(Audit::class)->tenant('company.updated', $company, ['password' => 'not-allowed']);
                $this->fail('Audit accepted a secret field.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        });
        try {
            app(TenantDomains::class)->register($this->b, $this->hostA);
            $this->fail('Hostname was reassigned.');
        } catch (HttpException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame($this->a, DB::connection('pgsql')->table('tenant_domains')->where('host', $this->hostA)->value('tenant_id'));
    }

    public function test_login_mfa_challenge_and_logout_are_real_fortify_flows(): void
    {
        $this->postJson('http://'.$this->hostA.'/login', ['email' => $this->owner->email, 'password' => $this->password])->assertOk()->assertJson(['two_factor' => true]);
        $secret = decrypt($this->owner->two_factor_secret);
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->postJson('http://'.$this->hostA.'/two-factor-challenge', ['code' => $code])->assertNoContent();
        $this->get('http://'.$this->hostA.'/workspace')->assertOk();
        $this->post('http://'.$this->hostA.'/logout')->assertRedirect();
        $this->assertGuest();
        $this->assertGreaterThan(0, DB::connection('pgsql')->table('platform_audit_events')->where('actor_id', $this->owner->id)->where('action', 'identity.login')->count());
    }

    public function test_failed_login_is_generic_throttled_and_does_not_retain_password(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('http://'.$this->hostA.'/login', ['email' => $this->reader->email, 'password' => 'incorrect'])->assertStatus(422)->assertJsonValidationErrors('email')->assertDontSee('incorrect');
        }
        $this->postJson('http://'.$this->hostA.'/login', ['email' => $this->reader->email, 'password' => 'incorrect'])->assertStatus(429);
        $metadata = DB::connection('pgsql')->table('platform_audit_events')->where('tenant_id', $this->a)->pluck('metadata')->implode('');
        $this->assertStringNotContainsString('incorrect', $metadata);
        $this->assertStringNotContainsString($this->reader->email, $metadata);
    }

    public function test_confirmed_mfa_and_recovery_codes_use_fortify_enrollment(): void
    {
        $this->owner->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->requestAs($this->owner, $this->a);
        $this->postJson('http://'.$this->hostA.'/user/two-factor-authentication')->assertStatus(423);
        $this->postJson('http://'.$this->hostA.'/user/confirm-password', ['password' => $this->password])->assertSuccessful();
        $this->postJson('http://'.$this->hostA.'/user/two-factor-authentication')->assertSuccessful();
        $secretResponse = $this->getJson('http://'.$this->hostA.'/user/two-factor-secret-key')->assertSuccessful();
        $secret = $secretResponse->json('secretKey');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->postJson('http://'.$this->hostA.'/user/confirmed-two-factor-authentication', ['code' => $code])->assertSuccessful();
        $this->assertNotNull($this->owner->fresh()->two_factor_confirmed_at);
        $this->postJson('http://'.$this->hostA.'/user/two-factor-authentication', ['force' => true])->assertSuccessful();
        $this->assertSame($secret, decrypt($this->owner->fresh()->two_factor_secret));
        $codes = $this->getJson('http://'.$this->hostA.'/user/two-factor-recovery-codes')->assertSuccessful()->json();
        $this->post('http://'.$this->hostA.'/logout');
        $this->postJson('http://'.$this->hostA.'/login', ['email' => $this->owner->email, 'password' => $this->password])->assertOk();
        $this->postJson('http://'.$this->hostA.'/two-factor-challenge', ['recovery_code' => $codes[0]])->assertNoContent();
        $this->assertNotContains($codes[0], $this->owner->fresh()->recoveryCodes());
    }

    public function test_password_change_rejects_wrong_password_and_revokes_old_sessions(): void
    {
        $this->requestAs($this->owner, $this->a);
        $new = 'N3w!'.Str::random(28);
        $this->putJson('http://'.$this->hostA.'/account/password', ['current_password' => 'incorrect', 'password' => $new, 'password_confirmation' => $new])->assertStatus(422);
        $oldVersion = $this->owner->session_version;
        $this->put('http://'.$this->hostA.'/account/password', ['current_password' => $this->password, 'password' => $new, 'password_confirmation' => $new])->assertStatus(303);
        $this->assertTrue(Hash::check($new, $this->owner->fresh()->password));
        $this->withSession(['identity_version' => $oldVersion])->get('http://'.$this->hostA.'/workspace')->assertStatus(401);
    }

    public function test_revoked_membership_and_disabled_identity_take_effect_on_next_request(): void
    {
        $this->requestAs($this->owner, $this->a)->get('http://'.$this->hostA.'/workspace')->assertOk();
        DB::connection('pgsql')->table('memberships')->where('tenant_id', $this->a)->where('user_id', $this->owner->id)->update(['active' => false]);
        $this->get('http://'.$this->hostA.'/workspace')->assertForbidden();
        DB::connection('pgsql')->table('memberships')->where('tenant_id', $this->a)->where('user_id', $this->owner->id)->update(['active' => true]);
        User::where('id', $this->owner->id)->update(['active' => false]);
        $this->get('http://'.$this->hostA.'/workspace')->assertStatus(401);
    }

    public function test_mismatched_database_identity_and_tenant_schema_writes_fail_closed(): void
    {
        $this->execute($this->a, function () {
            try {
                DB::connection('tenant')->statement('CREATE TABLE forbidden_schema_write (id int)');
                $this->fail('Tenant role changed schema.');
            } catch (QueryException $error) {
                $this->assertSame('42501', $error->errorInfo[0]);
            }
        });
        $original = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $config = config('database.connections.provisioner');
        $config['database'] = $original->database_name;
        config(['database.connections.identity_test' => $config]);
        DB::connection('identity_test')->table('tenant_identity')->update(['id' => $this->b]);
        DB::purge('identity_test');
        $this->requestAs($this->owner, $this->a)->get('http://'.$this->hostA.'/workspace')->assertForbidden();
        $this->assertNull(config('database.connections.tenant'));
    }

    public function test_mfa_challenge_cannot_be_replayed_on_another_tenant(): void
    {
        $this->postJson('http://'.$this->hostA.'/login', ['email' => $this->owner->email, 'password' => $this->password])->assertOk();
        $code = (new Google2FA)->getCurrentOtp(decrypt($this->owner->two_factor_secret));
        $this->postJson('http://'.$this->hostB.'/two-factor-challenge', ['code' => $code])->assertForbidden();
        $this->assertGuest();
    }

    public function test_versioned_api_ignores_client_tenant_identity_and_duplicate_ids_stay_isolated(): void
    {
        $response = $this->requestAs($this->owner, $this->a)->postJson('http://'.$this->hostA.'/api/v1/organization/company',
            ['name' => 'Only A', 'code' => 'APIA', 'base_currency' => 'LKR', 'timezone' => 'UTC', 'tenant_id' => $this->b], ['X-Tenant-ID' => $this->b])->assertCreated();
        $id = $response->json('id');
        $this->execute($this->b, fn () => DB::connection('tenant')->table('companies')->insert(['id' => $id, 'name' => 'Only B', 'code' => 'APIB', 'base_currency' => 'USD', 'timezone' => 'UTC', 'active' => true, 'created_at' => now(), 'updated_at' => now()]));
        $this->requestAs($this->owner, $this->a)->putJson('http://'.$this->hostA.'/api/v1/organization/company/'.$id, ['name' => 'Updated A', 'tenant_id' => $this->b])->assertOk();
        $this->execute($this->a, fn () => $this->assertSame('Updated A', DB::connection('tenant')->table('companies')->where('id', $id)->value('name')));
        $this->execute($this->b, fn () => $this->assertSame('Only B', DB::connection('tenant')->table('companies')->where('id', $id)->value('name')));
        $this->getJson('http://'.$this->hostA.'/api/v1/me')->assertOk()->assertJsonPath('tenant_id', $this->a)->assertJsonMissingPath('password')->assertJsonMissingPath('two_factor_secret');
    }

    public function test_database_owner_cannot_update_audit_history_and_jobs_reject_suspended_actors(): void
    {
        $this->company($this->a);
        $row = DB::connection('pgsql')->table('tenants')->where('id', $this->a)->first();
        $connection = config('database.connections.provisioner');
        $connection['database'] = $row->database_name;
        config(['database.connections.audit_test' => $connection]);
        try {
            DB::connection('audit_test')->table('audit_events')->update(['action' => 'forged']);
            $this->fail('Database owner rewrote history.');
        } catch (QueryException $error) {
            $this->assertSame('P0001', $error->errorInfo[0]);
        } finally {
            DB::purge('audit_test');
        }
        DB::connection('provisioner')->table('tenants')->where('id', $this->a)->update(['status' => 'suspended']);
        try {
            (new TenantJobMiddleware($this->a, $this->owner->id))->handle(new \stdClass, fn () => $this->fail('Suspended job executed.'));
        } catch (AccessDeniedHttpException) {
            $this->addToAssertionCount(1);
        }
        $this->assertNull(config('database.connections.tenant'));
    }

    public function test_csrf_is_required_for_authenticated_mutations(): void
    {
        $this->app->instance('env', 'local');
        $token = Str::random(40);
        $this->requestAs($this->owner, $this->a)->withSession(['_token' => $token]);
        $data = ['name' => 'CSRF checked', 'code' => 'CSRF', 'base_currency' => 'LKR', 'timezone' => 'UTC'];
        $this->postJson('http://'.$this->hostA.'/api/v1/organization/company', $data)->assertStatus(419);
        $this->postJson('http://'.$this->hostA.'/api/v1/organization/company', $data, ['X-CSRF-TOKEN' => $token])->assertCreated();
    }

    public function test_auditor_only_sees_granted_company_audit_history(): void
    {
        $company = $this->company($this->a, 'Granted');
        $this->company($this->a, 'Not granted');
        DB::connection('pgsql')->table('memberships')->insert(['tenant_id' => $this->a, 'user_id' => $this->reader->id, 'role' => 'auditor', 'active' => true, 'company_ids' => json_encode([$company]), 'branch_ids' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $this->requestAs($this->reader, $this->a)->getJson('http://'.$this->hostA.'/workspace/audit')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.resource_id', $company);
    }

    public function test_one_real_redis_worker_processes_two_tenants_without_context_leakage(): void
    {
        $queue = 'tenant-context-'.Str::uuid();
        $key = 'probe-'.Str::uuid();
        TenantContextProbeJob::dispatch($this->a, $this->owner->id, $key, 'A')->onConnection('redis')->onQueue($queue);
        TenantContextProbeJob::dispatch($this->b, $this->owner->id, $key, 'B')->onConnection('redis')->onQueue($queue);
        $this->artisan('queue:work', ['connection' => 'redis', '--queue' => $queue, '--stop-when-empty' => true, '--tries' => 1, '--timeout' => 20])->assertSuccessful();
        $this->assertNull(config('database.connections.tenant'));
        $this->execute($this->a, fn () => $this->assertSame('A', app(TenantCache::class)->get($key)));
        $this->execute($this->b, fn () => $this->assertSame('B', app(TenantCache::class)->get($key)));
    }
}
