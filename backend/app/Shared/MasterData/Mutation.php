<?php

namespace App\Shared\MasterData;

use App\Platform\ModuleRegistry\ModuleAccess;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class Mutation
{
    /** @param array<string,mixed> $payload @param Closure(): string $operation */
    public function run(string $module, string $company, string $operationName, array $payload, ?string $key, Closure $operation, string $permission = 'organization.manage'): string
    {
        if (! in_array($permission, ['organization.manage', 'crm.manage'], true) || ($permission === 'crm.manage' && $module !== 'CRM')) {
            throw new \InvalidArgumentException('Unsupported mutation permission.');
        }

        return DB::connection('tenant')->transaction(function () use ($module, $company, $operationName, $payload, $key, $operation, $permission) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('module-lifecycle'))");
            // A request may have waited on the tenant lock while an operator revoked access.
            $context = app(TenantContext::class);
            $principal = DB::connection('pgsql')->table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')
                ->where('tenant_id', $context->requireTenantId())->where('user_id', $context->actorId())->where('memberships.active', true)->where('users.active', true)
                ->select('role', 'company_ids', 'users.two_factor_confirmed_at')->first();
            $privileged = $principal && in_array($principal->role, ['owner', 'administrator'], true) && $principal->two_factor_confirmed_at !== null;
            $crmMember = $principal && $permission === 'crm.manage' && $principal->role === 'member' && in_array($company, json_decode($principal->company_ids, true, flags: JSON_THROW_ON_ERROR), true);
            abort_unless($principal && $principal->role === $context->role() && ($privileged || $crmMember), 403, 'Workspace permission changed; sign in or reload before continuing.');
            app(ModuleAccess::class)->require($module, true, $permission);
            app(CompanyScope::class)->verify($company);
            if ($key === null) {
                return $operation();
            }
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9_-]{16,100}\z/']])->validate();
            $scope = hash('sha256', app(TenantContext::class)->actorId().'|'.$company.'|'.$operationName.'|'.$key);
            $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
            DB::connection('tenant')->select('SELECT pg_advisory_xact_lock(hashtext(?))', [$scope]);
            $existing = DB::connection('tenant')->table('idempotency_records')->where('scope', $scope)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Idempotency key was used for a different request.');

                return $existing->resource_id;
            }
            $id = $operation();
            DB::connection('tenant')->table('idempotency_records')->insert(['scope' => $scope, 'request_hash' => $hash, 'resource_id' => $id, 'created_at' => now()]);

            return $id;
        });
    }

    /**
     * @param  array<mixed>  $input
     * @return array<mixed>
     */
    private function canonical(array $input): array
    {
        if (! array_is_list($input)) {
            ksort($input);
        }
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }

        return $input;
    }
}
