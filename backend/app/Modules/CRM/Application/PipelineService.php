<?php

namespace App\Modules\CRM\Application;

use App\Shared\MasterData\Mutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PipelineService
{
    /** @return list<\stdClass> */
    public function listing(string $company): array
    {
        app(CrmAccess::class)->company($company);
        $pipelines = DB::connection('tenant')->table('crm_pipelines')->where('company_id', $company)->orderBy('name')->get();
        $stages = DB::connection('tenant')->table('crm_stages')->where('company_id', $company)->orderBy('position')->get()->groupBy('pipeline_id');
        foreach ($pipelines as $pipeline) {
            $pipeline->stages = ($stages[$pipeline->id] ?? collect())->all();
        }

        return $pipelines->all();
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, array $input, ?string $id = null, ?string $key = null): string
    {
        app(CrmAccess::class)->privileged();
        $rules = ['name' => 'required|string|max:120', 'active' => 'required|boolean', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'];
        if (! $id) {
            $rules += ['stages' => 'required|array|min:1|max:12', 'stages.*.name' => 'required|string|max:80|distinct', 'stages.*.probability' => 'required|integer|min:0|max:100'];
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required'])->validate();
        }
        $data = Validator::make($input, $rules)->validate();

        return app(Mutation::class)->run('CRM', $company, $id ? 'crm.pipeline.update:'.$id : 'crm.pipeline.create', $data, $id ? null : $key, function () use ($company, $id, $data) {
            $row = $id ? DB::connection('tenant')->table('crm_pipelines')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($row !== null, 404);
                abort_unless($row->revision === $data['revision'], 409, 'Pipeline changed; reload.');
            }
            if (DB::connection('tenant')->table('crm_pipelines')->where('company_id', $company)->where('name', $data['name'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['name' => 'A pipeline with this name already exists.']);
            }
            $saved = $id ?? (string) Str::uuid();
            $values = ['name' => $data['name'], 'active' => $data['active'], 'revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
            if ($id) {
                DB::connection('tenant')->table('crm_pipelines')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('crm_pipelines')->insert($values + ['id' => $saved, 'company_id' => $company, 'created_at' => now()]);
                foreach ($data['stages'] as $index => $stage) {
                    DB::connection('tenant')->table('crm_stages')->insert(['id' => (string) Str::uuid(), 'pipeline_id' => $saved, 'company_id' => $company, 'name' => $stage['name'], 'probability' => $stage['probability'], 'position' => $index + 1, 'active' => true, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            app(CrmAccess::class)->event($company, 'pipeline', $saved, $id ? 'pipeline.updated' : 'pipeline.created');

            return $saved;
        });
    }

    /** @param array<string,mixed> $input */
    public function stage(string $company, string $pipeline, array $input, ?string $id = null, ?string $key = null): string
    {
        app(CrmAccess::class)->privileged();
        $data = Validator::make($input, ['name' => 'required|string|max:80', 'probability' => 'required|integer|min:0|max:100', 'active' => 'required|boolean', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required'])->validate();
        }

        return app(Mutation::class)->run('CRM', $company, $id ? 'crm.stage.update:'.$id : 'crm.stage.create:'.$pipeline, $data, $id ? null : $key, function () use ($company, $pipeline, $id, $data) {
            abort_unless(DB::connection('tenant')->table('crm_pipelines')->where('id', $pipeline)->where('company_id', $company)->where('active', true)->exists(), 404);
            $row = $id ? DB::connection('tenant')->table('crm_stages')->where('id', $id)->where('pipeline_id', $pipeline)->where('company_id', $company)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($row !== null, 404);
                abort_unless($row->revision === $data['revision'], 409, 'Stage changed; reload.');
            }
            if (DB::connection('tenant')->table('crm_stages')->where('pipeline_id', $pipeline)->where('name', $data['name'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Stage name is already used in this pipeline.']);
            }
            $saved = $id ?? (string) Str::uuid();
            $values = $data;
            unset($values['revision']);
            $values += ['revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
            if ($id) {
                DB::connection('tenant')->table('crm_stages')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('crm_stages')->insert($values + ['id' => $saved, 'company_id' => $company, 'pipeline_id' => $pipeline, 'position' => (DB::connection('tenant')->table('crm_stages')->where('pipeline_id', $pipeline)->max('position') ?? 0) + 1, 'created_at' => now()]);
            }
            app(CrmAccess::class)->event($company, 'stage', $saved, $id ? 'stage.updated' : 'stage.created');

            return $saved;
        });
    }

    public function target(string $company, string $pipeline, string $stage): \stdClass
    {
        $row = DB::connection('tenant')->table('crm_stages as s')->join('crm_pipelines as p', 'p.id', '=', 's.pipeline_id')->where('s.id',$stage)->where('s.pipeline_id',$pipeline)->where('s.company_id',$company)->where('s.active',true)->where('p.active',true)->select('s.*')->first();
        abort_unless($row !== null,422,'Choose an active stage in the selected company pipeline.');

        return $row;
    }
}
