<?php

use App\Http\Controllers\WorkspaceController;
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
    if (request()->attributes->has('tenant')) {
        return redirect('/workspace');
    }
    abort_unless(app()->environment(['local', 'testing']), 404);

    return Inertia::render('Foundation', ['readiness' => $readiness->check()]);
});

Route::middleware('auth')->group(function () {
    Route::get('/account/security', [WorkspaceController::class, 'security']);
    Route::put('/account/password', [WorkspaceController::class, 'password']);
    Route::middleware('privileged-mfa')->group(function () {
        Route::get('/api/v1/me', [WorkspaceController::class, 'me']);
        Route::get('/api/v1/organization', [WorkspaceController::class, 'organizationJson']);
        Route::post('/api/v1/organization/{kind}', [WorkspaceController::class, 'saveOrganization'])->whereIn('kind', ['company', 'branch', 'department']);
        Route::put('/api/v1/organization/{kind}/{id}', [WorkspaceController::class, 'saveOrganization'])->whereUuid('id')->whereIn('kind', ['company', 'branch', 'department']);
        Route::get('/workspace', [WorkspaceController::class, 'show']);
        Route::post('/workspace/scope', [WorkspaceController::class, 'switchScope']);
        Route::post('/organization/{kind}', [WorkspaceController::class, 'saveOrganization'])->whereIn('kind', ['company', 'branch', 'department']);
        Route::put('/organization/{kind}/{id}', [WorkspaceController::class, 'saveOrganization'])->whereUuid('id')->whereIn('kind', ['company', 'branch', 'department']);
        Route::get('/workspace/memberships', [WorkspaceController::class, 'memberships']);
        Route::post('/workspace/memberships', [WorkspaceController::class, 'saveMembership']);
        Route::get('/workspace/audit', [WorkspaceController::class, 'audit']);
    });
});
