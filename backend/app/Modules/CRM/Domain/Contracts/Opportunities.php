<?php

namespace App\Modules\CRM\Domain\Contracts;

interface Opportunities
{
    public function find(string $companyId, string $id): OpportunityData;
}
