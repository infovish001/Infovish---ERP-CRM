<?php

namespace App\Modules\Sales\Application;

use App\Modules\Catalog\Domain\Contracts\Products;
use App\Modules\CRM\Domain\Contracts\Opportunities;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Sales\Domain\Contracts\BillingData;
use App\Modules\Sales\Domain\Contracts\OrderData;
use App\Modules\Sales\Domain\Contracts\Orders;
use App\Shared\Audit\Audit;
use App\Shared\Events\Outbox;
use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SalesService implements Orders
{
    public function billing(string $companyId, string $id): BillingData
    {
        $row = app(SalesAccess::class)->record($companyId, $id, true);

        return new BillingData($this->find($companyId, $id), json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return list<OrderData> */
    public function available(string $companyId, string $search = ''): array
    {
        Validator::make(['q' => $search], ['q' => 'string|max:100'])->validate();

        return app(SalesAccess::class)->records($companyId, true)->where(fn ($q) => $q->where('number', 'ilike', '%'.$search.'%')->orWhere('title', 'ilike', '%'.$search.'%'))->orderByDesc('confirmed_at')->limit(25)->get()->map(fn ($r) => new OrderData($r->id, $companyId, $r->number, $r->quote_id, $r->customer_id, $r->currency, $r->net, $r->vat, $r->total))->all();
    }

    public function find(string $companyId, string $id): OrderData
    {
        $r = app(SalesAccess::class)->record($companyId, $id, true);

        return new OrderData($r->id, $companyId, $r->number, $r->quote_id, $r->customer_id, $r->currency, $r->net, $r->vat, $r->total);
    }

    /**
     * @param  array<string,mixed>  $input
     * @return LengthAwarePaginator<int,\stdClass>
     */
    public function listing(string $company, array $input, bool $orders = false): LengthAwarePaginator
    {
        $data = Validator::make($input, ['q' => 'nullable|string|max:100', 'status' => 'nullable|in:draft,issued,cancelled,converted,confirmed', 'page' => 'sometimes|integer|min:1|max:100000'])->validate();
        $query = app(SalesAccess::class)->records($company, $orders);
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['q'])) {
            $query->where(fn ($q) => $q->where('number', 'ilike', '%'.$data['q'].'%')->orWhere('title', 'ilike', '%'.$data['q'].'%')->orWhereRaw("snapshot->'customer'->>'name' ILIKE ?", ['%'.$data['q'].'%']));
        }

        return $query->select($orders ? ['id', 'company_id', 'number', 'quote_id', 'customer_id', 'title', 'currency', 'status', 'net', 'vat', 'total', 'confirmed_at'] : ['id', 'company_id', 'number', 'customer_id', 'title', 'currency', 'status', 'net', 'vat', 'total', 'revision', 'valid_until', 'order_id', 'updated_at'])
            ->orderByDesc($orders ? 'confirmed_at' : 'updated_at')->orderBy('id')->paginate(25, page: $data['page'] ?? 1);
    }

    /** @return array<string,mixed> */
    public function detail(string $company, string $id, bool $orders = false): array
    {
        $record = app(SalesAccess::class)->record($company, $id, $orders);
        $record->snapshot = json_decode($record->snapshot, true, flags: JSON_THROW_ON_ERROR);

        return ['record' => $record, 'history' => DB::connection('tenant')->table('sales_history')->where('company_id', $company)->where('quote_id', $orders ? $record->quote_id : $id)->orderByDesc('occurred_at')->orderByDesc('id')->limit(100)->get()->all()];
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, array $input, ?string $id = null, ?string $key = null): string
    {
        $today = $this->today();
        $data = Validator::make($input, ['customer_id' => 'required|uuid', 'opportunity_id' => 'nullable|uuid', 'title' => 'required|string|max:160', 'issue_date' => 'required|date_format:Y-m-d|before_or_equal:'.$today,
            'valid_until' => 'required|date_format:Y-m-d|after_or_equal:issue_date', 'terms' => 'nullable|string|max:4000', 'lines' => 'required|array|min:1|max:50', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['key' => $key], ['key' => 'required|string'])->validate();
        }

        return app(Mutation::class)->run('Sales', $company, $id ? 'sales.quote.update:'.$id : 'sales.quote.create', $data, $id ? null : $key, function () use ($company, $data, $id) {
            $old = $id ? app(SalesAccess::class)->record($company, $id, lock: true) : null;
            if ($old) {
                abort_unless($old->status === 'draft', 409, 'Only draft quotations can be edited.');
                abort_unless($old->revision === $data['revision'], 409, 'Quotation changed; reload.');
            }
            $profile = app(TaxProfile::class)->get($company);
            abort_unless($profile !== null, 422, 'Configure the company Sales tax profile first.');
            abort_unless(DB::connection('tenant')->table('companies')->where('id', $company)->value('base_currency') === 'LKR', 422, 'Sri Lanka Sales requires LKR.');
            $customer = app(Contacts::class)->find($company, $data['customer_id']);
            abort_unless($customer->customer, 422, 'Choose an active customer.');
            $opportunity = null;
            if (! empty($data['opportunity_id'])) {
                $opportunity = app(Opportunities::class)->find($company, $data['opportunity_id']);
                abort_unless($opportunity->status === 'open' && $opportunity->contactId === $customer->id, 422, 'Choose an open opportunity for this customer.');
            }
            $pricing = app(Pricing::class)->calculate($company, $data['lines'], $profile, $data['issue_date']);
            $saved = $id ?? (string) Str::uuid();
            $snapshot = ['seller' => $profile, 'customer' => ['id' => $customer->id, 'number' => $customer->number, 'name' => $customer->name],
                'opportunity' => $opportunity ? ['id' => $opportunity->id, 'number' => $opportunity->number, 'title' => $opportunity->title] : null,
                'pricing' => $pricing, 'title' => $data['title'], 'issue_date' => $data['issue_date'], 'valid_until' => $data['valid_until'], 'terms' => $data['terms'] ?? null];
            $values = ['customer_id' => $customer->id, 'opportunity_id' => $data['opportunity_id'] ?? null, 'title' => $data['title'], 'issue_date' => $data['issue_date'], 'valid_until' => $data['valid_until'], 'terms' => $data['terms'] ?? null,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'currency' => 'LKR', 'net' => $pricing['net'], 'vat' => $pricing['vat'], 'total' => $pricing['total'], 'revision' => ($old->revision ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::connection('tenant')->table('sales_quotes')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('sales_quotes')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'QTN'), 'status' => 'draft', 'created_by' => app(TenantContext::class)->actorId(), 'created_at' => now()]);
            }
            $this->event($company, $saved, $old ? 'quote.updated' : 'quote.created');

            return $saved;
        });
    }

    /** @param array<string,mixed> $input */
    public function transition(string $company, string $id, array $input): string
    {
        $data = Validator::make($input, ['action' => 'required|in:issue,cancel', 'revision' => 'required|integer|min:1', 'reason' => 'nullable|string|max:500'])->validate();
        if ($data['action'] === 'cancel') {
            Validator::make($data, ['reason' => 'required|string|max:500'])->validate();
        }

        return app(Mutation::class)->run('Sales', $company, 'sales.quote.transition:'.$id, $data, null, function () use ($company, $id, $data) {
            $row = app(SalesAccess::class)->record($company, $id, lock: true);
            abort_unless($row->revision === $data['revision'], 409, 'Quotation changed; reload.');
            abort_unless(in_array($row->status, ['draft', 'issued'], true), 409, 'Quotation is terminal.');
            if ($data['action'] === 'issue') {
                abort_unless($row->status === 'draft', 409, 'Quotation already issued.');
                $this->validateCurrent($company, $row);
            }
            DB::connection('tenant')->table('sales_quotes')->where('id', $id)->update(['status' => $data['action'] === 'issue' ? 'issued' : 'cancelled', 'reason' => $data['action'] === 'cancel' ? $data['reason'] : null, 'revision' => $row->revision + 1, 'updated_at' => now()]);
            $this->event($company, $id, $data['action'] === 'issue' ? 'quote.issued' : 'quote.cancelled', $data['reason'] ?? null);

            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function convert(string $company, string $id, array $input, ?string $key): string
    {
        $data = Validator::make($input, ['revision' => 'required|integer|min:1'])->validate();
        Validator::make(['key' => $key], ['key' => 'required|string'])->validate();

        return app(Mutation::class)->run('Sales', $company, 'sales.quote.convert:'.$id, $data, $key, function () use ($company, $id, $data) {
            $row = app(SalesAccess::class)->record($company, $id, lock: true);
            if ($row->status === 'converted') {
                abort_unless($data['revision'] === $row->revision - 1, 409, 'Conversion revision differs from the original.');

                return $row->order_id;
            }
            abort_unless($row->status === 'issued' && $row->revision === $data['revision'], 409, 'Convert a current issued quotation.');
            $this->validateCurrent($company, $row);
            $order = (string) Str::uuid();
            $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $snapshot['quotation_number'] = $row->number;
            DB::connection('tenant')->table('sales_orders')->insert(['id' => $order, 'company_id' => $company, 'quote_id' => $id, 'customer_id' => $row->customer_id, 'number' => app(Numbering::class)->next($company, 'SO'),
                'title' => $row->title, 'currency' => $row->currency, 'status' => 'confirmed', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'net' => $row->net, 'vat' => $row->vat, 'total' => $row->total, 'confirmed_by' => app(TenantContext::class)->actorId(), 'confirmed_at' => now()]);
            DB::connection('tenant')->table('sales_quotes')->where('id', $id)->update(['status' => 'converted', 'order_id' => $order, 'revision' => $row->revision + 1, 'updated_at' => now()]);
            $this->event($company, $id, 'quote.converted');
            app(Audit::class)->tenant('sales.order.confirmed', $order, ['company_id' => $company]);
            app(Outbox::class)->record('Sales', 'sales.order.confirmed', $order, $company);

            return $order;
        });
    }

    private function validateCurrent(string $company, \stdClass $quote): void
    {
        abort_unless($quote->valid_until >= $this->today(), 409, 'Quotation expired; cancel and create a new quotation.');
        $snapshot = json_decode($quote->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $profile = app(TaxProfile::class)->get($company);
        abort_unless($profile && $profile['revision'] === $snapshot['seller']['revision'], 409, 'Tax profile changed; revise a draft or cancel this issued quotation.');
        abort_unless(app(Contacts::class)->find($company, $quote->customer_id)->customer, 422, 'Customer is unavailable.');
        foreach ($snapshot['pricing']['lines'] as $line) {
            app(Products::class)->find($company, $line['variant_id']);
        }
        if ($quote->opportunity_id) {
            $opportunity = app(Opportunities::class)->find($company, $quote->opportunity_id);
            abort_unless($opportunity->status === 'open' && $opportunity->contactId === $quote->customer_id, 409, 'Linked opportunity is no longer open for this customer.');
        }
    }

    private function event(string $company, string $quote, string $action, ?string $reason = null): void
    {
        DB::connection('tenant')->table('sales_history')->insert(['id' => (string) Str::uuid(), 'company_id' => $company, 'quote_id' => $quote, 'actor_id' => app(TenantContext::class)->actorId(), 'action' => $action, 'reason' => $reason, 'occurred_at' => now()]);
        app(Audit::class)->tenant('sales.'.$action, $quote, ['company_id' => $company]);
        app(Outbox::class)->record('Sales', 'sales.'.$action, $quote, $company);
    }

    private function today(): string
    {
        return CarbonImmutable::now('Asia/Colombo')->toDateString();
    }
}
