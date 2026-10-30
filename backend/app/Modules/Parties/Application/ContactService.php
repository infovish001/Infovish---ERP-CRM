<?php

namespace App\Modules\Parties\Application;

use App\Modules\Parties\Domain\Contracts\ContactData;
use App\Modules\Parties\Domain\Contracts\Contacts;
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

final class ContactService implements Contacts
{
    public function find(string $companyId, string $id): ContactData
    {
        app(ModuleAccess::class)->require('Parties');
        app(CompanyScope::class)->verify($companyId);
        $row = DB::connection('tenant')->table('parties_contacts')->where('company_id', $companyId)->where('id', $id)->where('active', true)->first();
        abort_unless($row !== null, 404);

        return new ContactData($row->id, $row->company_id, $row->number, $row->name, $row->customer, $row->supplier);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int,\stdClass>
     */
    public function listing(string $company, array $filters): LengthAwarePaginator
    {
        app(ModuleAccess::class)->require('Parties');
        app(CompanyScope::class)->verify($company);
        $data = Validator::make($filters, ['q' => 'sometimes|nullable|string|max:100', 'page' => 'sometimes|integer|min:1|max:100000', 'status' => 'sometimes|in:all,active,inactive'])->validate();
        $query = DB::connection('tenant')->table('parties_contacts')->where('company_id', $company);
        if (($data['q'] ?? '') !== '') {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$data['q'].'%')->orWhere('number', 'ilike', '%'.$data['q'].'%')->orWhere('email', 'ilike', '%'.$data['q'].'%'));
        }
        if (($data['status'] ?? 'all') !== 'all') {
            $query->where('active', $data['status'] === 'active');
        }

        return $query->orderBy('name')->orderBy('id')->paginate(25, page: $data['page'] ?? 1);
    }

    /** @param array<string,mixed> $input */
    public function save(string $company, array $input, ?string $id = null, ?string $key = null): string
    {
        app(ModuleAccess::class)->require('Parties', true);
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = strtolower(trim($input['email']));
        }
        $data = Validator::make($input, ['name' => 'required|string|max:160', 'email' => 'nullable|email|max:254', 'phone' => ['nullable', 'string', 'max:40', 'regex:/\A[0-9+(). x-]+\z/'],
            'customer' => 'required|boolean', 'supplier' => 'required|boolean', 'active' => 'required|boolean', 'revision' => ($id ? 'required' : 'sometimes').'|integer|min:1'])->validate();
        if (! $id) {
            Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required|string'])->validate();
        }

        return app(Mutation::class)->run('Parties', $company, $id ? 'contact.update:'.$id : 'contact.create', $data, $id ? null : $key, function () use ($id, $company, $data) {
            $current = $id ? DB::connection('tenant')->table('parties_contacts')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($current !== null, 404);
                abort_unless($current->revision === $data['revision'], 409, 'Record changed; reload before editing.');
            }
            $duplicate = DB::connection('tenant')->table('parties_contacts')->where('company_id', $company)->where('email', $data['email'] ?? null);
            if (! empty($data['email']) && $duplicate->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['email' => 'This company already has a contact with that email.']);
            }
            $saved = $id ?? (string) Str::uuid();
            $values = collect($data)->except('revision')->all() + ['updated_at' => now(), 'revision' => ($current->revision ?? 0) + 1];
            if ($id) {
                DB::connection('tenant')->table('parties_contacts')->where('id', $id)->update($values);
            } else {
                DB::connection('tenant')->table('parties_contacts')->insert($values + ['id' => $saved, 'company_id' => $company, 'number' => app(Numbering::class)->next($company, 'CON'), 'created_at' => now()]);
            }
            $type = $id ? 'contact.updated' : 'contact.created';
            app(Audit::class)->tenant($type, $saved, ['company_id' => $company, 'changed_fields' => array_keys($data)]);
            app(Outbox::class)->record('Parties', $type, $saved, $company);

            return $saved;
        });
    }
}
