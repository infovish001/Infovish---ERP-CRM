<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Contracts\ProductData;
use App\Modules\Catalog\Domain\Contracts\Products;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Shared\Audit\Audit;
use App\Shared\Events\Outbox;
use App\Shared\MasterData\CompanyScope;
use App\Shared\MasterData\Mutation;
use App\Shared\MasterData\Numbering;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductService implements Products
{
    /** @return list<ProductData> */
    public function available(string $companyId, string $search = ''): array
    {
        app(ModuleAccess::class)->require('Catalog');
        app(CompanyScope::class)->verify($companyId);
        Validator::make(['q' => $search], ['q' => 'string|max:100'])->validate();

        return DB::connection('tenant')->table('catalog_variants as v')->join('catalog_products as p', 'p.id', '=', 'v.product_id')->where('v.company_id', $companyId)->where('v.active', true)->where('p.active', true)
            ->where(fn ($q) => $q->where('v.name', 'ilike', '%'.$search.'%')->orWhere('v.sku', 'ilike', '%'.$search.'%'))->orderBy('v.sku')->limit(25)->get(['v.*', 'p.unit', 'p.type'])->map(fn ($v) => new ProductData($v->id, $v->product_id, $companyId, $v->sku, $v->name, $v->type, $v->unit, $v->list_price, $v->currency))->all();
    }

    public function find(string $companyId, string $variantId): ProductData
    {
        app(ModuleAccess::class)->require('Catalog');
        app(CompanyScope::class)->verify($companyId);
        $row = DB::connection('tenant')->table('catalog_variants as v')->join('catalog_products as p', 'p.id', '=', 'v.product_id')
            ->where('v.company_id', $companyId)->where('v.id', $variantId)->where('v.active', true)->where('p.active', true)->select('v.*', 'p.type', 'p.unit')->first();
        abort_unless($row !== null, 404);

        return new ProductData($row->id, $row->product_id, $companyId, $row->sku, $row->name, $row->type, $row->unit, $row->list_price, $row->currency);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int,\stdClass>
     */
    public function listing(string $company, array $filters): LengthAwarePaginator
    {
        app(ModuleAccess::class)->require('Catalog');
        app(CompanyScope::class)->verify($company);
        $data = Validator::make($filters, ['q' => 'sometimes|nullable|string|max:100', 'page' => 'sometimes|integer|min:1|max:100000', 'status' => 'sometimes|in:all,active,inactive'])->validate();
        $query = DB::connection('tenant')->table('catalog_products')->where('company_id', $company);
        if (($data['q'] ?? '') !== '') {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$data['q'].'%')->orWhere('number', 'ilike', '%'.$data['q'].'%')->orWhereExists(fn ($v) => $v->selectRaw('1')->from('catalog_variants')->whereColumn('product_id', 'catalog_products.id')->where('sku', 'ilike', '%'.$data['q'].'%')));
        }
        if (($data['status'] ?? 'all') !== 'all') {
            $query->where('active', $data['status'] === 'active');
        }
        $page = $query->orderBy('name')->orderBy('id')->paginate(25, page: $data['page'] ?? 1);
        $variants = DB::connection('tenant')->table('catalog_variants')->where('company_id', $company)->whereIn('product_id', collect($page->items())->pluck('id'))->orderBy('sku')->get()->groupBy('product_id');
        foreach ($page->items() as $item) {
            $item->variants = ($variants[$item->id] ?? collect())->map(function ($variant) {
                $variant->attributes = json_decode($variant->attributes, true, flags: JSON_THROW_ON_ERROR);

                return $variant;
            })->all();
        }

        return $page;
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, array $input, ?string $id = null, ?string $key = null): string
    {
        app(ModuleAccess::class)->require('Catalog', true);
        $rules = ['name' => 'required|string|max:160', 'active' => 'required|boolean', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'];
        if (! $id) {
            $rules += ['type' => 'required|in:product,service', 'unit' => 'required|string|max:30', 'sku' => ['required', 'regex:/\A[A-Z0-9][A-Z0-9_.-]{0,49}\z/'], 'list_price' => ['nullable', 'string', 'regex:/\A(?:0|[1-9]\d{0,13})(?:\.\d{1,6})?\z/']];
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required|string'])->validate();
        }
        $data = Validator::make($input, $rules)->validate();

        return app(Mutation::class)->run('Catalog', $company, $id ? 'product.update:'.$id : 'product.create', $data, $id ? null : $key, function () use ($company, $id, $data) {
            $current = $id ? DB::connection('tenant')->table('catalog_products')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($current !== null, 404);
                abort_unless($current->revision === $data['revision'], 409, 'Record changed; reload before editing.');
            }
            if (! $id) {
                $this->uniqueSku($company, $data['sku']);
            }
            $saved = $id ?? (string) Str::uuid();
            $values = ['name' => $data['name'], 'active' => $data['active'], 'revision' => ($current->revision ?? 0) + 1, 'updated_at' => now()];
            if ($id) {
                DB::connection('tenant')->table('catalog_products')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('catalog_products')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'PRD'), 'type' => $data['type'], 'unit' => $data['unit'], 'created_at' => now()]);
                $currency = app(CompanyScope::class)->verify($company)->base_currency;
                DB::connection('tenant')->table('catalog_variants')->insert(['id' => (string) Str::uuid(), 'product_id' => $saved, 'company_id' => $company, 'sku' => $data['sku'], 'name' => $data['name'], 'attributes' => '{}', 'list_price' => $data['list_price'] ?? null, 'currency' => $currency, 'active' => true, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $type = $id ? 'product.updated' : 'product.created';
            app(Audit::class)->tenant($type, $saved, ['company_id' => $company, 'changed_fields' => array_keys($data)]);
            app(Outbox::class)->record('Catalog', $type, $saved, $company);

            return $saved;
        });
    }

    /** @param array<string,mixed> $input */
    public function saveVariant(string $company, string $product, array $input, ?string $id = null, ?string $key = null): string
    {
        app(ModuleAccess::class)->require('Catalog', true);
        $data = Validator::make($input, ['name' => 'required|string|max:160', 'active' => 'required|boolean', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1',
            'sku' => ($id ? 'sometimes' : 'required').'|regex:/\A[A-Z0-9][A-Z0-9_.-]{0,49}\z/', 'attributes' => 'present|array|max:10', 'attributes.*' => 'required|string|max:100',
            'list_price' => ['nullable', 'string', 'regex:/\A(?:0|[1-9]\d{0,13})(?:\.\d{1,6})?\z/']])->validate();
        foreach (array_keys($data['attributes']) as $attribute) {
            if (! preg_match('/\A[A-Za-z][A-Za-z0-9 _-]{0,39}\z/', (string) $attribute)) {
                throw ValidationException::withMessages(['attributes' => 'Attribute names must be short readable labels.']);
            }
        }
        if (! $id) {
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required|string'])->validate();
        }

        return app(Mutation::class)->run('Catalog', $company, $id ? 'variant.update:'.$id : 'variant.create:'.$product, $data, $id ? null : $key, function () use ($company, $product, $id, $data) {
            abort_unless(DB::connection('tenant')->table('catalog_products')->where('company_id', $company)->where('id', $product)->where('active', true)->lockForUpdate()->exists(), 404);
            $current = $id ? DB::connection('tenant')->table('catalog_variants')->where('product_id', $product)->where('company_id', $company)->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($current !== null, 404);
                abort_unless($current->revision === $data['revision'], 409, 'Record changed; reload before editing.');
            }
            if (! $id) {
                $this->uniqueSku($company, $data['sku']);
            }
            $saved = $id ?? (string) Str::uuid();
            $values = ['name' => $data['name'], 'active' => $data['active'], 'attributes' => json_encode((object) $data['attributes'], JSON_THROW_ON_ERROR), 'list_price' => $data['list_price'] ?? null, 'revision' => ($current->revision ?? 0) + 1, 'updated_at' => now()];
            if ($id) {
                DB::connection('tenant')->table('catalog_variants')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('catalog_variants')->insert($values + ['id' => $saved, 'product_id' => $product, 'company_id' => $company, 'sku' => $data['sku'], 'currency' => app(CompanyScope::class)->verify($company)->base_currency, 'created_at' => now()]);
            }
            $type = $id ? 'variant.updated' : 'variant.created';
            app(Audit::class)->tenant($type, $saved, ['company_id' => $company, 'changed_fields' => array_keys($data)]);
            app(Outbox::class)->record('Catalog', $type, $saved, $company);

            return $saved;
        });
    }

    private function uniqueSku(string $company, string $sku): void
    {
        if (DB::connection('tenant')->table('catalog_variants')->where('company_id', $company)->where('sku', $sku)->exists()) {
            throw ValidationException::withMessages(['sku' => 'This company already has a variant with that SKU.']);
        }
    }
}
