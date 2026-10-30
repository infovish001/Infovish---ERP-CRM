<?php

namespace App\Modules\Finance\Application;

use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class FinancePayments
{
    /** @param array<string,mixed> $input */
    public function record(string $company, array $input, ?string $key): string
    {
        $data = Validator::make($input, ['document_id' => 'required|uuid', 'bank_account_id' => 'required|uuid', 'date' => 'required|date_format:Y-m-d|before_or_equal:'.FinanceAccess::today(), 'reference' => 'required|string|max:100', 'amount' => FinanceAccess::moneyRule()])->validate();
        Validator::make(['key' => $key], ['key' => 'required'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.payment.record', $data, $key, function () use ($company, $data) {
            $doc = app(FinanceAccess::class)->record('finance_documents', $company, $data['document_id'], true);
            abort_unless($doc->state === 'posted' && $data['date'] >= $doc->date, 409, 'Pay a posted document on or after its posting date.');
            $amount = FinanceAccess::money($data['amount']);
            abort_unless(bccomp($amount, '0', 2) > 0 && bccomp($amount, app(FinanceDocuments::class)->outstanding($company, $doc->id), 2) <= 0, 409, 'Payment must be positive and cannot exceed outstanding.');
            $bank = app(FinanceAccess::class)->record('finance_accounts', $company, $data['bank_account_id']);
            abort_unless($bank->active && $bank->bank, 422, 'Choose an active bank asset.');
            $settings = app(FinanceSetup::class)->settings($company);
            abort_unless($settings !== null, 422);
            $receipt = $doc->kind === 'invoice';
            $id = (string) Str::uuid();
            $lines = [['account_id' => $bank->id, 'debit' => $receipt ? $amount : '0', 'credit' => $receipt ? '0' : $amount], ['account_id' => $settings[$receipt ? 'receivable' : 'payable'], 'debit' => $receipt ? '0' : $amount, 'credit' => $receipt ? $amount : '0']];
            $journal = app(FinanceLedger::class)->post($company, $data['date'], $data['reference'], $lines, 'payment', $id);
            DB::connection('tenant')->table('finance_payments')->insert($data + ['id' => $id, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'PAY'), 'journal_id' => $journal, 'actor_id' => app(TenantContext::class)->actorId(), 'created_at' => now()]);
            app(FinanceLedger::class)->event($company, 'payment.recorded', $id);

            return $id;
        });
    }

    public function void(string $company, string $id, string $date, string $reason, ?string $key): string
    {
        FinanceAccess::date($date);
        Validator::make(['reason' => $reason, 'key' => $key], ['reason' => 'required|string|max:500', 'key' => 'required'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.payment.void:'.$id, ['date' => $date, 'reason' => $reason], $key, function () use ($company, $id, $date, $reason, $key) {
            $payment = app(FinanceAccess::class)->record('finance_payments', $company, $id, true);
            $old = DB::connection('tenant')->table('finance_payment_voids')->where('payment_id', $id)->first();
            if ($old) {
                $journal = app(FinanceAccess::class)->record('finance_journals', $company, $old->journal_id);
                abort_unless($journal->date === $date && $old->reason === $reason, 409);

                return $old->id;
            }
            $lineIds = DB::connection('tenant')->table('finance_lines')->where('journal_id', $payment->journal_id)->pluck('id');
            abort_if(DB::connection('tenant')->table('finance_matches')->whereIn('line_id', $lineIds)->exists(), 409, 'A reconciled payment requires a reviewed bank correction workflow.');
            $journal = app(FinanceLedger::class)->reverse($company, $payment->journal_id, $date, $reason, $key, true);
            $void = (string) Str::uuid();
            DB::connection('tenant')->table('finance_payment_voids')->insert(['id' => $void, 'company_id' => $company, 'payment_id' => $id, 'journal_id' => $journal, 'reason' => $reason, 'actor_id' => app(TenantContext::class)->actorId(), 'created_at' => now()]);
            app(FinanceLedger::class)->event($company, 'payment.voided', $id);

            return $void;
        });
    }
}
