<?php

namespace App\Modules\Finance\Application;

use App\Shared\MasterData\Mutation;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class FinanceSetup
{
    /** @return array<string,mixed> */
    public function read(string $company): array
    {
        app(FinanceAccess::class)->company($company);

        return ['accounts' => DB::connection('tenant')->table('finance_accounts')->where('company_id', $company)->orderBy('code')->get()->all(),
            'periods' => DB::connection('tenant')->table('finance_periods')->where('company_id', $company)->orderByDesc('starts_on')->get()->all(), 'settings' => $this->settings($company)];
    }

    /** @return array<string,mixed>|null */
    public function settings(string $company): ?array
    {
        app(FinanceAccess::class)->company($company);
        $r = DB::connection('tenant')->table('finance_settings')->where('company_id', $company)->first();

        return $r ? ['revision' => $r->revision, ...json_decode($r->settings, true, flags: JSON_THROW_ON_ERROR)] : null;
    }

    /** @param array<string,mixed> $input */
    public function account(string $company, array $input, ?string $id, ?string $key): string
    {
        $data = Validator::make($input, ['code' => ['required', 'string', 'regex:/\A[A-Z0-9][A-Z0-9_-]{0,19}\z/'], 'name' => 'required|string|max:160', 'type' => 'required|in:asset,liability,equity,income,expense', 'bank' => 'required|boolean', 'active' => 'required|boolean', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['key' => $key], ['key' => 'required'])->validate();
        }

        return app(Mutation::class)->run('Finance', $company, $id ? 'finance.account.update:'.$id : 'finance.account.create', $data, $id ? null : $key, function () use ($company, $data, $id) {
            app(FinanceAccess::class)->company($company, true);
            $old = $id ? app(FinanceAccess::class)->record('finance_accounts', $company, $id, true) : null;
            if ($old) {
                abort_unless($old->revision === $data['revision'] && $old->code === $data['code'] && $old->type === $data['type'] && $old->bank === $data['bank'], 409, 'Account identity/classification is stable; reload the current revision.');
            }
            abort_unless(! $data['bank'] || $data['type'] === 'asset', 422, 'Bank accounts must be assets.');
            abort_if(DB::connection('tenant')->table('finance_accounts')->where('company_id', $company)->where('code', $data['code'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 422, 'Account code already exists.');
            $saved = $id ?? (string) Str::uuid();
            $values = $data;
            unset($values['revision']);
            $values += ['revision' => ($old->revision ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::connection('tenant')->table('finance_accounts')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('finance_accounts')->insert($values + ['id' => $saved, 'company_id' => $company, 'created_at' => now()]);
            }
            app(FinanceLedger::class)->event($company, 'account.saved', $saved);

            return $saved;
        });
    }

    /** @param array<string,mixed> $input */
    public function period(string $company, array $input, ?string $key): string
    {
        $data = Validator::make($input, ['name' => 'required|string|max:100', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on'])->validate();
        Validator::make(['key' => $key], ['key' => 'required'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.period.create', $data, $key, function () use ($company, $data) {
            app(FinanceAccess::class)->company($company, true);
            abort_if(DB::connection('tenant')->table('finance_periods')->where('company_id', $company)->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists(), 409, 'Accounting periods overlap.');
            $id = (string) Str::uuid();
            DB::connection('tenant')->table('finance_periods')->insert($data + ['id' => $id, 'company_id' => $company, 'locked' => false, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
            app(FinanceLedger::class)->event($company, 'period.saved', $id);

            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function lock(string $company, string $id, array $input): string
    {
        $data = Validator::make($input, ['locked' => 'required|boolean', 'reason' => 'required|string|max:500', 'revision' => 'required|integer|min:1'])->validate();

        return app(Mutation::class)->run('Finance', $company, 'finance.period.lock:'.$id, $data, null, function () use ($company, $id, $data) {
            app(FinanceAccess::class)->company($company, true);
            $old = app(FinanceAccess::class)->record('finance_periods', $company, $id, true);
            abort_unless($old->revision === $data['revision'], 409, 'Period changed; reload.');
            if (! $data['locked']) {
                abort_unless(app(TenantContext::class)->role() === 'owner', 403, 'Only the owner may reopen a period.');
            }
            DB::connection('tenant')->table('finance_periods')->where('id', $id)->update(['locked' => $data['locked'], 'reason' => $data['reason'], 'revision' => $old->revision + 1, 'updated_at' => now()]);
            app(FinanceLedger::class)->event($company, $data['locked'] ? 'period.locked' : 'period.unlocked', $id);

            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function configure(string $company, array $input): string
    {
        $rules = ['revision' => 'required|integer|min:0', 'seller_name' => 'required|string|max:160', 'seller_address' => 'required|string|max:500', 'vat_registered' => 'required|boolean', 'tin' => ['nullable', 'string', 'regex:/\A[0-9]{9}\z/'], 'vat_registration' => 'nullable|string|max:30', 'vat_effective_from' => 'nullable|date_format:Y-m-d', 'issuer_code' => ['required', 'string', 'regex:/\A(?=[A-Z0-9]*[0-9])[A-Z0-9]{1,15}\z/']];
        $types = ['receivable' => 'asset', 'payable' => 'liability', 'revenue' => 'income', 'expense' => 'expense', 'vat_input' => 'asset', 'vat_output' => 'liability'];
        foreach ($types as $name => $type) {
            $rules[$name] = 'required|uuid';
        }
        $data = Validator::make($input, $rules)->validate();
        if ($data['vat_registered']) {
            Validator::make($data, ['tin' => 'required', 'vat_registration' => 'required', 'vat_effective_from' => 'required'])->validate();
        }

        return app(Mutation::class)->run('Finance', $company, 'finance.settings', $data, null, function () use ($company, $data, $types) {
            app(FinanceAccess::class)->company($company, true);
            $old = $this->settings($company);
            abort_unless(($old['revision'] ?? 0) === $data['revision'], 409, 'Finance settings changed; reload.');
            if ($old && DB::connection('tenant')->table('finance_journals')->where('company_id', $company)->exists()) {
                foreach (array_keys($types) as $name) {
                    abort_unless($data[$name] === $old[$name], 409, 'Posted-ledger account mappings cannot change.');
                }
            }
            $ids = [];
            foreach ($types as $name => $type) {
                $account = app(FinanceAccess::class)->record('finance_accounts', $company, $data[$name]);
                abort_unless($account->active && ! $account->bank && $account->type === $type, 422, 'Choose an active correctly classified control account.');
                $ids[] = $account->id;
            }abort_unless(count(array_unique($ids)) === count($ids), 422, 'Account mappings must be distinct.');
            $revision = $data['revision'] + 1;
            unset($data['revision']);
            if (! $data['vat_registered']) {
                $data['tin'] = null;
                $data['vat_registration'] = null;
                $data['vat_effective_from'] = null;
            }
            $values = ['settings' => json_encode($data, JSON_THROW_ON_ERROR), 'revision' => $revision, 'updated_at' => now()];
            if ($old) {
                DB::connection('tenant')->table('finance_settings')->where('company_id', $company)->update($values);
            } else {
                DB::connection('tenant')->table('finance_settings')->insert($values + ['company_id' => $company, 'created_at' => now()]);
            }app(FinanceLedger::class)->event($company, 'settings.saved', $company);

            return $company;
        });
    }
}
