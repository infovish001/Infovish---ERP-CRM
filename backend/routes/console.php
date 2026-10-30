<?php

use App\Shared\Health\FoundationProbe;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

Artisan::command('foundation:queue-check', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Foundation probe is restricted to local/testing.');

        return 1;
    }
    $id = (string) Str::uuid();
    $queue = 'foundation-'.Str::uuid();
    FoundationProbe::dispatch($id)->onConnection('redis')->onQueue($queue);
    $this->call('queue:work', ['connection' => 'redis', '--queue' => $queue, '--once' => true, '--tries' => 1, '--timeout' => 20]);
    $key = 'foundation:probe:'.$id;
    $processed = Redis::get($key) === 'processed';
    Redis::del($key);
    $processed ? $this->info('PASS: Redis queued job processed.') : $this->error('FAIL: Queue result missing.');

    return $processed ? 0 : 1;
})->purpose('Dispatch and process an isolated real Redis queue probe');

require __DIR__.'/platform-console.php';
