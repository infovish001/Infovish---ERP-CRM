<?php

namespace App\Modules\Finance\Application;

use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Sales\Domain\Contracts\Orders;
use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class FinanceDocuments
{
    /** @param array<string,mixed> $input */
    public function save(string $company, string $kind, array $input, ?string $id, ?string $key): string
    {
        abort_unless(in_array($kind, ['invoice', 'bill'], true), 404);
        $rules = ['date' => 'required|date_format:Y-m-d|before_or_equal:'.FinanceAccess::today(), 'due_date' => 'required|date_format:Y-m-d|after_or_equal:date', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'];
        if ($kind === 'invoice') {
            $rules += ['order_id' => 'required|uuid', 'tax_invoice' => 'required|boolean', 'buyer_name' => 'required|string|max:160', 'buyer_address' => 'required|string|max:500', 'buyer_tin' => ['nullable', 'string', 'regex:/\A[0-9]{9}\z/'], 'supply_date' => 'required|date_format:Y-m-d|before_or_equal:date', 'request_evidence' => 'nullable|string|max:500'];
        } else {
            $rules += ['party_id' => 'required|uuid', 'supplier_reference' => 'required|string|max:100', 'description' => 'required|string|max:500', 'net' => FinanceAccess::moneyRule(), 'vat' => FinanceAccess::moneyRule(), 'vat_evidence' => 'nullable|string|max:500'];
        }
        $data = Validator::make($input, $rules)->validate();
        if (! $id) {
            Validator::make(['key' => $key], ['key' => 'required'])->validate();
        }

        return app(Mutation::class)->run('Finance', $company, $id ? 'finance.document.update:'.$id : 'finance.document.create:'.$kind, $data, $id ? null : $key, function () use ($company, $kind, $data, $id) {
            app(FinanceAccess::class)->company($company, true);
            $settings = app(FinanceSetup::class)->settings($company);
            abort_unless($settings !== null, 422, 'Configure Finance mappings and seller details first.');
            $old = $id ? app(FinanceAccess::class)->record('finance_documents', $company, $id, true) : null;
            if ($old) {
                abort_unless($old->state === 'draft' && $old->kind === $kind && $old->revision === $data['revision'], 409, 'Document changed or is posted.');
            }
            if ($kind === 'invoice') {
                $billing = app(Orders::class)->billing($company, $data['order_id']);
                $order = $billing->order;
                $source = $billing->snapshot;
                abort_if(DB::connection('tenant')->table('finance_documents')->where('company_id', $company)->where('order_id', $order->id)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 409, 'This order already has an invoice draft or posted invoice.');
                $party = app(Contacts::class)->find($company, $order->customerId);
                abort_unless($party->customer && $order->currency === 'LKR', 422);
                foreach (['seller_name', 'seller_address', 'vat_registered', 'tin', 'vat_registration', 'vat_effective_from'] as $field) {
                    abort_unless(($source['seller'][$field] ?? null) === ($settings[$field] ?? null), 409, 'Seller registration differs from the confirmed order; review the source.');
                }
                $codes = array_unique(array_column($source['pricing']['lines'], 'tax_code'));
                if ($data['tax_invoice']) {
                    Validator::make($data, ['buyer_tin' => 'required', 'request_evidence' => 'required'])->validate();
                    abort_unless($settings['vat_registered'] && count($codes) === 1 && reset($codes) === 'standard', 422, 'Tax invoices support ordinary standard-rated supplies only.');
                    abort_unless($data['date'] >= '2026-10-01' && $data['date'] >= $settings['vat_effective_from'] && $data['supply_date'] >= $settings['vat_effective_from'] && CarbonImmutable::parse($data['supply_date'])->diffInDays(CarbonImmutable::parse($data['date'])) <= 28, 422, 'Tax invoice date is outside the supported supply/registration window.');
                } else {
                    abort_unless(bccomp($order->vat, '0', 2) === 0, 422, 'Positive VAT requires the supported registered-purchaser tax-invoice workflow.');
                }
                $net = $order->net;
                $vat = $order->vat;
                $total = $order->total;
                $partyId = $party->id;
                $snapshot = ['seller' => $settings, 'party' => ['id' => $party->id, 'name' => $data['buyer_name'], 'address' => $data['buyer_address'], 'tin' => $data['buyer_tin'] ?? null], 'source' => $source, 'tax_invoice' => $data['tax_invoice'], 'supply_date' => $data['supply_date'], 'request_evidence' => $data['request_evidence'] ?? null, 'order_number' => $order->number, 'rule_version' => 'LK-INVOICE-2026-10-31'];
            } else {
                $party = app(Contacts::class)->find($company, $data['party_id']);
                abort_unless($party->supplier, 422, 'Choose an active supplier.');
                abort_if(DB::connection('tenant')->table('finance_documents')->where('company_id', $company)->where('kind', 'bill')->where('party_id', $party->id)->where('supplier_reference', $data['supplier_reference'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 409, 'Supplier document reference already recorded.');
                $net = FinanceAccess::money($data['net']);
                $vat = FinanceAccess::money($data['vat']);
                $total = bcadd($net, $vat, 2);
                $partyId = $party->id;
                if (bccomp($vat, '0', 2) > 0) {
                    abort_unless($settings['vat_registered'] && $data['date'] >= $settings['vat_effective_from'], 422, 'Recoverable VAT requires effective seller registration.');
                    Validator::make($data, ['vat_evidence' => 'required|string|max:500'])->validate();
                }
                $snapshot = ['party' => ['id' => $party->id, 'name' => $party->name], 'description' => $data['description'], 'vat_evidence' => $data['vat_evidence'] ?? null, 'seller' => $settings];
            }
            abort_unless(bccomp($total, '0', 2) > 0 && bccomp($total, '9999999999999999999999.99', 2) <= 0, 422, 'Document total is outside the supported range.');
            $saved = $id ?? (string) Str::uuid();
            $values = ['kind' => $kind, 'party_id' => $partyId, 'order_id' => $kind === 'invoice' ? $data['order_id'] : null, 'supplier_reference' => $data['supplier_reference'] ?? null, 'date' => $data['date'], 'due_date' => $data['due_date'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'net' => $net, 'vat' => $vat, 'total' => $total, 'revision' => ($old->revision ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::connection('tenant')->table('finance_documents')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('finance_documents')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'FDOC'), 'state' => 'draft', 'created_at' => now()]);
            }app(FinanceLedger::class)->event($company, 'document.saved', $saved);

            return $saved;
        });
    }

    public function post(string $company, string $id, int $revision, ?string $key): string
    {
        Validator::make(['key' => $key, 'revision' => $revision], ['key' => 'required', 'revision' => 'integer|min:1'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.document.post:'.$id, ['revision' => $revision], $key, function () use ($company, $id, $revision) {
            $row = app(FinanceAccess::class)->record('finance_documents', $company, $id, true);
            if ($row->state === 'posted') {
                abort_unless($row->revision === $revision + 1, 409);

                return $row->id;
            }abort_unless($row->state === 'draft' && $row->revision === $revision, 409, 'Document changed or is terminal.');
            $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $settings = app(FinanceSetup::class)->settings($company);
            abort_unless($settings !== null && $settings['revision'] === $snapshot['seller']['revision'], 409, 'Finance settings changed; revise the draft.');
            $party = app(Contacts::class)->find($company, $row->party_id);
            abort_unless($row->kind === 'invoice' ? $party->customer : $party->supplier, 422, 'Party is unavailable.');
            $lines = [];
            $debit = $row->kind === 'invoice';
            $control = $settings[$debit ? 'receivable' : 'payable'];
            $main = $settings[$debit ? 'revenue' : 'expense'];
            $tax = $settings[$debit ? 'vat_output' : 'vat_input'];
            $lines[] = ['account_id' => $control, 'debit' => $debit ? $row->total : '0', 'credit' => $debit ? '0' : $row->total];
            if (bccomp($row->net, '0', 2) > 0) {
                $lines[] = ['account_id' => $main, 'debit' => $debit ? '0' : $row->net, 'credit' => $debit ? $row->net : '0'];
            }
            if (bccomp($row->vat, '0', 2) > 0) {
                $lines[] = ['account_id' => $tax, 'debit' => $debit ? '0' : $row->vat, 'credit' => $debit ? $row->vat : '0'];
            }
            if ($debit) {
                abort_unless($row->date === FinanceAccess::today(), 422, 'Issue customer invoices using today’s actual date.');
                $billing = app(Orders::class)->billing($company, $row->order_id);
                abort_unless($billing->order->total === $row->total && $billing->order->vat === $row->vat, 409, 'Source order differs.');
                if ($snapshot['tax_invoice']) {
                    abort_unless(CarbonImmutable::parse($snapshot['supply_date'])->diffInDays(CarbonImmutable::parse($row->date)) <= 28, 422, 'Supply window expired.');
                }
            }
            $number = $row->kind === 'bill' ? app(Numbering::class)->next($company, 'BILL') : ($snapshot['tax_invoice'] ? CarbonImmutable::parse($row->date)->format('y').strtoupper(CarbonImmutable::parse($row->date)->format('M')).'_'.$settings['issuer_code'].'_'.substr(app(Numbering::class)->next($company, 'TAX'), 4) : app(Numbering::class)->next($company, 'INV'));
            $journal = app(FinanceLedger::class)->post($company, $row->date, $number, $lines, $row->kind, $row->id);
            DB::connection('tenant')->table('finance_documents')->where('id', $id)->update(['state' => 'posted', 'number' => $number, 'journal_id' => $journal, 'revision' => $row->revision + 1, 'updated_at' => now()]);
            app(FinanceLedger::class)->event($company, 'document.posted', $id);

            return $id;
        });
    }

    public function cancel(string $company, string $id, int $revision, string $reason): string
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|max:500'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.document.cancel:'.$id, ['revision' => $revision, 'reason' => $reason], null, function () use ($company, $id, $revision, $reason) {
            $r = app(FinanceAccess::class)->record('finance_documents', $company, $id, true);
            abort_unless($r->state === 'draft' && $r->revision === $revision, 409, 'Only current drafts can cancel.');
            $snapshot = json_decode($r->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $snapshot['cancellation_reason'] = $reason;
            DB::connection('tenant')->table('finance_documents')->where('id', $id)->update(['state' => 'cancelled', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'revision' => $r->revision + 1, 'updated_at' => now()]);
            app(FinanceLedger::class)->event($company, 'document.cancelled', $id);

            return $id;
        });
    }

    public function outstanding(string $company, string $id): string
    {
        $r = app(FinanceAccess::class)->record('finance_documents', $company, $id);
        $paid = DB::connection('tenant')->table('finance_payments as p')->where('p.company_id', $company)->where('p.document_id', $id)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('finance_payment_voids as v')->whereColumn('v.payment_id', 'p.id'))->selectRaw('coalesce(sum(amount),0)::text as total')->first();

        return bcsub($r->total, $paid->total, 2);
    }
}
