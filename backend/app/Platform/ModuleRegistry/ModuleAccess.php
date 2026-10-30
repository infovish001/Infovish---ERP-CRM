<?php

namespace App\Platform\ModuleRegistry;

use App\Shared\Audit\Audit;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class ModuleAccess
{
    public function allowed(string $module): bool
    {
        $tenant = app(TenantContext::class)->requireTenantId();
        $definition = app(ModuleRegistry::class)->manifests()[$module] ?? null;
        if (! $definition || DB::connection('pgsql')->table('tenants')->where('id', $tenant)->value('schema_version') !== 2) {
            return false;
        }

        $available = DB::connection('pgsql')->table('tenant_module_entitlements')->where('tenant_id', $tenant)->where('module', $module)->where('allowed', true)->exists()
            && DB::connection('tenant')->table('module_installations')->where('module', $module)->where('version', $definition['version'])->where('enabled', true)->exists();
        if (! $available) {
            return false;
        }
        foreach (array_keys($definition['dependencies']) as $dependency) {
            if (! $this->allowed($dependency)) {
                return false;
            }
        }

        return true;
    }

    public function require(string $module, bool $write = false): void
    {
        abort_unless($this->allowed($module), 403, 'Module access is unavailable.');
        $context = app(TenantContext::class);
        abort_unless($context->can($write ? 'organization.manage' : 'organization.view'), 403);
        foreach (app(ModuleRegistry::class)->manifests()[$module]['dependencies'] as $dependency => $version) {
            abort_unless($this->allowed($dependency), 403, 'A module dependency is unavailable.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function diagnostics(): array
    {
        $tenant = app(TenantContext::class)->requireTenantId();
        $ready = DB::connection('pgsql')->table('tenants')->where('id', $tenant)->value('schema_version') === 2;
        $rows = [];
        foreach (app(ModuleRegistry::class)->manifests() as $name => $definition) {
            $installed = $ready ? DB::connection('tenant')->table('module_installations')->where('module', $name)->first() : null;
            $rows[] = ['name' => $name, 'version' => $definition['version'], 'installed_version' => $installed?->version, 'enabled' => $installed->enabled ?? false,
                'entitled' => DB::connection('pgsql')->table('tenant_module_entitlements')->where('tenant_id', $tenant)->where('module', $name)->where('allowed', true)->exists(),
                'available' => $this->allowed($name), 'dependencies' => array_keys($definition['dependencies'])];
        }

        return $rows;
    }

    public function setEnabled(string $module, bool $enabled): void
    {
        $context = app(TenantContext::class);
        abort_unless($context->can('organization.manage'), 403);
        $definitions = app(ModuleRegistry::class)->manifests();
        abort_unless(isset($definitions[$module]), 404);
        abort_unless(DB::connection('pgsql')->table('tenants')->where('id', $context->requireTenantId())->value('schema_version') === 2, 409, 'An operator schema upgrade is required.');
        DB::connection('tenant')->transaction(function () use ($module, $enabled, $definitions) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('module-lifecycle'))");
            $row = DB::connection('tenant')->table('module_installations')->where('module', $module)->first();
            abort_unless($row && $row->version === $definitions[$module]['version'], 409, 'Module schema upgrade required.');
            if ($enabled) {
                abort_unless(DB::connection('pgsql')->table('tenant_module_entitlements')->where('tenant_id', app(TenantContext::class)->requireTenantId())->where('module', $module)->where('allowed', true)->exists(), 403);
                foreach ($definitions[$module]['dependencies'] as $dependency => $version) {
                    abort_unless($this->allowed($dependency), 409, 'Activate dependencies first.');
                }
            } else {
                foreach ($definitions as $other => $definition) {
                    if (isset($definition['dependencies'][$module])) {
                        abort_if(DB::connection('tenant')->table('module_installations')->where('module', $other)->where('enabled', true)->exists(), 409, 'Deactivate dependents first.');
                    }
                }
            }
            if ($row->enabled !== $enabled) {
                DB::connection('tenant')->table('module_installations')->where('module', $module)->update(['enabled' => $enabled, 'updated_at' => now()]);
                app(Audit::class)->tenant($enabled ? 'module.enabled' : 'module.disabled', app(TenantContext::class)->requireTenantId());
            }
        });
    }
}
