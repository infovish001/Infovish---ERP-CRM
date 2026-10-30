<?php

namespace App\Modules\CRM\Domain\Contracts;

interface Opportunities
{
    /** @return list<OpportunityData> */
    public function openForCustomer(string $companyId, string $customerId): array;

    public function find(string $companyId, string $id): OpportunityData;
}
