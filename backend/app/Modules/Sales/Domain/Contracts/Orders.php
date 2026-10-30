<?php

namespace App\Modules\Sales\Domain\Contracts;

interface Orders
{
    public function billing(string $companyId, string $id): BillingData;

    /** @return list<OrderData> */
    public function available(string $companyId, string $search = ''): array;

    public function find(string $companyId, string $id): OrderData;
}
