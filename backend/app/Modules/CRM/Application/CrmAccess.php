<?php

namespace App\Modules\CRM\Application;

use App\Platform\ModuleRegistry\ModuleAccess;
use App\Shared\Audit\Audit;
use App\Shared\Events\Outbox;
use App\Shared\MasterData\CompanyScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CrmAccess
{
    public function company(string $company, bool $write = false): void
    {
        $context = app(TenantContext::class);
        app(ModuleAccess::class)->require('CRM', $write, 'crm.manage');
        abort_if($context->privileged() && ! $context->hasConfirmedMfa(), 403);
        app(CompanyScope::class)->verify($company);
    }

    public function records(string $kind, string $company, bool $write = false): Builder
    {
        $this->company($company, $write);
        $table = match ($kind) {
            'lead' => 'crm_leads','opportunity' => 'crm_opportunities',default => throw new \InvalidArgumentException('Unsupported CRM record kind.')
        };
        $query = DB::connection('tenant')->table($table)->where('company_id', $company);
        if (app(TenantContext::class)->role() === 'member') {
            $query->where('assigned_to', app(TenantContext::class)->actorId());
        }

        return $query;
    }

    public function record(string $kind, string $company, string $id, bool $write = false, bool $lock = false): \stdClass
    {
        $query = $this->records($kind, $company, $write)->where('id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    public function assignee(string $company, ?string $id): string
    {
        $context = app(TenantContext::class);
        $id ??= $context->actorId();
        Validator::make(['assigned_to' => $id], ['assigned_to' => 'required|uuid'])->validate();
        abort_unless($context->privileged() || $id === $context->actorId(), 403, 'Members may assign records only to themselves.');
        $row = DB::connection('pgsql')->table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->where('tenant_id', $context->requireTenantId())->where('user_id', $id)->where('memberships.active', true)->where('users.active', true)->select('role', 'company_ids', 'users.two_factor_confirmed_at')->first();
        $valid = $row && (($row->role === 'member' && in_array($company, json_decode($row->company_ids, true, flags: JSON_THROW_ON_ERROR), true)) || (in_array($row->role, ['owner', 'administrator'], true) && $row->two_factor_confirmed_at !== null));
        abort_unless($valid, 422, 'Choose an active writable member of this company.');

        return $id;
    }

    /** @return list<array{id:string,name:string}> */
    public function assignees(string $company): array
    {
        $this->company($company);
        $context = app(TenantContext::class);
        if (! $context->privileged()) {
            return [['id' => $context->actorId(), 'name' => request()->user()->name ?? 'Me']];
        }

        return DB::connection('pgsql')->table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->where('tenant_id', $context->requireTenantId())->where('memberships.active', true)->where('users.active', true)
            ->where(fn ($q) => $q->where(fn ($p) => $p->whereIn('role', ['owner', 'administrator'])->whereNotNull('two_factor_confirmed_at'))->orWhere(fn ($p) => $p->where('role', 'member')->whereJsonContains('company_ids', $company)))
            ->orderBy('users.name')->get(['users.id', 'users.name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])->all();
    }

    public function event(string $company, string $kind, string $id, string $action, ?string $reason = null, ?string $from = null, ?string $to = null): void
    {
        DB::connection('tenant')->table('crm_history')->insert(['id' => (string) Str::uuid(), 'company_id' => $company, 'resource_id' => $id, 'kind' => $kind, 'actor_id' => app(TenantContext::class)->actorId(), 'action' => $action, 'reason' => $reason, 'from_stage' => $from, 'to_stage' => $to, 'occurred_at' => now()]);
        app(Audit::class)->tenant('crm.'.$action, $id, ['company_id' => $company]);
        app(Outbox::class)->record('CRM', 'crm.'.$action, $id, $company);
    }

    public function privileged(): void
    {
        abort_unless(app(TenantContext::class)->can('organization.manage'), 403);
    }
}
