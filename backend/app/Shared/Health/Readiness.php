<?php

namespace App\Shared\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class Readiness
{
    /** @return array{status: string, checks: array<string, string>} */
    public function check(): array
    {
        $checks = [];
        foreach (['database' => fn () => DB::connection('pgsql')->select('select 1'),
            'redis' => fn () => Redis::connection()->ping()] as $name => $probe) {
            try {
                $probe();
                $checks[$name] = 'ready';
            } catch (Throwable) {
                $checks[$name] = 'unavailable';
            }
        }

        return ['status' => in_array('unavailable', $checks, true) ? 'unavailable' : 'ready', 'checks' => $checks];
    }
}
