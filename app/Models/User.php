<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\Tenancy\Tenant;

/**
 * Single authentication identity for every human on the platform.
 *
 * ── Why one `users` table + separate role tables? ──────────────────────────
 * All user types log in through the SAME route (one credential store), while
 * role-specific data and school membership live in dedicated tables:
 *
 *   platform_admins  → super-admins of the SaaS (main domain)
 *   school_admins    → school administrators   (school subdomain)
 *   teachers         → teachers                (school subdomain)
 *   students         → students                (school subdomain)
 *
 * This gives clean separation of concerns (per-type columns, per-type
 * relations, per-type rules) without the cost of four auth guards, four
 * password-resets and four login routes. See docs/ARCHITECTURE.md.
 */
class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_STUDENT = 'student';

    protected $fillable = [
        'name', 'email', 'password', 'image',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /** @var array<string>|null Cached school ids for this request. */
    protected ?array $schoolIdsCache = null;

    /** @var string|null|false Cached highest role for this request (false = not resolved). */
    protected string|null|false $roleCache = false;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ── Data relations ──

    public function flashcards(): HasMany { return $this->hasMany(Flashcard::class); }
    public function examAttempts(): HasMany { return $this->hasMany(ExamAttempt::class); }
    public function topics(): HasMany { return $this->hasMany(Topic::class); }
    public function enrollments(): HasMany { return $this->hasMany(ClassEnrollment::class); }
    public function assignedClasses(): HasMany { return $this->hasMany(ClassModel::class, 'teacher_id'); }

    // ── Role profile relations (the per-type tables) ──

    public function platformAdmin(): HasMany { return $this->hasMany(PlatformAdmin::class); }
    public function schoolAdmins(): HasMany { return $this->hasMany(SchoolAdmin::class); }
    public function teacherProfiles(): HasMany { return $this->hasMany(Teacher::class); }
    public function studentProfiles(): HasMany { return $this->hasMany(Student::class); }

    // ── Role helpers ──

    /**
     * Highest role across all profiles (priority: super_admin > admin > teacher > student).
     * The result is cached for the request because it is hit by middleware,
     * policies, views and controllers.
     */
    public function highestRole(): ?string
    {
        if ($this->roleCache !== false) {
            return $this->roleCache;
        }

        return $this->roleCache =
            ($this->relationLoaded('platformAdmin') ? $this->platformAdmin->isNotEmpty() : $this->platformAdmin()->exists()) ? self::ROLE_SUPER_ADMIN :
            (($this->relationLoaded('schoolAdmins') ? $this->schoolAdmins->isNotEmpty() : $this->schoolAdmins()->exists()) ? self::ROLE_ADMIN :
            (($this->relationLoaded('teacherProfiles') ? $this->teacherProfiles->isNotEmpty() : $this->teacherProfiles()->exists()) ? self::ROLE_TEACHER :
            (($this->relationLoaded('studentProfiles') ? $this->studentProfiles->isNotEmpty() : $this->studentProfiles()->exists()) ? self::ROLE_STUDENT : null)));
    }

    public function isSuperAdmin(): bool { return $this->highestRole() === self::ROLE_SUPER_ADMIN; }
    public function isAdmin(): bool { return in_array($this->highestRole(), [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true); }
    public function isTeacher(): bool { return $this->highestRole() === self::ROLE_TEACHER; }
    public function isStudent(): bool { return $this->highestRole() === self::ROLE_STUDENT; }

    /** Human label for the UI ("Super admin", "Teacher", …). */
    public function roleLabel(): string
    {
        return match ($this->highestRole()) {
            self::ROLE_SUPER_ADMIN => 'Super admin',
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_TEACHER => 'Teacher',
            self::ROLE_STUDENT => 'Student',
            default => 'No role',
        };
    }

    // ── Tenancy helpers ──

    /** All school ids this user belongs to (via any profile table). Cached per request. */
    public function schoolIds(): array
    {
        if ($this->schoolIdsCache !== null) {
            return $this->schoolIdsCache;
        }

        $ids = collect()
            ->merge($this->schoolAdmins()->pluck('school_id'))
            ->merge($this->teacherProfiles()->pluck('school_id'))
            ->merge($this->studentProfiles()->pluck('school_id'))
            ->unique()
            ->values()
            ->all();

        return $this->schoolIdsCache = $ids;
    }

    public function belongsToSchool(string $schoolId): bool
    {
        return in_array($schoolId, $this->schoolIds(), true);
    }

    /**
     * The active school for this request:
     *  1. the school resolved from the subdomain (tenant),
     *  2. a session-picked school the user belongs to,
     *  3. the user's first profile school (admins before teachers before students).
     */
    public function currentSchool(): ?School
    {
        $tenant = Tenant::school();
        if ($tenant && ($this->isSuperAdmin() || $this->belongsToSchool($tenant->id))) {
            return $tenant;
        }

        $schoolId = session('active_school_id');
        if ($schoolId && $this->belongsToSchool($schoolId)) {
            return School::find($schoolId);
        }

        $profile = $this->schoolAdmins()->with('school')->first()
            ?? $this->teacherProfiles()->with('school')->first()
            ?? $this->studentProfiles()->with('school')->first();

        return $profile?->school;
    }

    /** All schools this user belongs to (for switchers / pickers). */
    public function schools(): Collection
    {
        return School::whereIn('id', $this->schoolIds())->orderBy('name')->get();
    }

    // ── Two-factor helpers ──

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    public function twoFactorRecoveryCodes(): array
    {
        return $this->two_factor_recovery_codes ?? [];
    }

    /** Consume a recovery code during the 2FA challenge. Returns false when invalid. */
    public function useRecoveryCode(string $code): bool
    {
        $code = strtolower(trim($code));
        $codes = $this->twoFactorRecoveryCodes();

        $index = array_search($code, array_map(fn ($c) => strtolower(trim($c)), $codes), true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }
}
