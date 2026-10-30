<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\ProductService;
use App\Modules\Parties\Application\ContactService;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\Organization\OrganizationService;
use App\Shared\Events\Outbox;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class MasterDataController extends Controller
{
    public function page(Request $request, OrganizationService $organization, TenantContext $context, ModuleAccess $modules): Response
    {
        return Inertia::render('MasterData', ['tenant' => ['name' => $request->attributes->get('tenant')->name],
            'user' => ['name' => $request->user()->name, 'role' => $context->role()],
            'companies' => $organization->companies()->where('active', true)->orderBy('name')->get(),
            'initialCompany' => $request->session()->get('company_id'), 'modules' => $modules->diagnostics(), 'canManage' => $context->can('organization.manage')]);
    }

    public function modules(ModuleAccess $modules): JsonResponse
    {
        return response()->json(['data' => $modules->diagnostics()]);
    }

    public function toggle(Request $request, ModuleAccess $modules, string $module): JsonResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $modules->setEnabled($module, $data['enabled']);

        return $this->modules($modules);
    }

    public function contacts(Request $request, ContactService $service): JsonResponse
    {
        return response()->json($service->listing($this->company($request), $request->all()));
    }

    public function saveContact(Request $request, ContactService $service, ?string $id = null): JsonResponse
    {
        return response()->json(['id' => $service->save($this->company($request), $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function products(Request $request, ProductService $service): JsonResponse
    {
        return response()->json($service->listing($this->company($request), $request->all()));
    }

    public function saveProduct(Request $request, ProductService $service, ?string $id = null): JsonResponse
    {
        return response()->json(['id' => $service->save($this->company($request), $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function saveVariant(Request $request, ProductService $service, string $product, ?string $id = null): JsonResponse
    {
        return response()->json(['id' => $service->saveVariant($this->company($request), $product, $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function events(Request $request, TenantContext $context): JsonResponse
    {
        abort_unless($context->can('organization.manage'), 403);
        $data = $request->validate(['page' => 'sometimes|integer|min:1|max:100000']);
        $ready = DB::connection('pgsql')->table('tenants')->where('id', $context->requireTenantId())->value('schema_version') === 2;
        abort_unless($ready, 409, 'Operator upgrade required.');

        return response()->json(DB::connection('tenant')->table('event_outbox')->select('id', 'event_type', 'event_version', 'aggregate_id', 'company_id', 'state', 'attempts', 'error_code', 'occurred_at', 'delivered_at')->orderByDesc('occurred_at')->orderByDesc('id')->simplePaginate(25, page: $data['page'] ?? 1));
    }

    public function replay(string $id, Outbox $outbox): JsonResponse
    {
        $outbox->replay($id);

        return response()->json(['status' => 'pending']);
    }

    private function company(Request $request): string
    {
        return $request->validate(['company_id' => 'required|uuid'])['company_id'];
    }
}
