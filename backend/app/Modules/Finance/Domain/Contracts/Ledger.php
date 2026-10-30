<?php

namespace App\Modules\Finance\Domain\Contracts;

interface Ledger
{
    public function balance(string $companyId, string $accountId, string $asOf): BalanceData;
}
