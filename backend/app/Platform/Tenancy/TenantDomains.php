<?php

namespace App\Platform\Tenancy;

use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class TenantDomains
{
    public function register(string $tenantId, string $host): void
    {
        $host = strtolower($host);
        Validator::make(['host' => $host], ['host' => ['required', 'max:253', 'regex:/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+\z/']])->validate();
        abort_if(in_array($host, config('tenancy.platform_hosts'), true), 422);
        DB::connection('provisioner')->transaction(function () use ($tenantId, $host) {
            $tenant = DB::connection('provisioner')->table('tenants')->where('id', $tenantId)->lockForUpdate()->first();
            abort_unless($tenant && in_array($tenant->status, ['active', 'suspended'], true), 422);
            DB::connection('provisioner')->table('tenant_domains')->insertOrIgnore(['host' => $host, 'tenant_id' => $tenantId]);
            abort_unless(DB::connection('provisioner')->table('tenant_domains')->where('host', $host)->value('tenant_id') === $tenantId, 422, 'Hostname is already registered.');
            app(Audit::class)->platform('tenant.domain_registered', null, $tenantId, $tenantId, connection: 'provisioner');
        });
    }
}
