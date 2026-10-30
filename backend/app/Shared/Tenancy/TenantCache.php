<?php

namespace App\Shared\Tenancy;

use Illuminate\Support\Facades\Cache;

final class TenantCache
{
    public function __construct(private TenantContext $context) {}

    public function put(string $key, mixed $value, int $seconds = 60): void
    {
        Cache::store('redis')->put($this->key($key), $value, $seconds);
    }

    public function get(string $key): mixed
    {
        return Cache::store('redis')->get($this->key($key));
    }

    private function key(string $key): string
    {
        return 'tenant:'.$this->context->requireTenantId().':'.$key;
    }
}
