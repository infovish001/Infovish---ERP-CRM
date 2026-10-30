<?php

use App\Shared\Health\Readiness;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/ready', function (Readiness $readiness) {
    $result = $readiness->check();

    return response()->json($result, $result['status'] === 'ready' ? 200 : 503);
});
// Foundation UI stays local until identity and tenancy exist in M02.
Route::get('/', function (Readiness $readiness) {
    abort_unless(app()->environment(['local', 'testing']), 404);

    return Inertia::render('Foundation', ['readiness' => $readiness->check()]);
});
