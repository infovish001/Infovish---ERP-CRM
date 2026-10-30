<?php

namespace App\Platform\Identity;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/** @property string $id @property string $name @property string $email @property string $password @property bool $active @property int $session_version @property ?string $two_factor_confirmed_at */
class User extends Authenticatable
{
    use HasUuids, TwoFactorAuthenticatable;

    protected $connection = 'pgsql';

    protected $attributes = ['active' => true, 'session_version' => 1];

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'session_version' => 'integer', 'password' => 'hashed', 'two_factor_confirmed_at' => 'datetime'];
    }
}
