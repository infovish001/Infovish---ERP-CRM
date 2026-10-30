<?php

namespace App\Shared\Tenancy;

use LogicException;

/** Trusted resolution and membership enforcement arrive in M02. */
final readonly class TenantContext
{
    public function __construct(private ?string $verifiedTenantId = null) {}

    public function requireTenantId(): string
    {
        return $this->verifiedTenantId ?? throw new LogicException('Verified tenant context is required.');
    }
}
