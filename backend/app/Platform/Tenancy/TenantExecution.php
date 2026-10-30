<?php

namespace App\Platform\Tenancy;

use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class TenantExecution
{
    public function __construct(private TenantContext $context, private TenantRegistry $registry) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function run(string $tenantId, string $actorId, Closure $operation): mixed
    {
        $this->clear();
        try {
            $tenant = $this->registry->find($tenantId);
            $member = DB::connection('pgsql')->table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')
                ->where('tenant_id', $tenantId)->where('user_id', $actorId)->where('memberships.active', true)->where('users.active', true)
                ->select('memberships.*', 'users.two_factor_confirmed_at')->first();
            if (! $tenant || $tenant->status !== 'active' || ! in_array($tenant->schema_version, [1, 2, 3, 4], true) || ! $member) {
                throw new AccessDeniedHttpException('Workspace access is unavailable.');
            }
            config(['database.connections.tenant' => [
                'driver' => 'pgsql', 'host' => config('tenancy.database_host'), 'port' => config('tenancy.database_port'),
                'database' => $tenant->database_name, 'username' => $tenant->database_user,
                'password' => Crypt::decryptString($tenant->database_password), 'charset' => 'utf8', 'search_path' => 'public',
                'sslmode' => config('tenancy.database_sslmode'), 'options' => [\PDO::ATTR_TIMEOUT => 2],
            ]]);
            if (DB::connection('tenant')->table('tenant_identity')->value('id') !== $tenantId) {
                throw new AccessDeniedHttpException('Workspace identity could not be verified.');
            }
            $this->context->activate($tenantId, $actorId, $member->role, json_decode($member->company_ids, true, flags: JSON_THROW_ON_ERROR), json_decode($member->branch_ids, true, flags: JSON_THROW_ON_ERROR), $member->two_factor_confirmed_at !== null);

            return $operation();
        } finally {
            $this->clear();
        }
    }

    public function clear(): void
    {
        $this->context->clear();
        if (config('database.connections.tenant') !== null) {
            DB::purge('tenant');
        }
        config(['database.connections.tenant' => null]);
    }
}
