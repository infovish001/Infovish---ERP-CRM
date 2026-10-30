<?php

namespace App\Shared\MasterData;

use App\Platform\Organization\OrganizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CompanyScope
{
    public function verify(string $company): \stdClass
    {
        Validator::make(['company_id' => $company], ['company_id' => 'required|uuid'])->validate();
        app(OrganizationService::class)->verifyScope($company, null);

        return DB::connection('tenant')->table('companies')->where('id', $company)->firstOrFail();
    }
}
