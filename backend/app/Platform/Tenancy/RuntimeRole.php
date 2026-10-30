<?php

namespace App\Platform\Tenancy;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RuntimeRole
{
    public function configure(string $username, string $password): void
    {
        $username = app(TenantProvisioner::class)->identifier($username);
        if (strlen($password) < 24) {
            throw new RuntimeException('Runtime password must have at least 24 characters.');
        }
        $admin = DB::connection('provisioner');
        $admin->disableQueryLog();
        $database = app(TenantProvisioner::class)->identifier(config('database.connections.provisioner.database'));
        try {
            if (! $admin->select('SELECT 1 FROM pg_roles WHERE rolname = ?', [$username])) {
                $admin->statement('CREATE ROLE "'.$username.'" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT PASSWORD '.$admin->getPdo()->quote($password));
            } else {
                $role = $admin->selectOne('SELECT rolsuper, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = ?', [$username]);
                if ($role->rolsuper || $role->rolcreatedb || $role->rolcreaterole) {
                    throw new RuntimeException('Existing role is privileged.');
                }
                $admin->statement('ALTER ROLE "'.$username.'" PASSWORD '.$admin->getPdo()->quote($password));
            }
            $admin->statement('REVOKE CONNECT ON DATABASE "'.$database.'" FROM PUBLIC');
            $admin->statement('GRANT CONNECT ON DATABASE "'.$database.'" TO "'.$username.'"');
            $admin->statement('GRANT USAGE ON SCHEMA public TO "'.$username.'"');
            $admin->statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO "'.$username.'"');
            $admin->statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO "'.$username.'"');
            $admin->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO "'.$username.'"');
            $admin->statement('REVOKE UPDATE, DELETE ON platform_audit_events FROM "'.$username.'"');
            $admin->statement('REVOKE INSERT, UPDATE, DELETE ON tenants, tenant_domains FROM "'.$username.'"');
        } catch (\Throwable) {
            throw new RuntimeException('Runtime role configuration failed; no credentials were emitted.');
        }
    }
}
