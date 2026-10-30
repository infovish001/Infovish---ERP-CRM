<?php

namespace Tests\Support;

use App\Shared\Tenancy\TenantCache;
use App\Shared\Tenancy\TenantJobMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TenantContextProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $tenantId, public string $actorId, public string $key, public string $value) {}

    public function middleware(): array
    {
        return [new TenantJobMiddleware($this->tenantId, $this->actorId)];
    }

    public function handle(): void
    {
        app(TenantCache::class)->put($this->key, $this->value);
    }
}
