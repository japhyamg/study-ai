<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A curriculum band — "Year 10", "JSS 1", "Grade 5".
 *
 * A level is what a syllabus targets and what students are promoted through.
 * The students themselves live in a {@see ClassArm} of that level.
 */
class ClassLevel extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'name', 'code', 'stage', 'position', 'description',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function arms(): HasMany
    {
        return $this->hasMany(ClassArm::class)->orderBy('name');
    }

    public function enrollments(): HasManyThrough
    {
        return $this->hasManyThrough(ClassEnrollment::class, ClassArm::class, 'class_level_id', 'class_arm_id');
    }

    /** The next level a student is promoted into, by position. */
    public function nextLevel(): ?self
    {
        return static::where('school_id', $this->school_id)
            ->where('position', '>', $this->position)
            ->orderBy('position')
            ->first();
    }

    public function studentCount(): int
    {
        return $this->enrollments()->count();
    }
}
