<?php

namespace App\Modules\Parties\Domain\Contracts;

final readonly class NewCustomer
{
    public function __construct(public string $name, public ?string $email, public ?string $phone) {}
}
