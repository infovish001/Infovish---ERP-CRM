<?php

namespace App\Modules\Catalog\Domain\Contracts;

final readonly class ProductData
{
    public function __construct(public string $id, public string $productId, public string $companyId, public string $sku, public string $name, public string $type, public string $unit, public ?string $listPrice, public string $currency) {}
}
