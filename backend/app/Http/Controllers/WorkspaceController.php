<?php

namespace App\Http\Controllers;

use App\Platform\Identity\MembershipService;
use App\Platform\Organization\OrganizationService;
use App\Shared\Audit\Audit;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function show(Request $request, OrganizationService $organization, TenantContext $context): Response
    {
        $snapshot = $organization->snapshot();
        $companyId = $request->session()->get('company_id');
        $branchId = $request->session()->get('branch_id');
        $companies = collect($snapshot['companies'])->where('active', true);
        if (! $companies->contains('id', $companyId)) {
            $companyId = $companies->first()?->id;
            $branchId = null;
        }
        if ($branchId && ! collect($snapshot['branches'])->contains(fn ($row) => $row->id === $branchId && $row->company_id === $companyId && $row->active)) {
            $branchId = null;
        }
        $request->session()->put(['company_id' => $companyId, 'branch_id' => $branchId]);

        return Inertia::render('Workspace', $snapshot + [
            'tenant' => ['id' => $context->requireTenantId(), 'name' => $request->attributes->get('tenant')->name],
            'user' => ['name' => $request->user()->name, 'email' => $request->user()->email, 'role' => $context->role()],
            'permissions' => ['manageOrganization' => $context->can('organization.manage'), 'manageMemberships' => $context->can('memberships.manage'), 'viewAudit' => $context->can('audit.view')],
            'scope' => ['companyId' => $companyId, 'branchId' => $branchId],
        ]);
    }

    public function saveOrganization(Request $request, OrganizationService $organization, string $kind, ?string $id = null): RedirectResponse|JsonResponse
    {
        $saved = $organization->save($kind, $request->all(), $id);
        if ($request->is('api/*')) {
            return response()->json(['id' => $saved], $id ? 200 : 201);
        }

        return back(303);
    }

    public function switchScope(Request $request, OrganizationService $organization): RedirectResponse
    {
        $data = $request->validate(['company_id' => 'required|uuid', 'branch_id' => 'nullable|uuid']);
        $organization->verifyScope($data['company_id'], $data['branch_id'] ?? null);
        $request->session()->put(['company_id' => $data['company_id'], 'branch_id' => $data['branch_id'] ?? null]);

        return back(303);
    }

    public function organizationJson(OrganizationService $organization): JsonResponse
    {
        return response()->json($organization->snapshot());
    }

    public function me(Request $request, TenantContext $context): JsonResponse
    {
        return response()->json(['id' => $context->actorId(), 'name' => $request->user()->name, 'email' => $request->user()->email,
            'tenant_id' => $context->requireTenantId(), 'role' => $context->role()]);
    }

    public function memberships(Request $request, TenantContext $context): JsonResponse
    {
        abort_unless($context->can('memberships.manage'), 403);

        return response()->json(DB::connection('pgsql')->table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('tenant_id', $context->requireTenantId())->select('users.name', 'users.email', 'memberships.role', 'memberships.active', 'memberships.company_ids', 'memberships.branch_ids')->orderBy('users.name')->get());
    }

    public function saveMembership(Request $request, MembershipService $service): RedirectResponse
    {
        $service->save($request->all());

        return back(303);
    }

    public function audit(Request $request, TenantContext $context): JsonResponse
    {
        abort_unless($context->can('audit.view'), 403);
        $page = $request->validate(['page' => 'sometimes|integer|min:1|max:100000']);
        $query = DB::connection('tenant')->table('audit_events');
        if (! $context->privileged()) {
            $query->whereIn('metadata->company_id', $context->companyIds());
        }

        return response()->json($query->orderByDesc('occurred_at')->orderByDesc('id')->simplePaginate(50, page: $page['page'] ?? 1));
    }

    public function security(Request $request): Response
    {
        return Inertia::render('Security', ['confirmed' => (bool) $request->user()->two_factor_confirmed_at]);
    }

    public function password(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols()]]);
        DB::connection('pgsql')->transaction(function () use ($request, $data, $context) {
            $user = $request->user();
            $user->password = Hash::make($data['password']);
            $user->session_version++;
            $user->remember_token = null;
            $user->save();
            $request->session()->regenerate();
            $request->session()->put('identity_version', $user->session_version);
            app(Audit::class)->platform('identity.password_changed', $context->actorId(), $context->requireTenantId(), $user->id);
        });

        return back(303);
    }
}
