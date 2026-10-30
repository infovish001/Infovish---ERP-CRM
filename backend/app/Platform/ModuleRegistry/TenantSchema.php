<?php

namespace App\Platform\ModuleRegistry;

use App\Platform\Tenancy\TenantProvisioner;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TenantSchema
{
    public const VERSION = 4;

    public function install(string $connection, string $tenantId, string $role): void
    {
        $db = DB::connection($connection);
        $identity = $db->getSchemaBuilder()->hasTable('tenant_identity') ? $db->table('tenant_identity')->value('id') : null;
        if ($identity !== null && $identity !== $tenantId) {
            throw new RuntimeException('Tenant identity mismatch.');
        }
        $this->migrate($connection, 'database/tenant-migrations');
        if ($identity === null) {
            $db->table('tenant_identity')->insert(['id' => $tenantId]);
        }
        foreach (app(ModuleRegistry::class)->manifests() as $module => $definition) {
            $this->migrate($connection, 'app/Modules/'.$module.'/'.$definition['migrations']);
            $db->table('module_installations')->insertOrIgnore(['module' => $module, 'version' => $definition['version'], 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
            $db->table('module_installations')->where('module', $module)->update(['version' => $definition['version'], 'updated_at' => now()]);
            DB::connection('provisioner')->table('tenant_module_entitlements')->insertOrIgnore(['tenant_id' => $tenantId, 'module' => $module, 'allowed' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $role = app(TenantProvisioner::class)->identifier($role);
        $db->statement('REVOKE ALL ON SCHEMA public FROM PUBLIC');
        $db->statement('GRANT USAGE ON SCHEMA public TO "'.$role.'"');
        $db->statement('GRANT SELECT ON tenant_identity, audit_events, module_installations TO "'.$role.'"');
        $db->statement('GRANT INSERT ON audit_events TO "'.$role.'"');
        $db->statement('GRANT UPDATE (enabled, updated_at) ON module_installations TO "'.$role.'"');
        $db->statement('GRANT SELECT, INSERT, UPDATE, DELETE ON companies, branches, departments, document_counters, idempotency_records, event_outbox, event_receipts, parties_contacts, catalog_products, catalog_variants TO "'.$role.'"');
        $db->statement('GRANT SELECT, INSERT, UPDATE, DELETE ON crm_pipelines, crm_stages, crm_leads, crm_opportunities, crm_activities TO "'.$role.'"');
        $db->statement('GRANT SELECT, INSERT, UPDATE ON sales_tax_profiles, sales_quotes TO "'.$role.'"');
        $db->statement('GRANT SELECT, INSERT ON sales_orders, sales_history TO "'.$role.'"');
        $db->statement('GRANT SELECT, INSERT ON crm_history TO "'.$role.'"');
    }

    private function migrate(string $connection, string $path): void
    {
        if (Artisan::call('migrate', ['--database' => $connection, '--path' => $path, '--force' => true]) !== 0) {
            throw new RuntimeException('Tenant schema migration failed.');
        }
    }

    public function upgrade(string $slug): void
    {
        $platform = DB::connection('provisioner');
        $platform->select('SELECT pg_advisory_lock(hashtext(?))', ['provision:'.$slug]);
        $tenant = null;
        try {
            $tenant = $platform->table('tenants')->where('slug', $slug)->first();
            if (! $tenant || ! in_array($tenant->status, ['active', 'suspended'], true)) {
                throw new RuntimeException('Provision an active/suspended tenant before upgrade.');
            }
            $platform->table('tenants')->where('id', $tenant->id)->update(['status' => 'suspended', 'updated_at' => now()]);
            $config = config('database.connections.provisioner');
            $config['database'] = $tenant->database_name;
            config(['database.connections.tenant_migration' => $config]);
            DB::purge('tenant_migration');
            // Existing tenants must carry an exact identity before any DDL is attempted.
            if (DB::connection('tenant_migration')->table('tenant_identity')->value('id') !== $tenant->id) {
                throw new RuntimeException('Tenant identity mismatch.');
            }
            $this->install('tenant_migration', $tenant->id, $tenant->database_user);
            $platform->transaction(function () use ($platform, $tenant) {
                $platform->table('tenants')->where('id', $tenant->id)->update(['status' => $tenant->status, 'schema_version' => self::VERSION, 'updated_at' => now()]);
                app(Audit::class)->platform('tenant.schema_upgraded', null, $tenant->id, $tenant->id, connection: 'provisioner');
            });
        } catch (\Throwable) {
            throw new RuntimeException('Tenant upgrade failed; data was preserved and the tenant remains suspended for operator review.');
        } finally {
            DB::purge('tenant_migration');
            config(['database.connections.tenant_migration' => null]);
            $platform->select('SELECT pg_advisory_unlock(hashtext(?))', ['provision:'.$slug]);
        }
    }
}
