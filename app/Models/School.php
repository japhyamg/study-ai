<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant. Reached at {subdomain}.{app.domain} in production, or via the
 * ?tenant= / path fallback in local + preview environments.
 */
class School extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'name', 'slug', 'subdomain', 'domain', 'logo', 'status', 'primary_color',
        'contact_email', 'phone', 'timezone', 'address', 'settings', 'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    // ── Relations ──

    public function members(): HasMany
    {
        return $this->hasMany(SchoolMember::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function adminProfiles(): HasMany
    {
        return $this->hasMany(AdminProfile::class);
    }

    public function teacherProfiles(): HasMany
    {
        return $this->hasMany(TeacherProfile::class);
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class);
    }

    public function questionBank(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }

    // ── Scopes ──

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /** Canonical absolute URL for this tenant. */
    public function url(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');

        if ($this->domain) {
            return rtrim(config('tenancy.scheme', 'https').'://'.$this->domain, '/').$path;
        }

        $baseDomain = config('tenancy.domain');

        // No wildcard DNS locally / in preview — fall back to a query parameter.
        if (! $baseDomain || config('tenancy.path_fallback')) {
            return rtrim(config('app.url'), '/').$path
                .(str_contains($path, '?') ? '&' : '?').'tenant='.$this->subdomain;
        }

        return config('tenancy.scheme', 'https').'://'.$this->subdomain.'.'.$baseDomain.$path;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: 'S';
    }
}
