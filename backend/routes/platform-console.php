<?php

use App\Platform\Identity\User;
use App\Platform\Tenancy\RuntimeRole;
use App\Platform\Tenancy\TenantDomains;
use App\Platform\Tenancy\TenantProvisioner;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('platform:user {email} {name} {--password-stdin}', function () {
    $password = $this->option('password-stdin') ? trim(stream_get_contents(STDIN)) : $this->secret('New password');
    $data = Validator::make(['email' => strtolower($this->argument('email')), 'name' => $this->argument('name'), 'password' => $password], [
        'email' => 'required|email|max:254|unique:pgsql.users,email', 'name' => 'required|string|max:120',
        'password' => ['required', Password::min(14)->mixedCase()->numbers()->symbols()],
    ])->validate();
    $user = DB::connection('pgsql')->transaction(function () use ($data) {
        $user = User::create($data);
        app(Audit::class)->platform('identity.created', null, null, $user->id);

        return $user;
    });
    $this->info('Created platform identity '.$user->id.'. No workspace access is granted until membership is provisioned.');
})->purpose('Create an operator-approved platform identity without printing credentials');

Artisan::command('platform:tenant {slug} {name} {host} {owner-email}', function () {
    $owner = User::where('email', strtolower($this->argument('owner-email')))->where('active', true)->firstOrFail();
    $id = app(TenantProvisioner::class)->provision($this->argument('slug'), $this->argument('name'), $this->argument('host'), $owner->id);
    $this->info('Tenant '.$id.' is provisioned. Privileged MFA enrollment is required on first sign-in.');
})->purpose('Idempotently provision one tenant through separately supplied administrator credentials');

Artisan::command('platform:tenant-state {slug} {status}', function () {
    abort_unless(in_array($this->argument('status'), ['active', 'suspended'], true), 422, 'Choose active or suspended.');
    DB::connection('provisioner')->transaction(function () {
        $tenant = DB::connection('provisioner')->table('tenants')->where('slug', $this->argument('slug'))->lockForUpdate()->first();
        abort_unless($tenant && $tenant->schema_version === 1 && in_array($tenant->status, ['active', 'suspended'], true), 422);
        if ($tenant->status === $this->argument('status')) {
            return;
        }
        DB::connection('provisioner')->table('tenants')->where('id', $tenant->id)->update(['status' => $this->argument('status'), 'updated_at' => now()]);
        app(Audit::class)->platform($this->argument('status') === 'active' ? 'tenant.resumed' : 'tenant.suspended', null, $tenant->id, $tenant->id, connection: 'provisioner');
    });
    $this->info('Tenant lifecycle updated; data was preserved.');
})->purpose('Suspend or resume an existing provisioned tenant');

Artisan::command('platform:tenant-domain {slug} {host}', function () {
    $id = DB::connection('pgsql')->table('tenants')->where('slug', $this->argument('slug'))->value('id');
    abort_unless(is_string($id), 404);
    app(TenantDomains::class)->register($id, $this->argument('host'));
    $this->info('Registered the workspace hostname. Configure DNS/routing separately for hosted deployments.');
})->purpose('Register an operator-approved exact hostname without changing DNS');

Artisan::command('platform:runtime-role {username} {--password-stdin} {--write-env}', function () {
    $password = $this->option('password-stdin') ? trim(stream_get_contents(STDIN)) : Str::random(64);
    if (! $this->option('password-stdin') && ! $this->option('write-env')) {
        $this->error('Use --write-env for generated credentials or provide --password-stdin.');

        return 1;
    }
    app(RuntimeRole::class)->configure($this->argument('username'), $password);
    if ($this->option('write-env')) {
        $file = base_path('.env');
        $contents = file_get_contents($file);
        foreach (['DB_USERNAME' => $this->argument('username'), 'DB_PASSWORD' => $password] as $key => $value) {
            $contents = preg_replace_callback('/^'.$key.'=.*$/m', fn () => $key.'='.$value, $contents);
        }
        file_put_contents($file, $contents);
    }
    $this->info('Runtime role configured. No credentials were printed.');

    return 0;
})->purpose('Create a non-provisioning runtime role; optionally retain generated credentials in ignored .env');
