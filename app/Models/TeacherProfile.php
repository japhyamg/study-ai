<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Role profile for a teacher. */
class TeacherProfile extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'school_id', 'staff_number', 'title', 'department',
        'qualification', 'specialisations', 'hired_on', 'bio',
        'office_hours', 'employment_type',
    ];

    protected function casts(): array
    {
        return [
            'specialisations' => 'array',
            'hired_on' => 'date',
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

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'teacher_id', 'user_id');
    }

    public function displayName(): string
    {
        return trim(($this->title ? $this->title.' ' : '').($this->user?->name ?? ''));
    }

    public function roleLabel(): string
    {
        return $this->department ? 'Teacher · '.$this->department : 'Teacher';
    }
}
