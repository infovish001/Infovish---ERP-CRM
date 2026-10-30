<?php

namespace App\Modules\Finance\Domain\Contracts;

final readonly class BalanceData
{
    public function __construct(public string $companyId, public string $accountId, public string $asOf, public string $debit, public string $credit, public string $net, public string $currency = 'LKR') {}
}
