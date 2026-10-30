<?php

namespace App\Modules\Finance\Application;

use App\Modules\Finance\Domain\Contracts\BalanceData;
use App\Modules\Finance\Domain\Contracts\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class FinanceReports implements Ledger
{
    public function balance(string $companyId, string $accountId, string $asOf): BalanceData
    {
        app(FinanceAccess::class)->record('finance_accounts', $companyId, $accountId);
        FinanceAccess::date($asOf);
        $r = DB::connection('tenant')->table('finance_lines as l')->join('finance_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $companyId)->where('l.account_id', $accountId)->where('j.date', '<=', $asOf)->selectRaw('coalesce(sum(l.debit),0)::text as debit,coalesce(sum(l.credit),0)::text as credit')->first();

        return new BalanceData($companyId, $accountId, $asOf, FinanceAccess::money($r->debit), FinanceAccess::money($r->credit), bcsub($r->debit, $r->credit, 2));
    }

    /** @return array<string,mixed> */
    public function trial(string $company, string $from, string $to): array
    {
        app(FinanceAccess::class)->company($company);
        Validator::make(['from' => $from, 'to' => $to], ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from|before_or_equal:'.FinanceAccess::today()])->validate();
        $rows = DB::connection('tenant')->table('finance_accounts as a')->leftJoinSub(DB::connection('tenant')->table('finance_lines as l')->join('finance_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $company)->where('j.date', '<=', $to)->groupBy('account_id')->selectRaw('account_id,coalesce(sum(CASE WHEN j.date < ? THEN debit-credit ELSE 0 END),0) as opening,coalesce(sum(CASE WHEN j.date >= ? THEN debit ELSE 0 END),0) as debit,coalesce(sum(CASE WHEN j.date >= ? THEN credit ELSE 0 END),0) as credit,sum(debit-credit) as closing', [$from, $from, $from]), 'b', 'b.account_id', '=', 'a.id')->where('a.company_id', $company)->orderBy('a.code')->selectRaw('a.id,a.code,a.name,a.type,coalesce(b.opening,0)::text as opening,coalesce(b.debit,0)::text as debit,coalesce(b.credit,0)::text as credit,coalesce(b.closing,0)::text as closing')->get()->all();
        $debit = '0.00';
        $credit = '0.00';
        $assets = '0.00';
        $liabilities = '0.00';
        $equity = '0.00';
        $income = '0.00';
        $expense = '0.00';
        $cumulativeResult = '0.00';
        foreach ($rows as $r) {
            foreach (['opening', 'debit', 'credit', 'closing'] as $field) {
                $r->$field = FinanceAccess::money($r->$field);
            }$debit = bcadd($debit, $r->debit, 2);
            $credit = bcadd($credit, $r->credit, 2);
            if ($r->type === 'asset') {
                $assets = bcadd($assets, $r->closing, 2);
            }if ($r->type === 'liability') {
                $liabilities = bcsub($liabilities, $r->closing, 2);
            }if ($r->type === 'equity') {
                $equity = bcsub($equity, $r->closing, 2);
            }if ($r->type === 'income') {
                $income = bcadd($income, bcsub($r->credit, $r->debit, 2), 2);
                $cumulativeResult = bcsub($cumulativeResult, $r->closing, 2);
            }if ($r->type === 'expense') {
                $expense = bcadd($expense, bcsub($r->debit, $r->credit, 2), 2);
                $cumulativeResult = bcsub($cumulativeResult, $r->closing, 2);
            }
        }

        return ['currency' => 'LKR', 'from' => $from, 'to' => $to, 'accounts' => $rows, 'period_debit' => $debit, 'period_credit' => $credit, 'period_difference' => bcsub($debit, $credit, 2), 'income' => $income, 'expense' => $expense, 'period_result' => bcsub($income, $expense, 2), 'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'cumulative_unclosed_result' => $cumulativeResult, 'position_difference' => bcsub($assets, bcadd(bcadd($liabilities, $equity, 2), $cumulativeResult, 2), 2), 'label' => 'Posted ledger management view; not statutory financial statements.'];
    }

    /** @return LengthAwarePaginator<int,\stdClass> */
    public function accountLedger(string $company, string $account, string $from, string $to, int $page = 1): LengthAwarePaginator
    {
        $this->trial($company, $from, $to);
        app(FinanceAccess::class)->record('finance_accounts', $company, $account);
        Validator::make(['page' => $page], ['page' => 'integer|min:1|max:100000'])->validate();

        return DB::connection('tenant')->table('finance_lines as l')->join('finance_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $company)->where('l.account_id', $account)->whereBetween('j.date', [$from, $to])->orderBy('j.date')->orderBy('j.number')->orderBy('l.id')->paginate(25, ['l.*', 'j.date', 'j.number', 'j.narration', 'j.source_kind'], page: $page);
    }

    /** @return array<string,mixed> */
    public function aging(string $company, string $asOf): array
    {
        app(FinanceAccess::class)->company($company);
        FinanceAccess::date($asOf);
        $rows = DB::connection('tenant')->table('finance_documents as d')->leftJoinSub(DB::connection('tenant')->table('finance_payments as p')->join('finance_journals as j', 'j.id', '=', 'p.journal_id')->where('p.company_id', $company)->where('j.date', '<=', $asOf)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('finance_payment_voids as v')->join('finance_journals as vj', 'vj.id', '=', 'v.journal_id')->whereColumn('v.payment_id', 'p.id')->where('vj.date', '<=', $asOf))->groupBy('document_id')->selectRaw('document_id,sum(p.amount) as paid'), 'p', 'p.document_id', '=', 'd.id')->where('d.company_id', $company)->where('d.state', 'posted')->where('d.date', '<=', $asOf)->whereRaw('d.total-coalesce(p.paid,0)>0')->orderBy('d.due_date')->selectRaw("d.id,d.kind,d.number,d.due_date,d.snapshot->'party'->>'name' as party_name,(d.total-coalesce(p.paid,0))::text as outstanding")->get()->all();
        $buckets = ['invoice' => ['current' => '0.00', '1_30' => '0.00', '31_60' => '0.00', '61_90' => '0.00', '90_plus' => '0.00'], 'bill' => ['current' => '0.00', '1_30' => '0.00', '31_60' => '0.00', '61_90' => '0.00', '90_plus' => '0.00']];
        foreach ($rows as $r) {
            $days = (int) CarbonImmutable::parse($r->due_date)->diffInDays(CarbonImmutable::parse($asOf), false);
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus')));
            $buckets[$r->kind][$bucket] = bcadd($buckets[$r->kind][$bucket], $r->outstanding, 2);
            $r->bucket = $bucket;
        }

        return ['currency' => 'LKR', 'as_of' => $asOf, 'buckets' => $buckets, 'open_count' => count($rows), 'documents' => array_slice($rows, 0, 100), 'preview_limit' => 100];
    }
}
