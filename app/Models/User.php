<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'email', 'password', 'image',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany { return $this->hasMany(SchoolMember::class); }
    public function flashcards(): HasMany { return $this->hasMany(Flashcard::class); }
    public function examAttempts(): HasMany { return $this->hasMany(ExamAttempt::class); }
    public function topics(): HasMany { return $this->hasMany(Topic::class); }
    public function enrollments(): HasMany { return $this->hasMany(ClassEnrollment::class); }
    public function assignedClasses(): HasMany { return $this->hasMany(ClassModel::class, 'teacher_id'); }

    // ── Role / tenancy helpers ──

    /** Highest role across all school memberships (priority: super_admin > admin > teacher > student). */
    public function highestRole(): ?string
    {
        $order = [
            SchoolMember::ROLE_SUPER_ADMIN,
            SchoolMember::ROLE_ADMIN,
            SchoolMember::ROLE_TEACHER,
            SchoolMember::ROLE_STUDENT,
        ];
        $roles = $this->memberships()->pluck('role')->unique()->all();
        foreach ($order as $r) {
            if (in_array($r, $roles, true)) {
                return $r;
            }
        }
        return null;
    }

    public function isSuperAdmin(): bool { return $this->highestRole() === SchoolMember::ROLE_SUPER_ADMIN; }
    public function isAdmin(): bool { return in_array($this->highestRole(), [SchoolMember::ROLE_SUPER_ADMIN, SchoolMember::ROLE_ADMIN], true); }
    public function isTeacher(): bool { return $this->highestRole() === SchoolMember::ROLE_TEACHER; }
    public function isStudent(): bool { return $this->highestRole() === SchoolMember::ROLE_STUDENT; }

    /** The active school for this session (first membership, or use a session override). */
    public function currentSchool(): ?School
    {
        $schoolId = session('active_school_id');
        if ($schoolId) {
            return $this->memberships()->where('school_id', $schoolId)->first()?->school;
        }
        return $this->memberships()->first()?->school;
    }
}
