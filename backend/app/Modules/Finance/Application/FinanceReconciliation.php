<?php

namespace App\Modules\Finance\Application;

use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class FinanceReconciliation
{
    /** @return list<\stdClass> */
    public function unmatched(string $company, string $account, string $asOf): array
    {
        app(FinanceAccess::class)->company($company);
        FinanceAccess::date($asOf);
        $bank = app(FinanceAccess::class)->record('finance_accounts', $company, $account);
        abort_unless($bank->bank, 422);
        $first = DB::connection('tenant')->table('finance_statements')->where('company_id', $company)->where('bank_account_id', $account)->where('state', 'finalized')->orderBy('starts_on')->first();

        return DB::connection('tenant')->table('finance_lines as l')->join('finance_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $company)->where('l.account_id', $account)->where('j.date', '<=', $asOf)->when($first, fn ($q) => $q->where('j.date', '>=', $first->starts_on))->whereNotExists(fn ($q) => $q->selectRaw('1')->from('finance_matches as m')->whereColumn('m.line_id', 'l.id'))->orderBy('j.date')->orderBy('l.id')->limit(500)->get(['l.id', 'l.debit', 'l.credit', 'j.date', 'j.number', 'j.narration'])->all();
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, array $input, ?string $id, ?string $key): string
    {
        $data = Validator::make($input, ['bank_account_id' => 'required|uuid', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on|before_or_equal:'.FinanceAccess::today(), 'opening' => FinanceAccess::moneyRule(true), 'closing' => FinanceAccess::moneyRule(true), 'opening_cleared_confirmed' => 'required|boolean', 'lines' => 'present|array|max:100', 'lines.*.date' => 'required|date_format:Y-m-d|after_or_equal:starts_on|before_or_equal:ends_on', 'lines.*.reference' => 'required|string|max:100', 'lines.*.amount' => FinanceAccess::moneyRule(true), 'lines.*.line_id' => 'nullable|uuid', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['key' => $key], ['key' => 'required'])->validate();
        }

        return app(Mutation::class)->run('Finance', $company, $id ? 'finance.statement.update:'.$id : 'finance.statement.create', $data, $id ? null : $key, function () use ($company, $data, $id) {
            app(FinanceAccess::class)->company($company, true);
            $bank = app(FinanceAccess::class)->record('finance_accounts', $company, $data['bank_account_id']);
            abort_unless($bank->active && $bank->bank, 422);
            $old = $id ? app(FinanceAccess::class)->record('finance_statements', $company, $id, true) : null;
            if ($old) {
                abort_unless($old->state === 'draft' && $old->revision === $data['revision'], 409, 'Statement changed or is finalized.');
            }
            $values = $data;
            unset($values['revision']);
            $values['lines'] = json_encode($data['lines'], JSON_THROW_ON_ERROR);
            $values['opening'] = FinanceAccess::money($data['opening']);
            $values['closing'] = FinanceAccess::money($data['closing']);
            $values += ['revision' => ($old->revision ?? 0) + 1, 'updated_at' => now()];
            $saved = $id ?? (string) Str::uuid();
            if ($old) {
                DB::connection('tenant')->table('finance_statements')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('finance_statements')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'BNK'), 'state' => 'draft', 'created_at' => now()]);
            }app(FinanceLedger::class)->event($company, 'statement.saved', $saved);

            return $saved;
        });
    }

    public function finalize(string $company, string $id, int $revision, ?string $key): string
    {
        Validator::make(['key' => $key, 'revision' => $revision], ['key' => 'required', 'revision' => 'integer|min:1'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.statement.finalize:'.$id, ['revision' => $revision], $key, function () use ($company, $id, $revision) {
            $r = app(FinanceAccess::class)->record('finance_statements', $company, $id, true);
            if ($r->state === 'finalized') {
                abort_unless($r->revision === $revision + 1, 409);

                return $id;
            }abort_unless($r->revision === $revision, 409, 'Statement changed; reload.');
            $previous = DB::connection('tenant')->table('finance_statements')->where('company_id', $company)->where('bank_account_id', $r->bank_account_id)->where('state', 'finalized')->orderByDesc('ends_on')->first();
            $first = DB::connection('tenant')->table('finance_statements')->where('company_id', $company)->where('bank_account_id', $r->bank_account_id)->where('state', 'finalized')->orderBy('starts_on')->first();
            if ($previous) {
                abort_unless($r->starts_on > $previous->ends_on && bccomp($r->opening, $previous->closing, 2) === 0, 409, 'Statement must follow the previous closing balance and dates.');
            } else {
                abort_unless($r->opening_cleared_confirmed, 422, 'Confirm the cleared opening baseline.');
                $opening = $this->bankNet($company, $r->bank_account_id, $r->starts_on, true);
                abort_unless(bccomp($opening, $r->opening, 2) === 0, 409, 'Opening balance differs from the pre-statement book balance.');
            }
            $lines = json_decode($r->lines, true, flags: JSON_THROW_ON_ERROR);
            $balance = $r->opening;
            $seen = [];
            foreach ($lines as $position => $line) {
                abort_unless(isset($line['line_id']) && bccomp($line['amount'], '0', 2) !== 0, 422, 'Every nonzero statement line must match a ledger entry.');
                abort_if(in_array($line['line_id'], $seen, true), 409, 'Ledger entry used twice.');
                $seen[] = $line['line_id'];
                $book = DB::connection('tenant')->table('finance_lines as l')->join('finance_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $company)->where('l.account_id', $r->bank_account_id)->where('l.id', $line['line_id'])->where('j.date', '<=', $r->ends_on)->where('j.date', '>=', $first->starts_on ?? $r->starts_on)->select('l.*')->first();
                abort_unless($book !== null && bccomp(bcsub($book->debit, $book->credit, 2), $line['amount'], 2) === 0, 422, 'Match the same account, amount, sign and eligible date.');
                abort_if(DB::connection('tenant')->table('finance_matches')->where('line_id', $book->id)->exists(), 409, 'Ledger entry already reconciled.');
                DB::connection('tenant')->table('finance_matches')->insert(['id' => (string) Str::uuid(), 'company_id' => $company, 'statement_id' => $id, 'line_id' => $book->id, 'position' => $position + 1, 'created_at' => now()]);
                $balance = bcadd($balance, $line['amount'], 2);
            }
            abort_unless(bccomp($balance, $r->closing, 2) === 0, 409, 'Statement opening and lines do not equal closing.');
            $book = $this->bankNet($company, $r->bank_account_id, $r->ends_on);
            $baseline = $first->starts_on ?? $r->starts_on;
            $out = DB::connection('tenant')->table('finance_lines as l')->join('finance_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $company)->where('l.account_id', $r->bank_account_id)->whereBetween('j.date', [$baseline, $r->ends_on])->whereNotExists(fn ($q) => $q->selectRaw('1')->from('finance_matches as m')->whereColumn('m.line_id', 'l.id'))->selectRaw('coalesce(sum(l.debit-l.credit),0)::text as amount')->first();
            $adjusted = bcsub($book, $out->amount, 2);
            abort_unless(bccomp($adjusted, $r->closing, 2) === 0, 409, 'Adjusted ledger balance does not reconcile.');
            $summary = ['book_balance' => $book, 'outstanding_net' => FinanceAccess::money($out->amount), 'adjusted_book' => $adjusted, 'statement_closing' => $r->closing, 'baseline_start' => $baseline];
            DB::connection('tenant')->table('finance_statements')->where('id', $id)->update(['state' => 'finalized', 'summary' => json_encode($summary, JSON_THROW_ON_ERROR), 'revision' => $r->revision + 1, 'updated_at' => now()]);
            app(FinanceLedger::class)->event($company, 'statement.finalized', $id);

            return $id;
        });
    }

    private function bankNet(string $company, string $account, string $date, bool $before = false): string
    {
        $r = DB::connection('tenant')->table('finance_lines as l')->join('finance_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $company)->where('l.account_id', $account)->where('j.date', $before ? '<' : '<=', $date)->selectRaw('coalesce(sum(l.debit-l.credit),0)::text as amount')->first();

        return FinanceAccess::money($r->amount);
    }
}
