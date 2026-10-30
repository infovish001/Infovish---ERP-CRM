<?php

namespace App\Modules\Sales\Application;

use App\Shared\Audit\Audit;
use App\Shared\MasterData\Mutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class TaxProfile
{
    public const RULE = 'LK-VAT-2026-10-30';

    /** @return array<string,mixed>|null */
    public function get(string $company): ?array
    {
        app(SalesAccess::class)->company($company);
        $row = DB::connection('tenant')->table('sales_tax_profiles')->where('company_id', $company)->first();

        return $row ? ['revision' => $row->revision, ...json_decode($row->settings, true, flags: JSON_THROW_ON_ERROR)] : null;
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, array $input): string
    {
        $data = Validator::make($input, [
            'country' => 'required|in:LK', 'seller_name' => 'required|string|max:160', 'seller_address' => 'required|string|max:500',
            'vat_registered' => 'required|boolean', 'tin' => ['nullable', 'string', 'regex:/\A[0-9]{9}\z/'],
            'vat_registration' => ['nullable', 'string', 'max:30', 'regex:/\A[0-9-]+\z/'],
            'vat_effective_from' => 'nullable|date_format:Y-m-d', 'revision' => 'required|integer|min:0',
        ])->validate();
        if ($data['vat_registered']) {
            Validator::make($data, ['tin' => 'required', 'vat_registration' => 'required', 'vat_effective_from' => 'required'])->validate();
        }

        return app(Mutation::class)->run('Sales', $company, 'sales.profile.update', $data, null, function () use ($company, $data) {
            abort_unless(DB::connection('tenant')->table('companies')->where('id', $company)->value('base_currency') === 'LKR', 422, 'Sri Lanka Sales requires LKR base currency.');
            $old = $this->get($company);
            abort_unless(($old['revision'] ?? 0) === $data['revision'], 409, 'Tax profile changed; reload.');
            $revision = $data['revision'] + 1;
            unset($data['revision']);
            if (! $data['vat_registered']) {
                $data['tin'] = null;
                $data['vat_registration'] = null;
                $data['vat_effective_from'] = null;
            }
            $values = ['settings' => json_encode($data, JSON_THROW_ON_ERROR), 'revision' => $revision, 'updated_at' => now()];
            if ($old) {
                DB::connection('tenant')->table('sales_tax_profiles')->where('company_id', $company)->update($values);
            } else {
                DB::connection('tenant')->table('sales_tax_profiles')->insert($values + ['company_id' => $company, 'created_at' => now()]);
            }
            app(Audit::class)->tenant('sales.profile.updated', $company, ['company_id' => $company]);

            return $company;
        });
    }
}
