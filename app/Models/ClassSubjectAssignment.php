<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds one teacher to one subject in one class arm.
 *
 * The (class_arm_id, subject_id) pair is unique at the database level, so a
 * subject can never end up with two teachers in the same arm.
 */
class ClassSubjectAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'class_arm_id', 'subject_id', 'teacher_id',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function label(): string
    {
        return ($this->subject?->name ?? 'Subject').' · '.($this->classArm?->fullName() ?? 'Class');
    }
}
