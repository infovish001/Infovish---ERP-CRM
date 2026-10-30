<?php

namespace App\Shared\Tenancy;

use App\Platform\Tenancy\TenantExecution;

final class TenantJobMiddleware
{
    public function __construct(private string $tenantId, private string $actorId) {}

    public function handle(object $job, callable $next): void
    {
        app(TenantExecution::class)->run($this->tenantId, $this->actorId, function () use ($next, $job) {
            $context = app(TenantContext::class);
            abort_if($context->privileged() && ! $context->hasConfirmedMfa(), 403);
            $next($job);
        });
    }
}
