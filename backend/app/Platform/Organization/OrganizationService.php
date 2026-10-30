<?php

namespace App\Platform\Organization;

use App\Shared\Audit\Audit;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OrganizationService
{
    public function __construct(private TenantContext $context, private Audit $audit) {}

    /** @return array{companies: array<int, \stdClass>, branches: array<int, \stdClass>, departments: array<int, \stdClass>} */
    public function snapshot(): array
    {
        $companies = $this->companies()->orderBy('name')->get();
        $branches = DB::connection('tenant')->table('branches')->whereIn('company_id', $companies->pluck('id'));
        $departments = DB::connection('tenant')->table('departments')->whereIn('company_id', $companies->pluck('id'));
        if (! $this->context->privileged()) {
            $branches->where('active', true)->whereIn('id', $this->context->branchIds());
            $departments->where('active', true)->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $this->context->branchIds()));
        }

        return ['companies' => $companies->all(), 'branches' => $branches->orderBy('name')->get()->all(), 'departments' => $departments->orderBy('name')->get()->all()];
    }

    public function companies(): Builder
    {
        $this->context->requireTenantId();
        $query = DB::connection('tenant')->table('companies');

        return $this->context->privileged() ? $query : $query->where('active', true)->whereIn('id', $this->context->companyIds());
    }

    /** @param array<string, mixed> $input */
    public function save(string $kind, array $input, ?string $id = null): string
    {
        abort_unless($this->context->can('organization.manage'), 403);
        abort_unless(in_array($kind, ['company', 'branch', 'department'], true), 404);
        $table = ['company' => 'companies', 'branch' => 'branches', 'department' => 'departments'][$kind];
        $rules = ['name' => 'required|string|max:120', 'active' => 'sometimes|boolean'];
        if (! $id) {
            $input['code'] = strtoupper(trim((string) ($input['code'] ?? '')));
            $unique = Rule::unique('tenant.'.$table, 'code');
            if ($kind !== 'company') {
                $unique->where('company_id', $input['company_id'] ?? null);
            }
            $rules['code'] = ['required', 'regex:/\A[A-Z][A-Z0-9_-]{0,19}\z/', $unique];
            if ($kind === 'company') {
                $rules['base_currency'] = ['required', 'regex:/\A[A-Z]{3}\z/'];
                $rules['timezone'] = ['required', Rule::in(\DateTimeZone::listIdentifiers())];
            } else {
                $rules['company_id'] = 'required|uuid';
                if ($kind === 'department') {
                    $rules['branch_id'] = 'nullable|uuid';
                }
            }
        }
        $data = Validator::make($input, $rules)->validate();

        return DB::connection('tenant')->transaction(function () use ($kind, $table, $id, $data) {
            if ($id) {
                $row = DB::connection('tenant')->table($table)->where('id', $id)->lockForUpdate()->first();
                abort_unless($row !== null, 404);
            } else {
                if ($kind !== 'company') {
                    abort_unless($this->companies()->where('id', $data['company_id'])->where('active', true)->exists(), 403);
                    if (($data['branch_id'] ?? null) !== null) {
                        abort_unless(DB::connection('tenant')->table('branches')->where('id', $data['branch_id'])->where('company_id', $data['company_id'])->where('active', true)->exists(), 403);
                    }
                }
                $id = (string) Str::uuid();
            }
            $values = $data + ['updated_at' => now()];
            if (isset($row)) {
                DB::connection('tenant')->table($table)->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table($table)->insert($values + ['id' => $id, 'created_at' => now()]);
            }
            $this->audit->tenant($kind.(isset($row) ? '.updated' : '.created'), $id, ['changed_fields' => array_keys($data), 'company_id' => $kind === 'company' ? $id : ($row->company_id ?? $data['company_id'])]);

            return $id;
        });
    }

    /**
     * @param  list<string>  $companies
     * @param  list<string>  $branches
     */
    public function validateGrants(array $companies, array $branches): void
    {
        abort_unless(DB::connection('tenant')->table('companies')->whereIn('id', $companies)->where('active', true)->count() === count(array_unique($companies)), 422, 'Invalid company grants.');
        abort_unless(DB::connection('tenant')->table('branches')->whereIn('id', $branches)->whereIn('company_id', $companies)->where('active', true)->count() === count(array_unique($branches)), 422, 'Invalid branch grants.');
    }

    public function verifyScope(string $companyId, ?string $branchId): void
    {
        abort_unless($this->companies()->where('id', $companyId)->where('active', true)->exists(), 403);
        if ($branchId) {
            abort_unless($this->context->permitsBranch($branchId) && DB::connection('tenant')->table('branches')->where('id', $branchId)->where('company_id', $companyId)->where('active', true)->exists(), 403);
        }
    }
}
