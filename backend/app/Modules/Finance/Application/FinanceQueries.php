<?php

namespace App\Modules\Finance\Application;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class FinanceQueries
{
    /** @return LengthAwarePaginator<int,\stdClass> */
    public function listing(string $company, string $kind, string $search, int $page): LengthAwarePaginator
    {
        app(FinanceAccess::class)->company($company);
        Validator::make(['q' => $search, 'page' => $page], ['q' => 'string|max:100', 'page' => 'integer|min:1|max:100000'])->validate();
        $table = $this->table($kind);
        $q = DB::connection('tenant')->table($table)->where('company_id', $company);
        if (in_array($kind, ['invoices', 'bills'], true)) {
            $q->where('kind', $kind === 'invoices' ? 'invoice' : 'bill');
        }
        if ($search !== '') {
            $q->where('number', 'ilike', '%'.$search.'%');
        }$order = $kind === 'statements' ? 'ends_on' : 'date';
        $result = $q->orderByDesc($order)->orderBy('id')->paginate(25, page: $page);
        foreach ($result->items() as $r) {
            $this->decode($r);
            if (in_array($kind, ['invoices', 'bills'], true)) {
                $r->outstanding = $r->state === 'posted' ? app(FinanceDocuments::class)->outstanding($company, $r->id) : null;
            }
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function detail(string $company, string $kind, string $id): array
    {
        $r = app(FinanceAccess::class)->record($this->table($kind), $company, $id);
        if (in_array($kind, ['invoices', 'bills'], true)) {
            abort_unless($r->kind === ($kind === 'invoices' ? 'invoice' : 'bill'), 404);
        }$this->decode($r);
        $extra = [];
        if ($kind === 'journals') {
            $extra['lines'] = DB::connection('tenant')->table('finance_lines')->where('company_id', $company)->where('journal_id', $id)->get()->all();
            $extra['reversal_id'] = DB::connection('tenant')->table('finance_journals')->where('company_id', $company)->where('reversal_of', $id)->value('id');
        }
        if (in_array($kind, ['invoices', 'bills'], true)) {
            $extra['outstanding'] = $r->state === 'posted' ? app(FinanceDocuments::class)->outstanding($company, $id) : null;
            $extra['payments'] = DB::connection('tenant')->table('finance_payments as p')->leftJoin('finance_payment_voids as v', 'v.payment_id', '=', 'p.id')->where('p.company_id', $company)->where('p.document_id', $id)->orderBy('p.date')->get(['p.*', 'v.id as void_id'])->all();
            $extra['prints'] = DB::connection('tenant')->table('finance_prints')->where('company_id', $company)->where('document_id', $id)->orderByDesc('created_at')->limit(100)->get(['id', 'kind', 'reason', 'created_at', 'sha256'])->all();
        }
        if ($kind === 'payments') {
            $extra['void'] = DB::connection('tenant')->table('finance_payment_voids')->where('company_id', $company)->where('payment_id', $id)->first();
        }

        return ['record' => $r, ...$extra];
    }

    private function table(string $kind): string
    {
        return match ($kind) {
            'drafts' => 'finance_drafts','journals' => 'finance_journals','invoices','bills' => 'finance_documents','payments' => 'finance_payments','statements' => 'finance_statements',default => throw new \InvalidArgumentException('Unsupported Finance resource.')
        };
    }

    private function decode(\stdClass $r): void
    {
        unset($r->posting_xid);
        foreach (['snapshot', 'lines', 'summary'] as $field) {
            if (isset($r->$field)) {
                $r->$field = json_decode($r->$field, true, flags: JSON_THROW_ON_ERROR);
            }
        }
    }
}
