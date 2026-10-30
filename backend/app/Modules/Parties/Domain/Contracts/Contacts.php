<?php

namespace App\Modules\Parties\Domain\Contracts;

interface Contacts
{
    public function find(string $companyId, string $id): ContactData;

    public function findByEmail(string $companyId, string $email): ?ContactData;

    public function createCustomer(string $companyId, NewCustomer $customer, string $idempotencyKey): ContactData;

    /** @return list<ContactData> */
    public function customers(string $companyId, string $search = ''): array;
}
