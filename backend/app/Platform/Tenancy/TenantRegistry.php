<?php

namespace App\Platform\Tenancy;

use Illuminate\Support\Facades\DB;
use stdClass;

final class TenantRegistry
{
    public function forHost(string $host): ?stdClass
    {
        $domain = DB::connection('pgsql')->table('tenant_domains')->where('host', strtolower($host))->first();

        return $domain ? $this->find((string) $domain->tenant_id) : null;
    }

    public function find(string $id): ?stdClass
    {
        return DB::connection('pgsql')->table('tenants')->where('id', $id)->first();
    }
}
