<?php

namespace Tests\Unit;

use App\Shared\Tenancy\TenantContext;
use LogicException;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    public function test_missing_verified_context_fails_closed(): void
    {
        $this->expectException(LogicException::class);
        (new TenantContext)->requireTenantId();
    }
}
