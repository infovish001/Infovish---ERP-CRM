<?php

namespace App\Shared\Health;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

final class FoundationProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(public readonly string $probeId) {}

    public function handle(): void
    {
        Redis::setex('foundation:probe:'.$this->probeId, 60, 'processed');
    }
}
