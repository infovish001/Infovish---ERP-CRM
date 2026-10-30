<?php

namespace App\Platform\Tenancy;

use App\Platform\ModuleRegistry\TenantSchema;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TenantProvisioner
{
    public function provision(string $slug, string $name, string $host, string $ownerId): string
    {
        $slug = strtolower($slug);
        $host = strtolower($host);
        Validator::make(compact('slug', 'name', 'host', 'ownerId'), [
            'slug' => ['required', 'regex:/\A[a-z][a-z0-9-]{1,48}\z/'], 'name' => 'required|string|max:120',
            'host' => ['required', 'max:253', 'regex:/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+\z/'],
            'ownerId' => 'required|uuid',
        ])->validate();
        if (in_array($host, config('tenancy.platform_hosts'), true) || ! DB::connection('pgsql')->table('users')->where('id', $ownerId)->where('active', true)->exists()) {
            throw new RuntimeException('An active owner and a non-platform hostname are required.');
        }
        $platform = DB::connection('provisioner');
        $admin = DB::connection('provisioner');
        $admin->disableQueryLog();
        $platform->select('SELECT pg_advisory_lock(hashtext(?))', ['provision:'.$slug]);
        $id = null;
        try {
            $tenant = $platform->table('tenants')->where('slug', $slug)->first();
            if (! $tenant) {
                $id = (string) Str::uuid();
                $suffix = str_replace('-', '', $id);
                $platform->table('tenants')->insert(['id' => $id, 'slug' => $slug, 'name' => $name, 'status' => 'provisioning',
                    'database_name' => 'inv_t_'.$suffix, 'database_user' => 'inv_u_'.$suffix,
                    'database_password' => Crypt::encryptString(Str::random(64)), 'schema_version' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $tenant = $platform->table('tenants')->where('id', $id)->first();
            }
            $id = $tenant->id;
            $domain = $platform->table('tenant_domains')->where('host', $host)->first();
            if ($domain && $domain->tenant_id !== $id) {
                throw new RuntimeException('Hostname is already registered.');
            }
            if ($tenant->status === 'active' || $tenant->status === 'suspended') {
                if (! $domain || ! $platform->table('memberships')->where('tenant_id', $id)->where('user_id', $ownerId)->where('role', 'owner')->exists()) {
                    throw new RuntimeException('Existing provisioning parameters differ; explicit operator review is required.');
                }

                return $id;
            }
            $database = $this->identifier($tenant->database_name);
            $role = $this->identifier($tenant->database_user);
            if (! $admin->select('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role])) {
                $password = $admin->getPdo()->quote(Crypt::decryptString($tenant->database_password));
                $admin->statement('CREATE ROLE "'.$role.'" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT PASSWORD '.$password);
            }
            if (! $admin->select('SELECT 1 FROM pg_database WHERE datname = ?', [$database])) {
                $admin->statement('CREATE DATABASE "'.$database.'"');
            }
            $admin->statement('REVOKE ALL ON DATABASE "'.$database.'" FROM PUBLIC');
            $admin->statement('GRANT CONNECT ON DATABASE "'.$database.'" TO "'.$role.'"');
            $connection = config('database.connections.provisioner');
            $connection['database'] = $database;
            config(['database.connections.tenant_migration' => $connection]);
            DB::purge('tenant_migration');
            app(TenantSchema::class)->install('tenant_migration', $id, $role);
            $platform->transaction(function () use ($platform, $id, $host, $ownerId) {
                $platform->table('tenant_domains')->insertOrIgnore(['host' => $host, 'tenant_id' => $id]);
                if ($platform->table('tenant_domains')->where('host', $host)->value('tenant_id') !== $id) {
                    throw new RuntimeException('Hostname belongs to another tenant.');
                }
                $platform->table('memberships')->updateOrInsert(['tenant_id' => $id, 'user_id' => $ownerId], ['role' => 'owner', 'active' => true, 'company_ids' => '[]', 'branch_ids' => '[]', 'created_at' => now(), 'updated_at' => now()]);
                $platform->table('tenants')->where('id', $id)->update(['status' => 'active', 'schema_version' => TenantSchema::VERSION, 'updated_at' => now()]);
                app(Audit::class)->platform('tenant.provisioned', null, $id, $id, connection: 'provisioner');
            });

            return $id;
        } catch (Throwable) {
            if ($id && $platform->table('tenants')->where('id', $id)->whereNotIn('status', ['active', 'suspended'])->exists()) {
                $platform->table('tenants')->where('id', $id)->update(['status' => 'failed', 'updated_at' => now()]);
                app(Audit::class)->platform('tenant.provision_failed', null, $id, $id, connection: 'provisioner');
            }
            // Utility SQL can contain generated credentials. Never attach the original exception.
            throw new RuntimeException('Provisioning did not complete. Resources were preserved; review registry state and retry.');
        } finally {
            DB::purge('tenant_migration');
            config(['database.connections.tenant_migration' => null]);
            $platform->select('SELECT pg_advisory_unlock(hashtext(?))', ['provision:'.$slug]);
        }
    }

    public function identifier(string $name): string
    {
        if (! preg_match('/\A[a-z][a-z0-9_]{0,62}\z/', $name)) {
            throw new RuntimeException('Invalid database identifier.');
        }

        return $name;
    }
}
