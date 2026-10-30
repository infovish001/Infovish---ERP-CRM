<?php

namespace App\Modules\Finance\Application;

use App\Shared\Audit\Audit;
use App\Shared\Events\Outbox;
use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class FinanceLedger
{
    /** @param array<string,mixed> $input */
    public function draft(string $company, array $input, ?string $id, ?string $key): string
    {
        $data = Validator::make($input, ['date' => 'required|date_format:Y-m-d|before_or_equal:'.FinanceAccess::today(), 'narration' => 'required|string|max:500', 'lines' => 'required|array|min:2|max:100', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['key' => $key], ['key' => 'required'])->validate();
        }

        return app(Mutation::class)->run('Finance', $company, $id ? 'finance.draft.update:'.$id : 'finance.draft.create', $data, $id ? null : $key, function () use ($company, $data, $id) {
            app(FinanceAccess::class)->company($company, true);
            $old = $id ? app(FinanceAccess::class)->record('finance_drafts', $company, $id, true) : null;
            if ($old) {
                abort_unless($old->state === 'draft' && $old->revision === $data['revision'], 409, 'Journal draft changed or is posted.');
            }
            $lines = $this->lines($company, $data['lines'], 'manual');
            $saved = $id ?? (string) Str::uuid();
            $values = ['date' => $data['date'], 'narration' => $data['narration'], 'lines' => json_encode($lines, JSON_THROW_ON_ERROR), 'revision' => ($old->revision ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::connection('tenant')->table('finance_drafts')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('finance_drafts')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'JDR'), 'state' => 'draft', 'created_at' => now()]);
            }$this->event($company, 'draft.saved', $saved);

            return $saved;
        });
    }

    public function postDraft(string $company, string $id, int $revision, ?string $key): string
    {
        Validator::make(['key' => $key, 'revision' => $revision], ['key' => 'required', 'revision' => 'integer|min:1'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.draft.post:'.$id, ['revision' => $revision], $key, function () use ($company, $id, $revision) {
            $old = app(FinanceAccess::class)->record('finance_drafts', $company, $id, true);
            if ($old->state === 'posted') {
                abort_unless($old->revision === $revision + 1, 409);

                return $old->journal_id;
            }
            abort_unless($old->revision === $revision, 409, 'Journal draft changed; reload.');
            $journal = $this->post($company, $old->date, $old->narration, json_decode($old->lines, true, flags: JSON_THROW_ON_ERROR), 'manual', $id);
            DB::connection('tenant')->table('finance_drafts')->where('id', $id)->update(['state' => 'posted', 'journal_id' => $journal, 'revision' => $old->revision + 1, 'updated_at' => now()]);

            return $journal;
        });
    }

    /** @param list<array<string,mixed>> $lines */
    public function post(string $company, string $date, string $narration, array $lines, string $source, string $sourceId, ?string $reverse = null): string
    {
        if (DB::connection('tenant')->transactionLevel() < 1 || ! in_array($source, ['manual', 'reversal', 'invoice', 'bill', 'payment', 'payment_void'], true)) {
            throw new \LogicException('Finance posting requires an authorized transaction.');
        }
        app(FinanceAccess::class)->company($company, true);
        FinanceAccess::date($date);
        $period = DB::connection('tenant')->table('finance_periods')->where('company_id', $company)->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->where('locked', false)->lockForUpdate()->first();
        abort_unless($period !== null, 409, 'No open accounting period covers this date.');
        abort_unless(app(FinanceSetup::class)->settings($company) !== null, 422, 'Configure Finance mappings before posting.');
        $validated = $this->lines($company, $lines, $source);
        $debit = '0.00';
        $credit = '0.00';
        foreach ($validated as $l) {
            $debit = bcadd($debit, $l['debit'], 2);
            $credit = bcadd($credit, $l['credit'], 2);
        }
        abort_unless(bccomp($debit, $credit, 2) === 0 && bccomp($debit, '0', 2) > 0 && bccomp($debit, '9999999999999999999999.99', 2) <= 0, 422, 'Journal debits and credits must balance within the supported range.');
        $id = (string) Str::uuid();
        DB::connection('tenant')->table('finance_journals')->insert(['id' => $id, 'company_id' => $company, 'period_id' => $period->id, 'number' => app(Numbering::class)->next($company, 'JRN'), 'date' => $date, 'narration' => $narration, 'source_kind' => $source, 'source_id' => $sourceId, 'reversal_of' => $reverse, 'actor_id' => app(TenantContext::class)->actorId(), 'posted_at' => now()]);
        foreach ($validated as $l) {
            if ($l['bank']) {
                abort_if(DB::connection('tenant')->table('finance_statements')->where('company_id', $company)->where('bank_account_id', $l['account_id'])->where('state', 'finalized')->where('ends_on', '>=', $date)->exists(), 409, 'Bank period is reconciled; use a later date.');
            }DB::connection('tenant')->table('finance_lines')->insert($l + ['id' => (string) Str::uuid(), 'company_id' => $company, 'journal_id' => $id]);
        }
        $this->event($company, 'journal.posted', $id);

        return $id;
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     * @return list<array<string,mixed>>
     */
    private function lines(string $company, array $lines, string $source): array
    {
        $data = Validator::make(['lines' => $lines], ['lines' => 'required|array|min:2|max:100', 'lines.*.account_id' => 'required|uuid', 'lines.*.debit' => FinanceAccess::moneyRule(), 'lines.*.credit' => FinanceAccess::moneyRule(), 'lines.*.description' => 'nullable|string|max:300'])->validate();
        $output = [];
        $settings = app(FinanceSetup::class)->settings($company);
        foreach ($data['lines'] as $line) {
            $d = FinanceAccess::money($line['debit']);
            $c = FinanceAccess::money($line['credit']);
            abort_unless((bccomp($d, '0', 2) > 0 && $c === '0.00') || (bccomp($c, '0', 2) > 0 && $d === '0.00'), 422, 'Each journal line requires exactly one positive side.');
            $a = app(FinanceAccess::class)->record('finance_accounts', $company, $line['account_id']);
            abort_unless($a->active, 422, 'Account is inactive.');
            if ($source === 'manual' && $settings) {
                abort_if(in_array($a->id, [$settings['receivable'], $settings['payable']], true), 422, 'Use document/payment workflows for AR/AP control accounts.');
            }$output[] = ['account_id' => $a->id, 'account_code' => $a->code, 'account_name' => $a->name, 'account_type' => $a->type, 'bank' => $a->bank, 'description' => $line['description'] ?? null, 'debit' => $d, 'credit' => $c];
        }

        return $output;
    }

    public function reverse(string $company, string $id, string $date, string $reason, ?string $key, bool $payment = false): string
    {
        FinanceAccess::date($date);
        Validator::make(['reason' => $reason, 'key' => $key], ['reason' => 'required|string|max:500', 'key' => 'required'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.reverse:'.$id, ['date' => $date, 'reason' => $reason], $key, function () use ($company, $id, $date, $reason, $payment) {
            $old = app(FinanceAccess::class)->record('finance_journals', $company, $id, true);
            abort_unless($old->source_kind === ($payment ? 'payment' : 'manual'), 409, 'Reverse this source through its dedicated workflow.');
            abort_unless($date >= $old->date, 422, 'Reversal date precedes the original.');
            $existing = DB::connection('tenant')->table('finance_journals')->where('company_id', $company)->where('reversal_of', $id)->first();
            if ($existing) {
                abort_unless($existing->date === $date && $existing->narration === $reason, 409, 'Reversal intent differs.');

                return $existing->id;
            }
            $lines = DB::connection('tenant')->table('finance_lines')->where('journal_id', $id)->get()->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'description' => 'Reversal'])->all();
            $journal = $this->post($company, $date, $reason, $lines, $payment ? 'payment_void' : 'reversal', $id, $id);
            $this->event($company, 'journal.reversed', $journal);

            return $journal;
        });
    }

    public function event(string $company, string $action, string $id): void
    {
        app(Audit::class)->tenant('finance.'.$action, $id, ['company_id' => $company]);
        app(Outbox::class)->record('Finance', 'finance.'.$action, $id, $company);
    }
}
