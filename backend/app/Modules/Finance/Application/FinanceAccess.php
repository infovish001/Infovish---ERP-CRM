<?php

namespace App\Modules\Finance\Application;

use App\Platform\ModuleRegistry\ModuleAccess;
use App\Shared\MasterData\CompanyScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class FinanceAccess
{
    public function company(string $company, bool $write = false): void
    {
        abort_unless(in_array(app(TenantContext::class)->role(), ['owner', 'administrator', 'auditor'], true), 403, 'Finance access is restricted.');
        abort_if(app(TenantContext::class)->privileged() && ! app(TenantContext::class)->hasConfirmedMfa(), 403);
        app(ModuleAccess::class)->require('Finance', $write);
        abort_unless(app(CompanyScope::class)->verify($company)->base_currency === 'LKR', 422, 'Finance baseline supports LKR only.');
    }

    public function record(string $table, string $company, string $id, bool $lock = false): \stdClass
    {
        $this->company($company);
        if (! in_array($table, ['finance_accounts', 'finance_periods', 'finance_drafts', 'finance_documents', 'finance_journals', 'finance_payments', 'finance_statements', 'finance_prints'], true)) {
            throw new \InvalidArgumentException('Unsupported Finance resource.');
        }
        $q = DB::connection('tenant')->table($table)->where('company_id', $company)->where('id', $id);
        $mutable = in_array($table, ['finance_accounts', 'finance_periods', 'finance_drafts', 'finance_documents', 'finance_statements'], true);
        $row = ($lock && $mutable ? $q->lockForUpdate() : $q)->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    /** @return list<string> */
    public static function moneyRule(bool $signed = false): array
    {
        return ['required', 'string', $signed ? 'regex:/\A-?(?:0|[1-9]\d{0,21})(?:\.\d{1,2})?\z/' : 'regex:/\A(?:0|[1-9]\d{0,21})(?:\.\d{1,2})?\z/'];
    }

    public static function money(string $value): string
    {
        return bcadd($value, '0', 2);
    }

    public static function today(): string
    {
        return CarbonImmutable::now('Asia/Colombo')->toDateString();
    }

    public static function date(string $date): string
    {
        Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d|before_or_equal:'.self::today()])->validate();

        return $date;
    }
}
