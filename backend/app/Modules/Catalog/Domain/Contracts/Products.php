<?php

namespace App\Modules\Catalog\Domain\Contracts;

interface Products
{
    /** @return list<ProductData> */
    public function available(string $companyId, string $search = ''): array;

    public function find(string $companyId, string $variantId): ProductData;
}
