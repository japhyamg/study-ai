<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An actual class group — "Year 10 B". Holds students, has a capacity, a form
 * teacher, and one subject teacher per subject via {@see ClassSubjectAssignment}.
 */
class ClassArm extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'class_level_id', 'academic_session_id', 'form_teacher_id',
        'name', 'stream', 'capacity', 'description', 'invite_code',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $arm) {
            $arm->invite_code ??= static::generateInviteCode();
        });
    }

    // ── Relations ──

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function formTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'form_teacher_id');
    }

    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(ClassSubjectAssignment::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'class_arm_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'class_arm_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'class_arm_id');
    }

    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class, 'class_arm_id');
    }

    // ── Helpers ──

    /** "Year 10 B" — level name plus arm name. */
    public function fullName(): string
    {
        return trim(($this->classLevel?->name ?? '').' '.$this->name);
    }

    public function isFull(): bool
    {
        return $this->enrollments()->count() >= $this->capacity;
    }

    public function availableSeats(): int
    {
        return max(0, $this->capacity - $this->enrollments()->count());
    }

    /** The teacher responsible for a given subject in this arm. */
    public function teacherFor(string $subjectId): ?User
    {
        return $this->subjectAssignments()
            ->where('subject_id', $subjectId)
            ->first()?->teacher;
    }

    public static function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(7));
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }
}
