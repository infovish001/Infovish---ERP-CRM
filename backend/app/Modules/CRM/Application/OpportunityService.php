<?php

namespace App\Modules\CRM\Application;

use App\Modules\CRM\Domain\Contracts\Opportunities;
use App\Modules\CRM\Domain\Contracts\OpportunityData;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Shared\MasterData\CompanyScope;
use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class OpportunityService implements Opportunities
{
    public function find(string $companyId, string $id): OpportunityData
    {
        $row = app(CrmAccess::class)->record('opportunity', $companyId, $id);

        return new OpportunityData($id, $companyId, $row->number, $row->contact_id, $row->title, $row->status, $row->expected_amount, $row->currency);
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, array $input, ?string $id = null, ?string $key = null, ?string $leadId = null): string
    {
        $rules = ['title' => 'required|string|max:160', 'assigned_to' => 'nullable|uuid', 'expected_amount' => ['nullable', 'string', 'regex:/\A(?:0|[1-9]\d{0,13})(?:\.\d{1,6})?\z/'], 'expected_close' => 'nullable|date_format:Y-m-d', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'];
        if (! $id) {
            $rules += ['contact_id' => 'required|uuid', 'pipeline_id' => 'required|uuid', 'stage_id' => 'required|uuid'];
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required'])->validate();
        }
        $data = Validator::make($input, $rules)->validate();

        return app(Mutation::class)->run('CRM', $company, $id ? 'crm.opportunity.update:'.$id : 'crm.opportunity.create', $data + ['lead_id' => $leadId], $id ? null : $key, function () use ($company, $id, $data, $leadId) {
            $access = app(CrmAccess::class);
            $row = $id ? $access->record('opportunity', $company, $id, true, true) : null;
            if ($id) {
                abort_unless($row->status === 'open', 409, 'Reopen a closed opportunity before editing.');
                abort_unless($row->revision === $data['revision'], 409, 'Opportunity changed; reload.');
            }
            $assigned = $access->assignee($company, $data['assigned_to'] ?? $row->assigned_to ?? null);
            $values = ['title' => $data['title'], 'assigned_to' => $assigned, 'expected_amount' => $data['expected_amount'] ?? null, 'expected_close' => $data['expected_close'] ?? null, 'revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
            $saved = $id ?? (string) Str::uuid();
            if ($id) {
                DB::connection('tenant')->table('crm_opportunities')->where('id', $id)->update($values);
            } else {
                abort_unless(app(Contacts::class)->find($company, $data['contact_id'])->customer, 422, 'Select an active customer.');
                app(PipelineService::class)->target($company, $data['pipeline_id'], $data['stage_id']);
                DB::connection('tenant')->table('crm_opportunities')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'OPP'), 'contact_id' => $data['contact_id'], 'lead_id' => $leadId, 'pipeline_id' => $data['pipeline_id'], 'stage_id' => $data['stage_id'], 'status' => 'open', 'currency' => app(CompanyScope::class)->verify($company)->base_currency, 'created_at' => now()]);
            }
            $access->event($company, 'opportunity', $saved, $id ? 'opportunity.updated' : 'opportunity.created', to: $data['stage_id'] ?? $row->stage_id);

            return $saved;
        }, 'crm.manage');
    }

    /** @param array<string,mixed> $input */
    public function transition(string $company, string $id, array $input): string
    {
        $data = Validator::make($input, ['action' => 'required|in:move,won,lost,reopen', 'stage_id' => 'nullable|uuid', 'reason' => 'nullable|string|max:500', 'revision' => 'required|integer|min:1'])->validate();
        if (in_array($data['action'], ['lost', 'reopen'], true)) {
            Validator::make($data, ['reason' => 'required|string|max:500'])->validate();
        }

        return app(Mutation::class)->run('CRM', $company, 'crm.opportunity.transition:'.$id, $data, null, function () use ($company, $id, $data) {
            $access = app(CrmAccess::class);
            $row = $access->record('opportunity', $company, $id, true, true);
            abort_unless($row->revision === $data['revision'], 409, 'Opportunity changed; reload.');
            if ($data['action'] === 'reopen') {
                $access->privileged();
                abort_unless($row->status !== 'open', 409);
            } else {
                abort_unless($row->status === 'open', 409, 'Closed opportunity is immutable.');
            }
            $stage = $row->stage_id;
            if (in_array($data['action'], ['move', 'reopen'], true)) {
                Validator::make($data, ['stage_id' => 'required|uuid'])->validate();
                $stage = app(PipelineService::class)->target($company, $row->pipeline_id, $data['stage_id'])->id;
            }
            DB::connection('tenant')->table('crm_opportunities')->where('id', $id)->update(['stage_id' => $stage, 'status' => in_array($data['action'], ['won', 'lost'], true) ? $data['action'] : 'open', 'reason' => $data['reason'] ?? null, 'revision' => $row->revision + 1, 'updated_at' => now()]);
            $access->event($company, 'opportunity', $id, 'opportunity.'.$data['action'], $data['reason'] ?? null, $row->stage_id, $stage);

            return $id;
        }, 'crm.manage');
    }
}
