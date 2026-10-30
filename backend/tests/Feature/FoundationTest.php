<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_health_is_independent_of_external_services(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok'])->assertHeader('X-Correlation-ID');
    }

    public function test_readiness_returns_safe_unavailable_status(): void
    {
        DB::shouldReceive('connection')->with('pgsql')->andThrow(new \RuntimeException('private-password'));
        Redis::shouldReceive('connection')->andThrow(new \RuntimeException('private-host'));
        $this->getJson('/ready')->assertStatus(503)->assertExactJson([
            'status' => 'unavailable', 'checks' => ['database' => 'unavailable', 'redis' => 'unavailable'],
        ])->assertDontSee('private-');
    }

    public function test_readiness_reports_reachable_dependencies(): void
    {
        DB::shouldReceive('connection')->with('pgsql')->andReturnSelf();
        DB::shouldReceive('select')->with('select 1')->andReturn([(object) ['value' => 1]]);
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('ping')->andReturn('PONG');
        $this->getJson('/ready')->assertOk()->assertJsonPath('status', 'ready');
    }

    public function test_inertia_renders_foundation_props_without_business_records(): void
    {
        $this->withoutVite();
        DB::shouldReceive('connection')->with('pgsql')->andReturnSelf();
        DB::shouldReceive('select')->with('select 1')->andReturn([]);
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('ping')->andReturn('PONG');
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Foundation')->where('readiness.status', 'ready')->missing('user')->missing('tenant'));
    }

    public function test_foundation_interface_is_not_exposed_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->get('/')->assertNotFound();
    }
}
