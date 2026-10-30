<?php

namespace App\Modules\CRM\Application;

use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Parties\Domain\Contracts\NewCustomer;
use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LeadService
{
    /** @param array<string,mixed> $input */
    public function save(string $company, array $input, ?string $id = null, ?string $key = null): string
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = strtolower(trim($input['email']));
        }
        $data = Validator::make($input, ['title' => 'required|string|max:160', 'contact_name' => 'required|string|max:160', 'email' => 'nullable|email|max:254', 'phone' => ['nullable', 'string', 'max:40', 'regex:/\A[0-9+(). x-]+\z/'], 'source' => 'nullable|string|max:80', 'assigned_to' => 'nullable|uuid', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required'])->validate();
        }

        return app(Mutation::class)->run('CRM', $company, $id ? 'crm.lead.update:'.$id : 'crm.lead.create', $data, $id ? null : $key, function () use ($company, $id, $data) {
            $access = app(CrmAccess::class);
            $row = $id ? $access->record('lead', $company, $id, true, true) : null;
            if ($id) {
                abort_unless(in_array($row->status, ['new', 'qualified'], true), 409, 'Closed or converted lead is immutable.');
                abort_unless($row->revision === $data['revision'], 409, 'Lead changed; reload.');
            }
            if (! empty($data['email']) && DB::connection('tenant')->table('crm_leads')->where('company_id', $company)->where('email', $data['email'])->whereIn('status', ['new', 'qualified'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['email' => 'An active lead already uses this email. Ask your CRM administrator to review duplicates.']);
            }
            $values = $data;
            unset($values['revision']);
            $values['assigned_to'] = $access->assignee($company, $data['assigned_to'] ?? $row->assigned_to ?? null);
            $values += ['revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
            $saved = $id ?? (string) Str::uuid();
            if ($id) {
                DB::connection('tenant')->table('crm_leads')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('crm_leads')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'LED'), 'status' => 'new', 'created_at' => now()]);
            }
            $access->event($company, 'lead', $saved, $id ? 'lead.updated' : 'lead.created');

            return $saved;
        }, 'crm.manage');
    }

    /** @param array<string,mixed> $input */
    public function transition(string $company, string $id, array $input): string
    {
        $data = Validator::make($input, ['action' => 'required|in:qualify,lost,reopen', 'reason' => 'nullable|string|max:500', 'revision' => 'required|integer|min:1'])->validate();
        if ($data['action'] !== 'qualify') {
            Validator::make($data, ['reason' => 'required|string|max:500'])->validate();
        }

        return app(Mutation::class)->run('CRM', $company, 'crm.lead.transition:'.$id, $data, null, function () use ($company, $id, $data) {
            $access = app(CrmAccess::class);
            $row = $access->record('lead', $company, $id, true, true);
            abort_unless($row->revision === $data['revision'], 409, 'Lead changed; reload.');
            $status = match ($data['action']) {
                'qualify' => 'qualified','lost' => 'lost','reopen' => 'new',default => throw new \InvalidArgumentException('Unsupported lead action.')
            };
            if ($data['action'] === 'reopen') {
                $access->privileged();
                abort_unless($row->status === 'lost', 409);
            } elseif ($data['action'] === 'qualify') {
                abort_unless($row->status === 'new', 409);
            } else {
                abort_unless(in_array($row->status, ['new', 'qualified'], true), 409);
            }
            if ($status !== 'lost' && $row->email && DB::connection('tenant')->table('crm_leads')->where('company_id', $company)->where('email', $row->email)->whereIn('status', ['new', 'qualified'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages(['email' => 'An active duplicate must be resolved before reopening.']);
            }
            DB::connection('tenant')->table('crm_leads')->where('id', $id)->update(['status' => $status, 'reason' => $data['reason'] ?? null, 'revision' => $row->revision + 1, 'updated_at' => now()]);
            $access->event($company, 'lead', $id, 'lead.'.$data['action'], $data['reason'] ?? null);

            return $id;
        }, 'crm.manage');
    }

    /** @param array<string,mixed> $input */
    public function convert(string $company, string $id, array $input, ?string $key): string
    {
        app(CrmAccess::class)->privileged();
        $data = Validator::make($input, ['revision' => 'required|integer|min:1', 'customer_mode' => 'required|in:new,existing', 'contact_id' => 'nullable|required_if:customer_mode,existing|uuid', 'pipeline_id' => 'required|uuid', 'stage_id' => 'required|uuid', 'expected_amount' => ['nullable', 'string', 'regex:/\A(?:0|[1-9]\d{0,13})(?:\.\d{1,6})?\z/'], 'expected_close' => 'nullable|date_format:Y-m-d'])->validate();
        Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required'])->validate();

        return app(Mutation::class)->run('CRM', $company, 'crm.lead.convert:'.$id, $data, $key, function () use ($company, $id, $data) {
            $access = app(CrmAccess::class);
            $row = $access->record('lead', $company, $id, true, true);
            $intent = ['customer_mode' => $data['customer_mode'], 'contact_id' => $data['customer_mode'] === 'existing' ? $data['contact_id'] : null, 'pipeline_id' => $data['pipeline_id'], 'stage_id' => $data['stage_id'], 'expected_amount' => $data['expected_amount'] ?? null, 'expected_close' => $data['expected_close'] ?? null];
            $hash = hash('sha256', json_encode($intent, JSON_THROW_ON_ERROR));
            if ($row->status === 'converted') {
                abort_unless(hash_equals($row->conversion_hash, $hash), 409, 'Lead was converted with a different intent.');

                return $row->opportunity_id;
            }
            abort_unless($row->status === 'qualified' && $row->revision === $data['revision'], 409, 'Convert the latest revision of a qualified lead.');
            app(PipelineService::class)->target($company, $data['pipeline_id'], $data['stage_id']);
            $contacts = app(Contacts::class);
            if ($data['customer_mode'] === 'new') {
                if ($row->email && $contacts->findByEmail($company, $row->email)) {
                    throw ValidationException::withMessages(['contact_id' => 'A contact already uses this email. Explicitly select an active customer, or review it in Contacts.']);
                }
                $contact = $contacts->createCustomer($company, new NewCustomer($row->contact_name, $row->email, $row->phone), 'crm-convert-'.$id);
            } else {
                $contact = $contacts->find($company, $data['contact_id']);
                abort_unless($contact->customer, 422, 'Choose an active customer.');
            }
            $opportunity = app(OpportunityService::class)->save($company, ['title' => $row->title, 'contact_id' => $contact->id, 'pipeline_id' => $data['pipeline_id'], 'stage_id' => $data['stage_id'], 'expected_amount' => $data['expected_amount'] ?? null, 'expected_close' => $data['expected_close'] ?? null, 'assigned_to' => $row->assigned_to], key: 'crm-convert-'.$id, leadId: $id);
            DB::connection('tenant')->table('crm_leads')->where('id', $id)->update(['status' => 'converted', 'contact_id' => $contact->id, 'opportunity_id' => $opportunity, 'conversion_hash' => $hash, 'revision' => $row->revision + 1, 'updated_at' => now()]);
            $access->event($company, 'lead', $id, 'lead.converted');

            return $opportunity;
        });
    }
}
