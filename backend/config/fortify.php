<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'middleware' => ['web'], 'auth_middleware' => 'auth',
    'passwords' => 'users', 'username' => 'email', 'email' => 'email',
    'views' => true, 'home' => '/workspace', 'prefix' => '', 'domain' => null,
    'lowercase_usernames' => true,
    'redirects' => ['password-confirmation' => '/account/security'],
    'limiters' => ['login' => 'workspace-login', 'two-factor' => 'workspace-mfa'],
    'features' => [Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])],
];
