<?php

namespace App\Modules\CRM\Application;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CrmQueries
{
    /**
     * @param  array<string,mixed>  $input
     * @return LengthAwarePaginator<int,\stdClass>
     */
    public function listing(string $kind, string $company, array $input): LengthAwarePaginator
    {
        $data = Validator::make($input, ['q' => 'nullable|string|max:100', 'page' => 'sometimes|integer|min:1|max:100000', 'status' => ['nullable', Rule::in($kind === 'lead' ? ['new', 'qualified', 'lost', 'converted'] : ['open', 'won', 'lost'])], 'pipeline_id' => 'nullable|uuid', 'stage_id' => 'nullable|uuid'])->validate();
        $query = app(CrmAccess::class)->records($kind, $company);
        if (! empty($data['q'])) {
            $query->where(function ($q) use ($kind, $data) {
                $q->where('title', 'ilike', '%'.$data['q'].'%')->orWhere('number', 'ilike', '%'.$data['q'].'%');
                if ($kind === 'lead') {
                    $q->orWhere('contact_name', 'ilike', '%'.$data['q'].'%')->orWhere('email', 'ilike', '%'.$data['q'].'%');
                }
            });
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if ($kind === 'opportunity') {
            foreach (['pipeline_id', 'stage_id'] as $key) {
                if (! empty($data[$key])) {
                    $query->where($key, $data[$key]);
                }
            }
        }

        $page = $query->orderByDesc('updated_at')->orderBy('id')->paginate(25, page: $data['page'] ?? 1);
        foreach ($page->items() as $row) {
            unset($row->conversion_hash);
        }

        return $page;
    }

    /** @return list<\stdClass> */
    public function history(string $kind, string $company, string $id): array
    {
        app(CrmAccess::class)->record($kind, $company, $id);

        return DB::connection('tenant')->table('crm_history')->where('company_id', $company)->where('resource_id', $id)->where('kind', $kind)->orderByDesc('occurred_at')->limit(100)->get()->all();
    }

    /** @return array<string,mixed> */
    public function forecast(string $company, ?string $pipeline = null): array
    {
        if ($pipeline) {
            Validator::make(['pipeline_id' => $pipeline], ['pipeline_id' => 'uuid'])->validate();
        }
        $query = app(CrmAccess::class)->records('opportunity', $company)->where('status', 'open');
        if ($pipeline) {
            $query->where('pipeline_id', $pipeline);
        }
        $authorized = $query->select('crm_opportunities.*');
        $summary = DB::connection('tenant')->query()->fromSub($authorized, 'o')->join('crm_stages as s', 's.id', '=', 'o.stage_id')
            ->selectRaw('count(*) as open_count, count(expected_amount) as known_count, count(*)-count(expected_amount) as unknown_count, COALESCE(sum(expected_amount),0)::numeric(30,6)::text as unweighted, COALESCE(sum(round(expected_amount*s.probability::numeric/100,6)),0)::numeric(30,6)::text as weighted')->first();

        return ['open_count' => (int) $summary->open_count, 'known_count' => (int) $summary->known_count, 'unknown_count' => (int) $summary->unknown_count, 'unweighted' => $summary->unweighted, 'weighted' => $summary->weighted, 'currency' => DB::connection('tenant')->table('companies')->where('id', $company)->value('base_currency'), 'label' => 'Estimates from open opportunities; not recognized revenue.'];
    }
}
