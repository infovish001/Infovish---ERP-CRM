<?php

namespace Tests\Support;

use App\Platform\Identity\User;
use App\Platform\Organization\OrganizationService;
use App\Platform\Tenancy\RuntimeRole;
use App\Platform\Tenancy\TenantExecution;
use App\Platform\Tenancy\TenantProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Tests\TestCase;

abstract class TenantIntegrationTest extends TestCase
{
    protected array $ids = [];

    protected array $users = [];

    protected array $originalPlatform = [];

    protected string $password;

    protected User $owner;

    protected User $reader;

    protected string $hostA;

    protected string $hostB;

    protected string $a;

    protected string $b;

    protected string $runtimeRole = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_INTEGRATION') !== '1') {
            $this->markTestSkipped('M02 requires real PostgreSQL provisioning and Redis.');
        }
        if (config('database.connections.pgsql.database') !== 'infovish_test' || config('database.connections.provisioner.database') !== 'infovish_test') {
            throw new \RuntimeException('M02 integration tests require dedicated infovish_test connections.');
        }
        $this->withoutVite();
        $this->artisan('migrate', ['--database' => 'provisioner', '--force' => true])->assertSuccessful();
        $this->originalPlatform = config('database.connections.pgsql');
        $this->password = 'T!'.Str::random(32).'9a';
        $this->owner = User::create(['name' => 'Test operator', 'email' => Str::uuid().'@example.test', 'password' => $this->password, 'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()), 'two_factor_recovery_codes' => encrypt(json_encode([Str::random(20)])), 'two_factor_confirmed_at' => now()]);
        $this->reader = User::create(['name' => 'Test reader', 'email' => Str::uuid().'@example.test', 'password' => $this->password]);
        $this->users = [$this->owner->id, $this->reader->id];
        $slugA = 'test-'.Str::lower(Str::random(16));
        $slugB = 'test-'.Str::lower(Str::random(16));
        $this->hostA = $slugA.'.localhost';
        $this->hostB = $slugB.'.localhost';
        $this->a = app(TenantProvisioner::class)->provision($slugA, 'Test tenant A', $this->hostA, $this->owner->id);
        $this->ids[] = $this->a;
        $this->b = app(TenantProvisioner::class)->provision($slugB, 'Test tenant B', $this->hostB, $this->owner->id);
        $this->ids[] = $this->b;
        $this->runtimeRole = 'inv_test_'.Str::lower(Str::random(16));
        $runtimePassword = Str::random(64);
        app(RuntimeRole::class)->configure($this->runtimeRole, $runtimePassword);
        config(['database.connections.pgsql.username' => $this->runtimeRole, 'database.connections.pgsql.password' => $runtimePassword]);
        DB::purge('pgsql');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        if ($this->originalPlatform) {
            app(TenantExecution::class)->clear();
            config(['database.connections.pgsql' => $this->originalPlatform]);
            DB::purge('pgsql');
            $admin = DB::connection('provisioner');
            foreach ($this->ids as $id) {
                $row = DB::connection('pgsql')->table('tenants')->where('id', $id)->first();
                if (! $row || ! str_starts_with($row->slug, 'test-') || $row->database_name !== 'inv_t_'.str_replace('-', '', $id)) {
                    throw new \RuntimeException('Unsafe test cleanup target.');
                }
                $admin->statement('DROP DATABASE "'.$row->database_name.'" WITH (FORCE)');
                $admin->statement('DROP ROLE "'.$row->database_user.'"');
                DB::connection('pgsql')->table('memberships')->where('tenant_id', $id)->delete();
                DB::connection('provisioner')->table('tenant_domains')->where('tenant_id', $id)->delete();
                DB::connection('provisioner')->table('tenants')->where('id', $id)->delete();
            }
            if ($this->runtimeRole) {
                $admin->statement('DROP OWNED BY "'.$this->runtimeRole.'"');
                $admin->statement('DROP ROLE "'.$this->runtimeRole.'"');
            }
            User::whereIn('id', $this->users)->delete();
        }
        parent::tearDown();
    }

    protected function requestAs(User $user, string $tenant): static
    {
        $this->actingAs($user);
        $this->withSession(['tenant_login_id' => $tenant, 'identity_version' => $user->session_version]);

        return $this;
    }

    protected function execute(string $id, callable $callback): mixed
    {
        return app(TenantExecution::class)->run($id, $this->owner->id, fn () => $callback());
    }

    protected function company(string $tenant, string $name = 'Test company'): string
    {
        return $this->execute($tenant, fn () => app(OrganizationService::class)->save('company', ['name' => $name, 'code' => 'C'.Str::upper(Str::random(8)), 'base_currency' => 'LKR', 'timezone' => 'Asia/Colombo']));
    }
}
