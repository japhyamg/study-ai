<?php

namespace App\Models;

use App\Models\Concerns\HasTwoFactorAuthentication;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A school user — administrator, teacher or student.
 *
 * One credential row (email + password + 2FA) so everybody signs in through
 * the same route on their school's subdomain. Role-specific data lives on the
 * matching profile: {@see AdminProfile}, {@see TeacherProfile},
 * {@see StudentProfile}. Platform staff are NOT here — see {@see SuperAdmin}.
 */
class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, HasTwoFactorAuthentication;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'name', 'email', 'password', 'image', 'phone',
        'locale', 'timezone', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ── Relations ──

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolMember::class);
    }

    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    /** Arms where this user is the form teacher. */
    public function assignedClasses(): HasMany
    {
        return $this->hasMany(ClassArm::class, 'form_teacher_id');
    }

    /** Per-subject teaching assignments across all arms. */
    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(ClassSubjectAssignment::class, 'teacher_id');
    }

    // ── Scopes ──

    public function scopeOfSchool(Builder $query, string $schoolId): Builder
    {
        return $query->where('users.school_id', $schoolId);
    }

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('memberships', fn ($q) => $q->where('role', $role));
    }

    // ── Role helpers ──

    /**
     * The user's role within the currently-resolved tenant.
     * Cached per request so views can call this freely.
     */
    public function roleInSchool(?string $schoolId = null): ?string
    {
        $schoolId ??= app('tenant')?->id ?? $this->school_id;

        if (! $schoolId) {
            return null;
        }

        $key = 'role_in_'.$schoolId;

        if (! array_key_exists($key, $this->cachedRoles)) {
            $this->cachedRoles[$key] = $this->memberships
                ->firstWhere('school_id', $schoolId)?->role;
        }

        return $this->cachedRoles[$key];
    }

    /** @var array<string, string|null> */
    protected array $cachedRoles = [];

    /**
     * Highest role held anywhere (admin > teacher > student).
     * Kept for backwards compatibility with existing controllers/policies.
     */
    public function highestRole(): ?string
    {
        $order = [
            SchoolMember::ROLE_ADMIN,
            SchoolMember::ROLE_TEACHER,
            SchoolMember::ROLE_STUDENT,
        ];

        // Prefer the active tenant's role when one is resolved.
        if ($role = $this->roleInSchool()) {
            return $role;
        }

        $roles = $this->memberships->pluck('role')->unique()->all();

        foreach ($order as $r) {
            if (in_array($r, $roles, true)) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Super-admins are a separate guard entirely; a school user is never one.
     * Retained so legacy `$user->isSuperAdmin()` calls stay safe.
     */
    public function isSuperAdmin(): bool
    {
        return false;
    }

    public function isAdmin(): bool
    {
        return $this->roleInSchool() === SchoolMember::ROLE_ADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->roleInSchool() === SchoolMember::ROLE_TEACHER;
    }

    public function isStudent(): bool
    {
        return $this->roleInSchool() === SchoolMember::ROLE_STUDENT;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->roleInSchool(), $roles, true);
    }

    /** The profile model matching this user's role in the active school. */
    public function profile(): AdminProfile|TeacherProfile|StudentProfile|null
    {
        return match ($this->roleInSchool()) {
            SchoolMember::ROLE_ADMIN => $this->adminProfile,
            SchoolMember::ROLE_TEACHER => $this->teacherProfile,
            SchoolMember::ROLE_STUDENT => $this->studentProfile,
            default => null,
        };
    }

    public function roleLabel(): string
    {
        return match ($this->roleInSchool()) {
            SchoolMember::ROLE_ADMIN => 'Administrator',
            SchoolMember::ROLE_TEACHER => 'Teacher',
            SchoolMember::ROLE_STUDENT => 'Student',
            default => 'Member',
        };
    }

    /** The active school for this session. */
    public function currentSchool(): ?School
    {
        if ($tenant = app()->bound('tenant') ? app('tenant') : null) {
            return $tenant;
        }

        return $this->school ?? $this->memberships->first()?->school;
    }

    public function belongsToSchool(string $schoolId): bool
    {
        return $this->school_id === $schoolId
            || $this->memberships->contains('school_id', $schoolId);
    }

    // ── Presentation ──

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: '?';
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }

    public function avatarUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, 'http')
            ? $this->image
            : asset('storage/'.ltrim($this->image, '/'));
    }
}
