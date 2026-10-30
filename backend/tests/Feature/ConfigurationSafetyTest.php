<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigurationSafetyTest extends TestCase
{
    public function test_example_configuration_contains_no_key_or_database_secret(): void
    {
        $example = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $example);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD=\s*$/m', $example);
        $this->assertStringContainsString('APP_DEBUG=false', $example);
        $this->assertStringContainsString('REDIS_CLIENT=predis', $example);
    }

    public function test_database_and_redis_have_bounded_probe_timeouts(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame(2, config('database.connections.pgsql.options')[\PDO::ATTR_TIMEOUT]);
        $this->assertSame(2.0, config('database.redis.default.timeout'));
    }
}
