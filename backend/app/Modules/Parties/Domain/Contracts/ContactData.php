<?php

namespace App\Modules\Parties\Domain\Contracts;

final readonly class ContactData
{
    public function __construct(public string $id, public string $companyId, public string $number, public string $name, public bool $customer, public bool $supplier) {}
}
