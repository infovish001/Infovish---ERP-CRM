<?php

namespace App\Shared\Audit;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class Audit
{
    /** @param array<string, mixed> $metadata */
    public function platform(string $action, ?string $actor, ?string $tenant = null, ?string $resource = null, array $metadata = [], string $connection = 'pgsql'): void
    {
        DB::connection($connection)->table('platform_audit_events')->insert($this->row($action, $actor, $resource, $metadata) + ['tenant_id' => $tenant]);
    }

    /** @param array<string, mixed> $metadata */
    public function tenant(string $action, string $resource, array $metadata = []): void
    {
        $context = app(TenantContext::class);
        $context->requireTenantId();
        DB::connection('tenant')->table('audit_events')->insert($this->row($action, $context->actorId(), $resource, $metadata));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function row(string $action, ?string $actor, ?string $resource, array $metadata): array
    {
        $allowed = ['identity.created', 'identity.login', 'identity.logout', 'identity.failed', 'identity.password_changed', 'identity.revoked',
            'identity.mfa_setup_started', 'identity.mfa_confirmed', 'identity.mfa_disabled', 'identity.recovery_codes_generated', 'identity.recovery_code_used',
            'tenant.provisioned', 'tenant.provision_failed', 'tenant.suspended', 'tenant.resumed', 'tenant.domain_registered', 'membership.updated',
            'company.created', 'company.updated', 'branch.created', 'branch.updated', 'department.created', 'department.updated',
            'tenant.schema_upgraded', 'module.entitlement_changed', 'module.enabled', 'module.disabled', 'contact.created', 'contact.updated',
            'product.created', 'product.updated', 'variant.created', 'variant.updated', 'event.replayed'];
        if (! in_array($action, $allowed, true) || array_diff(array_keys($metadata), ['role', 'active', 'company_id', 'branch_id', 'changed_fields'])) {
            throw new InvalidArgumentException('Unsupported audit event metadata.');
        }

        return ['id' => (string) Str::uuid(), 'actor_id' => $actor, 'action' => $action, 'resource_id' => $resource,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'correlation_id' => request()->attributes->get('correlation_id'), 'occurred_at' => now()];
    }
}
