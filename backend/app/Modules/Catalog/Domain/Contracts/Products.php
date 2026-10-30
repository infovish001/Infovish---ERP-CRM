<?php

namespace App\Modules\Catalog\Domain\Contracts;

interface Products
{
    public function find(string $companyId, string $variantId): ProductData;
}
