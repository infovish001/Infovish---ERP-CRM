<?php

namespace App\Modules\Sales\Domain\Contracts;

interface Orders
{
    public function find(string $companyId, string $id): OrderData;
}
