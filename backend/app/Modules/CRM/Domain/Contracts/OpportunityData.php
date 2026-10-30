<?php

namespace App\Modules\CRM\Domain\Contracts;

final readonly class OpportunityData
{
    public function __construct(public string $id, public string $companyId, public string $number, public string $contactId, public string $title, public string $status, public ?string $expectedAmount, public string $currency) {}
}
