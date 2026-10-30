<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set RUN_INTEGRATION=1 with a dedicated infovish_test PostgreSQL database and Redis.');
        }
        // Prevent migration/rollback checks from touching a development or production database.
        if (config('database.connections.pgsql.database') !== 'infovish_test') {
            throw new \RuntimeException('Integration tests require infovish_test.');
        }
    }

    public function test_postgres_migrations_round_trip_and_transactions(): void
    {
        $this->artisan('migrate', ['--database' => 'provisioner', '--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('foundation_checks'));
        $id = (string) Str::uuid();
        DB::beginTransaction();
        try {
            DB::table('foundation_checks')->insert(['id' => $id, 'checked_at' => now()]);
            $this->assertSame(1, DB::table('foundation_checks')->where('id', $id)->count());
        } finally {
            DB::rollBack();
        }
        $this->assertSame(0, DB::table('foundation_checks')->where('id', $id)->count());
        $this->artisan('migrate', ['--database' => 'provisioner', '--path' => 'database/testing-migrations', '--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('migration_probe'));
        $this->artisan('migrate:rollback', ['--database' => 'provisioner', '--path' => 'database/testing-migrations', '--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('migration_probe'));
    }

    public function test_real_redis_connectivity_and_queue_processing(): void
    {
        $this->assertNotFalse(Redis::connection()->ping());
        $this->artisan('foundation:queue-check')->assertSuccessful();
    }
}
