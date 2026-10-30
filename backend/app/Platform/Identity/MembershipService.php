<?php

namespace App\Platform\Identity;

use App\Platform\Organization\OrganizationService;
use App\Shared\Audit\Audit;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class MembershipService
{
    /** @param array<string, mixed> $input */
    public function save(array $input): void
    {
        $context = app(TenantContext::class);
        abort_unless($context->can('memberships.manage'), 403);
        $data = Validator::make($input, [
            'email' => 'required|email|max:254', 'role' => ['required', Rule::in(['owner', 'administrator', 'auditor', 'member'])],
            'active' => 'required|boolean', 'company_ids' => 'present|array|max:1000', 'company_ids.*' => 'uuid|distinct',
            'branch_ids' => 'present|array|max:1000', 'branch_ids.*' => 'uuid|distinct',
        ])->validate();
        $target = User::where('email', strtolower($data['email']))->first();
        abort_unless($target && (! $data['active'] || $target->active), 422, 'An active platform identity is required.');
        app(OrganizationService::class)->validateGrants($data['company_ids'], $data['branch_ids']);
        DB::connection('pgsql')->transaction(function () use ($context, $target, $data) {
            $tenantId = $context->requireTenantId();
            DB::connection('pgsql')->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['tenant-membership:'.$tenantId]);
            $tenant = DB::connection('pgsql')->table('tenants')->where('id', $tenantId)->first();
            abort_unless($tenant && $tenant->status === 'active' && DB::connection('pgsql')->table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')
                ->where('tenant_id', $tenantId)->where('user_id', $context->actorId())->where('role', 'owner')->where('memberships.active', true)->where('users.active', true)->whereNotNull('users.two_factor_confirmed_at')->exists(), 403);
            $members = DB::connection('pgsql')->table('memberships');
            $current = (clone $members)->where('tenant_id', $tenantId)->where('user_id', $target->id)->first();
            if ($current && $current->role === 'owner' && $current->active && ($data['role'] !== 'owner' || ! $data['active'])) {
                abort_if((clone $members)->join('users', 'users.id', '=', 'memberships.user_id')->where('tenant_id', $tenantId)->where('role', 'owner')->where('memberships.active', true)->where('users.active', true)->count() <= 1, 422, 'A workspace must retain an active owner.');
            }
            $members->updateOrInsert(['tenant_id' => $tenantId, 'user_id' => $target->id], [
                'role' => $data['role'], 'active' => $data['active'], 'company_ids' => json_encode($data['company_ids'], JSON_THROW_ON_ERROR),
                'branch_ids' => json_encode($data['branch_ids'], JSON_THROW_ON_ERROR), 'created_at' => $current->created_at ?? now(), 'updated_at' => now(),
            ]);
            app(Audit::class)->platform('membership.updated', $context->actorId(), $tenantId, $target->id, ['role' => $data['role'], 'active' => $data['active']]);
        });
    }
}
