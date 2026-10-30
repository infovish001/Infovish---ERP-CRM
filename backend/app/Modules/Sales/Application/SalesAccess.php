<?php

namespace App\Modules\Sales\Application;

use App\Platform\ModuleRegistry\ModuleAccess;
use App\Shared\MasterData\CompanyScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SalesAccess
{
    public function company(string $company, bool $write = false): void
    {
        app(ModuleAccess::class)->require('Sales', $write);
        app(CompanyScope::class)->verify($company);
    }

    public function records(string $company, bool $orders = false): Builder
    {
        $this->company($company);

        return DB::connection('tenant')->table($orders ? 'sales_orders' : 'sales_quotes')->where('company_id', $company);
    }

    public function record(string $company, string $id, bool $orders = false, bool $lock = false): \stdClass
    {
        $query = $this->records($company, $orders)->where('id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($row !== null, 404);

        return $row;
    }
}
