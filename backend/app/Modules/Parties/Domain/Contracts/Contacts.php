<?php

namespace App\Modules\Parties\Domain\Contracts;

interface Contacts
{
    public function find(string $companyId, string $id): ContactData;
}
