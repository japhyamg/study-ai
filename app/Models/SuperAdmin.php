<?php

namespace App\Models;

use App\Models\Concerns\HasTwoFactorAuthentication;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Platform staff — the SaaS operator.
 *
 * Deliberately a separate table + guard (`superadmin`) from school `users`:
 * a super-admin is not a member of any school, authenticates only on the
 * main/admin domain, and never appears in a tenant's member lists.
 */
class SuperAdmin extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, HasTwoFactorAuthentication;

    protected $table = 'super_admins';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'phone', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Password reset broker table. */
    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: 'SA';
    }

    /** Uniform role label so shared views can render either principal. */
    public function roleLabel(): string
    {
        return 'Super Admin';
    }

    public function isSuperAdmin(): bool
    {
        return true;
    }
}
