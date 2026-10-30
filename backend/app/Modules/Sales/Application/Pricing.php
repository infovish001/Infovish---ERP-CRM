<?php

namespace App\Modules\Sales\Application;

use App\Modules\Catalog\Domain\Contracts\Products;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class Pricing
{
    /**
     * @param  array<int,array<string,mixed>>  $input
     * @param  array<string,mixed>  $profile
     * @return array<string,mixed>
     */
    public function calculate(string $company, array $input, array $profile, string $date): array
    {
        $decimal = ['required', 'string', 'regex:/\A(?:0|[1-9]\d{0,13})(?:\.\d{1,6})?\z/'];
        $data = Validator::make(['lines' => $input], ['lines' => 'required|array|min:1|max:50', 'lines.*' => 'array:variant_id,quantity,unit_price,discount_percent,tax_code,tax_basis',
            'lines.*.variant_id' => 'required|uuid', 'lines.*.quantity' => $decimal, 'lines.*.unit_price' => $decimal,
            'lines.*.discount_percent' => ['required', 'string', 'regex:/\A(?:0|[1-9]\d{0,2})(?:\.\d{1,6})?\z/'],
            'lines.*.tax_code' => 'required|in:standard,zero,exempt,unregistered', 'lines.*.tax_basis' => 'nullable|string|max:300'])->validate();
        $lines = [];
        $net = '0.00';
        $standard = '0.00';
        foreach ($data['lines'] as $index => $line) {
            if (bccomp($line['quantity'], '0', 6) <= 0 || bccomp($line['discount_percent'], '100', 6) > 0) {
                throw ValidationException::withMessages(['lines.'.$index => 'Quantity must be positive and discount between 0 and 100.']);
            }
            $registered = $profile['vat_registered'];
            if ((! $registered && $line['tax_code'] !== 'unregistered') || ($registered && $line['tax_code'] === 'unregistered')) {
                throw ValidationException::withMessages(['lines.'.$index.'.tax_code' => 'Tax treatment conflicts with declared seller VAT registration.']);
            }
            if ($registered && $date < $profile['vat_effective_from']) {
                throw ValidationException::withMessages(['issue_date' => 'Date precedes seller VAT registration.']);
            }
            if ($line['tax_code'] === 'standard' && $date < '2024-01-01') {
                throw ValidationException::withMessages(['issue_date' => 'VAT18 is supported from 2024-01-01.']);
            }
            if (in_array($line['tax_code'], ['zero', 'exempt'], true) && trim($line['tax_basis'] ?? '') === '') {
                throw ValidationException::withMessages(['lines.'.$index.'.tax_basis' => 'Provide the legal basis for this treatment.']);
            }
            $product = app(Products::class)->find($company, $line['variant_id']);
            abort_unless($product->currency === 'LKR', 422, 'Only LKR products are supported.');
            $gross = bcmul($line['quantity'], $line['unit_price'], 12);
            $amount = bcround(bcmul($gross, bcsub('1', bcdiv($line['discount_percent'], '100', 8), 8), 20), 2, \RoundingMode::HalfAwayFromZero);
            $net = bcadd($net, $amount, 2);
            if ($line['tax_code'] === 'standard') {
                $standard = bcadd($standard, $amount, 2);
            }
            $lines[] = ['variant_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'unit' => $product->unit, 'type' => $product->type,
                'quantity' => bcadd($line['quantity'], '0', 6), 'unit_price' => bcadd($line['unit_price'], '0', 6), 'discount_percent' => bcadd($line['discount_percent'], '0', 6),
                'tax_code' => $line['tax_code'], 'tax_basis' => $line['tax_basis'] ?? null, 'vat_rate' => $line['tax_code'] === 'standard' ? '18.000000' : '0.000000', 'net' => $amount];
        }
        $vat = bcround(bcmul($standard, '0.18', 4), 2, \RoundingMode::HalfAwayFromZero);
        $total = bcadd($net, $vat, 2);
        if (bccomp($total, '9999999999999999999999.99', 2) > 0) {
            throw ValidationException::withMessages(['lines' => 'Document total exceeds the supported range.']);
        }

        return ['lines' => $lines, 'net' => $net, 'standard_base' => $standard, 'vat' => $vat, 'total' => $total, 'rule_version' => TaxProfile::RULE, 'rounding' => 'net-line cents half-up; document standard-base VAT cents half-up'];
    }
}
