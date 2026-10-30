<?php

namespace App\Shared\Events;

use App\Platform\ModuleRegistry\ModuleAccess;
use App\Shared\Audit\Audit;
use App\Shared\MasterData\CompanyScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class Outbox
{
    public function record(string $module, string $type, string $aggregate, string $company): void
    {
        if (DB::connection('tenant')->transactionLevel() < 1) {
            throw new \LogicException('Outbox writes require a tenant transaction.');
        }
        $context = app(TenantContext::class);
        DB::connection('tenant')->table('event_outbox')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $context->requireTenantId(), 'actor_id' => $context->actorId(),
            'company_id' => $company, 'module' => $module, 'event_type' => $type, 'event_version' => 1, 'aggregate_id' => $aggregate,
            'correlation_id' => request()->attributes->get('correlation_id'), 'occurred_at' => now(), 'available_at' => now()]);
    }

    public function dispatch(string $queue = 'default'): int
    {
        $context = app(TenantContext::class);
        abort_unless($context->can('organization.manage'), 403);
        $ids = DB::connection('tenant')->table('event_outbox')->whereIn('state', ['pending', 'queued'])->where('available_at', '<=', now())->orderBy('occurred_at')->limit(100)->pluck('id');
        $published = 0;
        foreach ($ids as $id) {
            $sent = DB::connection('tenant')->transaction(function () use ($id, $queue, $context) {
                $row = DB::connection('tenant')->table('event_outbox')->where('id', $id)->lockForUpdate()->first();
                if (! $row || ! in_array($row->state, ['pending', 'queued'], true) || now()->lt($row->available_at)) {
                    return false;
                }
                DB::connection('tenant')->table('event_outbox')->where('id', $id)->update(['state' => 'queued', 'available_at' => now()->addSeconds(60)]);
                // Publishing inside this short transaction makes an enqueue failure roll back the lease.
                // A worker can block on the row; a crash after enqueue can still produce duplicates.
                DeliverEvent::dispatch($context->requireTenantId(), $context->actorId(), $id)->onConnection('redis')->onQueue($queue);

                return true;
            });
            if ($sent) {
                $published++;
            }
        }

        return $published;
    }

    public function consume(string $id): void
    {
        abort_unless(app(TenantContext::class)->can('organization.manage'), 403);
        DB::connection('tenant')->transaction(function () use ($id) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('module-lifecycle'))");
            $row = DB::connection('tenant')->table('event_outbox')->where('id', $id)->lockForUpdate()->first();
            if (! $row || in_array($row->state, ['delivered', 'dead'], true)) {
                return;
            }
            try {
                DB::connection('tenant')->transaction(function () use ($row, $id) {
                    $context = app(TenantContext::class);
                    abort_unless($row->tenant_id === $context->requireTenantId() && $row->event_version === 1 && in_array($row->event_type, ['contact.created', 'contact.updated', 'product.created', 'product.updated', 'variant.created', 'variant.updated', 'crm.lead.created', 'crm.lead.updated', 'crm.lead.qualify', 'crm.lead.lost', 'crm.lead.reopen', 'crm.lead.converted', 'crm.opportunity.created', 'crm.opportunity.updated', 'crm.opportunity.move', 'crm.opportunity.won', 'crm.opportunity.lost', 'crm.opportunity.reopen', 'crm.pipeline.created', 'crm.pipeline.updated', 'crm.stage.created', 'crm.stage.updated', 'crm.activity.created', 'crm.activity.updated', 'crm.activity.done', 'crm.activity.cancelled'], true), 422);
                    abort_unless(($row->module === 'Parties' && str_starts_with($row->event_type, 'contact.')) || ($row->module === 'Catalog' && (str_starts_with($row->event_type, 'product.') || str_starts_with($row->event_type, 'variant.'))) || ($row->module === 'CRM' && str_starts_with($row->event_type, 'crm.')), 422);
                    app(ModuleAccess::class)->require($row->module, true);
                    app(CompanyScope::class)->verify($row->company_id);
                    $author = DB::connection('pgsql')->table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->where('tenant_id', $row->tenant_id)->where('user_id', $row->actor_id)->where('memberships.active', true)->where('users.active', true)->select('role', 'company_ids', 'users.two_factor_confirmed_at')->first();
                    $privileged = $author && in_array($author->role, ['owner', 'administrator'], true) && $author->two_factor_confirmed_at !== null;
                    $crmMember = $author && $row->module === 'CRM' && $author->role === 'member' && in_array($row->company_id, json_decode($author->company_ids, true, flags: JSON_THROW_ON_ERROR), true);
                    abort_unless($privileged || $crmMember, 403);
                    DB::connection('tenant')->table('event_receipts')->insertOrIgnore(['event_id' => $id, 'consumer' => 'master-data-journal-v1', 'processed_at' => now()]);
                    DB::connection('tenant')->table('event_outbox')->where('id', $id)->update(['state' => 'delivered', 'delivered_at' => now(), 'error_code' => null]);
                });
            } catch (\Throwable $error) {
                $attempts = $row->attempts + 1;
                $code = $error instanceof HttpExceptionInterface ? 'authorization_or_schema' : 'delivery_failed';
                DB::connection('tenant')->table('event_outbox')->where('id', $id)->update(['state' => $attempts >= 5 ? 'dead' : 'pending', 'attempts' => $attempts, 'available_at' => now()->addSeconds(min(900, 5 * (2 ** $attempts))), 'error_code' => $code]);
            }
        });
    }

    public function replay(string $id): void
    {
        abort_unless(app(TenantContext::class)->can('organization.manage'), 403);
        DB::connection('tenant')->transaction(function () use ($id) {
            $row = DB::connection('tenant')->table('event_outbox')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row && $row->state === 'dead', 409, 'Only dead-letter events can be replayed.');
            app(ModuleAccess::class)->require($row->module, true);
            app(CompanyScope::class)->verify($row->company_id);
            DB::connection('tenant')->table('event_outbox')->where('id', $id)->update(['state' => 'pending', 'attempts' => 0, 'available_at' => now(), 'error_code' => null]);
            app(Audit::class)->tenant('event.replayed', $id, ['company_id' => $row->company_id]);
        });
    }
}
