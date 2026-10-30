<?php

use App\Modules\Parties\Application\ContactService;
use App\Platform\Tenancy\TenantExecution;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (config('database.connections.pgsql.database') !== 'infovish_test') {
    throw new RuntimeException('Dedicated test database required.');
}
$id = app(TenantExecution::class)->run($data['tenant'], $data['actor'], fn () => app(ContactService::class)->save($data['company'], $data['payload'], key: $data['key']));
echo $id;
