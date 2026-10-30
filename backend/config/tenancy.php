<?php

return [
    'platform_hosts' => ['localhost', '127.0.0.1', '[::1]'],
    'database_host' => env('TENANT_DB_HOST', env('DB_HOST', '127.0.0.1')),
    'database_port' => env('TENANT_DB_PORT', env('DB_PORT', '5432')),
    'database_sslmode' => env('TENANT_DB_SSLMODE', env('DB_SSLMODE', 'prefer')),
    'provisioning_user' => env('PROVISIONING_DB_USERNAME'),
    'provisioning_password' => env('PROVISIONING_DB_PASSWORD'),
];
