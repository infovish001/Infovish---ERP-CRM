<?php

use App\Modules\Sales\Application\SalesService;
use App\Platform\Tenancy\TenantExecution;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.pgsql.database') !== 'infovish_test') {
    throw new RuntimeException('Dedicated test database required.');
}
$data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo app(TenantExecution::class)->run($data['tenant'], $data['actor'], fn () => app(SalesService::class)->convert($data['company'], $data['quote'], ['revision' => 2], $data['key']));
