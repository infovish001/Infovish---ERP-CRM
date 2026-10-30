<?php

use App\Modules\Finance\Application\FinanceLedger;
use App\Modules\Finance\Application\FinancePayments;
use App\Platform\Tenancy\TenantExecution;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.pgsql.database') !== 'infovish_test') {
    throw new RuntimeException('Dedicated test database required.');
}
$d = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    echo app(TenantExecution::class)->run($d['tenant'], $d['actor'], fn () => ($d['operation'] === 'payment' ? app(FinancePayments::class)->record($d['company'], $d['payload'], $d['key']) : app(FinanceLedger::class)->postDraft($d['company'], $d['draft'], 1, $d['key'])));
} catch (HttpException $e) {
    echo 'HTTP:'.$e->getStatusCode();
}
