<?php

namespace App\Modules\Sales\Domain\Contracts;

final readonly class OrderData
{
    public function __construct(public string $id, public string $companyId, public string $number, public string $quoteId, public string $customerId, public string $currency, public string $net, public string $vat, public string $total) {}
}
