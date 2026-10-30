<?php

namespace App\Shared\Events;

use App\Shared\Tenancy\TenantJobMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $tenantId, public string $actorId, public string $eventId) {}

    /** @return list<TenantJobMiddleware> */
    public function middleware(): array
    {
        return [new TenantJobMiddleware($this->tenantId, $this->actorId)];
    }

    public function handle(Outbox $outbox): void
    {
        $outbox->consume($this->eventId);
    }
}
