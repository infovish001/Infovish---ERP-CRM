<?php

namespace App\Shared\MasterData;

use Illuminate\Support\Facades\DB;

final class Numbering
{
    public function next(string $company, string $series): string
    {
        if (! in_array($series, ['CON', 'PRD', 'LED', 'OPP', 'QTN', 'SO', 'JDR', 'JRN', 'FDOC', 'INV', 'BILL', 'PAY', 'BNK', 'TAX'], true)) {
            throw new \InvalidArgumentException('Unsupported number series.');
        }
        app(CompanyScope::class)->verify($company);
        if (DB::connection('tenant')->transactionLevel() < 1) {
            throw new \LogicException('Numbering requires a tenant transaction.');
        }
        DB::connection('tenant')->table('document_counters')->insertOrIgnore(['company_id' => $company, 'series' => $series, 'next_value' => 1]);
        $counter = DB::connection('tenant')->table('document_counters')->where('company_id', $company)->where('series', $series)->lockForUpdate()->first();
        abort_if($counter->next_value > 999999999, 409, 'Number series exhausted.');
        DB::connection('tenant')->table('document_counters')->where('company_id', $company)->where('series', $series)->update(['next_value' => $counter->next_value + 1]);

        return $series.'-'.str_pad((string) $counter->next_value, 9, '0', STR_PAD_LEFT);
    }
}
