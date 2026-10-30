<?php

namespace App\Modules\CRM\Presentation;

use App\Modules\CRM\Application\ActivityService;
use App\Modules\CRM\Application\CrmAccess;
use App\Modules\CRM\Application\CrmQueries;
use App\Modules\CRM\Application\LeadService;
use App\Modules\CRM\Application\OpportunityService;
use App\Modules\CRM\Application\PipelineService;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\Organization\OrganizationService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CrmController
{
    public function page(Request $request, OrganizationService $organization, TenantContext $context): Response
    {
        return Inertia::render('CRM', ['tenant' => ['name' => $request->attributes->get('tenant')->name], 'user' => ['id' => $context->actorId(), 'name' => $request->user()->name, 'role' => $context->role()],
            'companies' => $organization->companies()->where('active', true)->orderBy('name')->get(), 'initialCompany' => $request->session()->get('company_id'), 'available' => app(ModuleAccess::class)->allowed('CRM'), 'canManage' => $context->can('crm.manage'), 'canAdmin' => $context->can('organization.manage')]);
    }

    public function setup(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $search = $request->validate(['q' => 'nullable|string|max:100']);

        return response()->json(['pipelines' => app(PipelineService::class)->listing($company), 'assignees' => app(CrmAccess::class)->assignees($company), 'customers' => app(Contacts::class)->customers($company, $search['q'] ?? '')]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(app(CrmQueries::class)->listing($this->kind($request), $this->company($request), $request->all()));
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $service = $this->kind($request) === 'lead' ? app(LeadService::class) : app(OpportunityService::class);

        return response()->json(['id' => $service->save($this->company($request), $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function detail(Request $request, string $id): JsonResponse
    {
        $kind = $this->kind($request);
        $company = $this->company($request);

        $record = app(CrmAccess::class)->record($kind, $company, $id);
        unset($record->conversion_hash);

        return response()->json(['record' => $record, 'history' => app(CrmQueries::class)->history($kind, $company, $id), 'activities' => app(ActivityService::class)->listing($company, $kind, $id)]);
    }

    public function transition(Request $request, string $id): JsonResponse
    {
        $service = $this->kind($request) === 'lead' ? app(LeadService::class) : app(OpportunityService::class);

        return response()->json(['id' => $service->transition($this->company($request), $id, $request->all())]);
    }

    public function convert(Request $request, string $id): JsonResponse
    {
        return response()->json(['id' => app(LeadService::class)->convert($this->company($request), $id, $request->all(), $request->header('Idempotency-Key'))]);
    }

    public function pipeline(Request $request, ?string $id = null): JsonResponse
    {
        return response()->json(['id' => app(PipelineService::class)->save($this->company($request), $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function stage(Request $request, string $pipeline, ?string $id = null): JsonResponse
    {
        return response()->json(['id' => app(PipelineService::class)->stage($this->company($request), $pipeline, $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function activity(Request $request, string $kind, string $parent, ?string $id = null): JsonResponse
    {
        return response()->json(['id' => app(ActivityService::class)->save($this->company($request), $kind, $parent, $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function activityState(Request $request, string $id): JsonResponse
    {
        return response()->json(['id' => app(ActivityService::class)->transition($this->company($request), $id, $request->all())]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $data = $request->validate(['pipeline_id' => 'nullable|uuid']);

        return response()->json(app(CrmQueries::class)->forecast($this->company($request), $data['pipeline_id'] ?? null));
    }

    private function kind(Request $request): string
    {
        return $request->route()->named('crm.lead.*') ? 'lead' : 'opportunity';
    }

    private function company(Request $request): string
    {
        return $request->validate(['company_id' => 'required|uuid'])['company_id'];
    }
}
