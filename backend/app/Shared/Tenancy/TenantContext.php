<?php

namespace App\Shared\Tenancy;

use LogicException;

/** Mutable state is populated only by verified execution and cleared in finally blocks. */
final class TenantContext
{
    private ?string $tenantId = null;

    private ?string $actorId = null;

    private string $role = '';

    private bool $mfaConfirmed = false;

    /** @var list<string> */
    private array $companyIds = [];

    /** @var list<string> */
    private array $branchIds = [];

    /**
     * @param  list<string>  $companies
     * @param  list<string>  $branches
     *
     * @internal
     */
    public function activate(string $tenantId, string $actorId, string $role, array $companies, array $branches, bool $mfaConfirmed): void
    {
        $this->tenantId = $tenantId;
        $this->actorId = $actorId;
        $this->role = $role;
        $this->mfaConfirmed = $mfaConfirmed;
        $this->companyIds = $companies;
        $this->branchIds = $branches;
    }

    public function requireTenantId(): string
    {
        return $this->tenantId ?? throw new LogicException('Verified tenant context is required.');
    }

    public function actorId(): string
    {
        $this->requireTenantId();

        return $this->actorId ?? throw new LogicException('Verified actor is required.');
    }

    public function role(): string
    {
        $this->requireTenantId();

        return $this->role;
    }

    public function privileged(): bool
    {
        return in_array($this->role(), ['owner', 'administrator'], true);
    }

    public function can(string $permission): bool
    {
        return match ($permission) {
            'organization.view' => in_array($this->role(), ['owner', 'administrator', 'auditor', 'member'], true),
            'organization.manage' => $this->privileged() && $this->mfaConfirmed,
            'crm.manage' => ($this->privileged() && $this->mfaConfirmed) || $this->role() === 'member',
            'memberships.manage' => $this->role() === 'owner' && $this->mfaConfirmed,
            'audit.view' => in_array($this->role(), ['owner', 'administrator', 'auditor'], true),
            default => false,
        };
    }

    public function permitsCompany(string $id): bool
    {
        return $this->privileged() || in_array($id, $this->companyIds, true);
    }

    public function permitsBranch(string $id): bool
    {
        return $this->privileged() || in_array($id, $this->branchIds, true);
    }

    /** @return list<string> */
    public function companyIds(): array
    {
        $this->requireTenantId();

        return $this->companyIds;
    }

    /** @return list<string> */
    public function branchIds(): array
    {
        $this->requireTenantId();

        return $this->branchIds;
    }

    public function clear(): void
    {
        $this->tenantId = $this->actorId = null;
        $this->role = '';
        $this->mfaConfirmed = false;
        $this->companyIds = $this->branchIds = [];
    }

    public function hasConfirmedMfa(): bool
    {
        $this->requireTenantId();

        return $this->mfaConfirmed;
    }
}
