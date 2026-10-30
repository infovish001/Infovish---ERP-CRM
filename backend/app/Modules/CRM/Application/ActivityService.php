<?php

namespace App\Modules\CRM\Application;

use App\Shared\MasterData\Mutation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ActivityService
{
    /** @return list<\stdClass> */
    public function listing(string $company, string $kind, string $parent): array
    {
        app(CrmAccess::class)->record($kind, $company, $parent);

        return DB::connection('tenant')->table('crm_activities')->where('company_id', $company)->where($kind.'_id', $parent)
            ->orderByRaw("CASE WHEN status='planned' THEN 0 ELSE 1 END, CASE WHEN status='planned' THEN due_at END ASC, CASE WHEN status<>'planned' THEN updated_at END DESC")->limit(100)->get()->all();
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, string $kind, string $parent, array $input, ?string $id = null, ?string $key = null): string
    {
        $data = Validator::make($input, ['subject' => 'required|string|max:160', 'type' => 'required|in:call,meeting,task', 'note' => 'nullable|string|max:2000', 'due_at' => ['required', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})\z/'], 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required'])->validate();
        }

        return app(Mutation::class)->run('CRM', $company, $id ? 'crm.activity.update:'.$id : 'crm.activity.create:'.$kind.':'.$parent, $data, $id ? null : $key, function () use ($company, $kind, $parent, $id, $data) {
            $access = app(CrmAccess::class);
            $record = $access->record($kind, $company, $parent, true, true);
            abort_unless(in_array($record->status, ['new', 'qualified', 'open'], true), 409, 'Use an open parent record for activities.');
            $row = $id ? DB::connection('tenant')->table('crm_activities')->where('id', $id)->where('company_id', $company)->where($kind.'_id', $parent)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($row !== null, 404);
                abort_unless($row->status === 'planned' && $row->revision === $data['revision'], 409, 'Activity changed or is complete.');
            }
            $saved = $id ?? (string) Str::uuid();
            $values = $data;
            unset($values['revision']);
            $values['due_at'] = CarbonImmutable::parse($data['due_at'])->utc();
            $values += ['revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
            if ($id) {
                DB::connection('tenant')->table('crm_activities')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('crm_activities')->insert($values + ['id' => $saved, 'company_id' => $company, $kind.'_id' => $parent, 'status' => 'planned', 'created_at' => now()]);
            }
            $access->event($company, 'activity', $saved, $id ? 'activity.updated' : 'activity.created');

            return $saved;
        }, 'crm.manage');
    }

    /** @param array<string,mixed> $input */
    public function transition(string $company, string $id, array $input): string
    {
        $data = Validator::make($input, ['status' => 'required|in:done,cancelled', 'revision' => 'required|integer|min:1'])->validate();

        return app(Mutation::class)->run('CRM', $company, 'crm.activity.transition:'.$id, $data, null, function () use ($company, $id, $data) {
            $row = DB::connection('tenant')->table('crm_activities')->where('id', $id)->where('company_id', $company)->lockForUpdate()->first();
            abort_unless($row !== null, 404);
            app(CrmAccess::class)->record($row->lead_id ? 'lead' : 'opportunity', $company, $row->lead_id ?? $row->opportunity_id, true, true);
            abort_unless($row->status === 'planned' && $row->revision === $data['revision'], 409, 'Activity changed or is complete.');
            DB::connection('tenant')->table('crm_activities')->where('id', $id)->update(['status' => $data['status'], 'completed_at' => now(), 'revision' => $row->revision + 1, 'updated_at' => now()]);
            app(CrmAccess::class)->event($company, 'activity', $id, 'activity.'.$data['status']);

            return $id;
        }, 'crm.manage');
    }
}
