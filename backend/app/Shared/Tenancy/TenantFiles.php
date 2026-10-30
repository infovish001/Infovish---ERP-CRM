<?php

namespace App\Shared\Tenancy;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class TenantFiles
{
    public function __construct(private TenantContext $context) {}

    public function put(string $path, string $contents): bool
    {
        return Storage::disk('local')->put($this->path($path), $contents);
    }

    public function get(string $path): ?string
    {
        return Storage::disk('local')->get($this->path($path));
    }

    private function path(string $path): string
    {
        $id = $this->context->requireTenantId();
        if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.\/-]*\z/', $path) || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException('Invalid tenant storage path.');
        }

        return 'tenants/'.$id.'/'.$path;
    }
}
