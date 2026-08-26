<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const CATEGORY_CORE = 'core';

    public const CATEGORY_ELECTIVE = 'elective';

    public const CATEGORY_VOCATIONAL = 'vocational';

    protected $fillable = [
        'school_id', 'name', 'code', 'category', 'applies_to', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'applies_to' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClassSubjectAssignment::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether this subject is taught at a given class level code. */
    public function appliesToLevel(string $levelCode): bool
    {
        // An empty applies_to means "every level".
        return empty($this->applies_to) || in_array($levelCode, $this->applies_to, true);
    }

    public function label(): string
    {
        return $this->code ? "{$this->name} ({$this->code})" : $this->name;
    }
}
