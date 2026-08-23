<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Role profile for a student. */
class StudentProfile extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRADUATED = 'graduated';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id', 'school_id', 'admission_number', 'grade_level', 'section',
        'date_of_birth', 'gender', 'guardian_name', 'guardian_phone',
        'guardian_email', 'address', 'enrolled_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'enrolled_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'user_id', 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function classLabel(): string
    {
        return trim(($this->grade_level ?? '').' '.($this->section ?? '')) ?: '—';
    }

    public function roleLabel(): string
    {
        return $this->grade_level ? 'Student · '.$this->classLabel() : 'Student';
    }
}
