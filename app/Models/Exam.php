<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'school_id', 'class_arm_id', 'subject_id', 'title', 'description',
        'status', 'duration', 'pass_mark', 'shuffle_questions', 'shuffle_options',
        'negative_marking', 'max_attempts', 'start_time', 'end_time',
        'show_results', 'published_at', 'created_by',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'published_at' => 'datetime',
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'show_results' => 'boolean',
        'duration' => 'integer',
        'max_attempts' => 'integer',
        'pass_mark' => 'float',
        'negative_marking' => 'float',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function classArm(): BelongsTo { return $this->belongsTo(ClassArm::class, 'class_arm_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function questions(): HasMany { return $this->hasMany(ExamQuestion::class); }
    public function attempts(): HasMany { return $this->hasMany(ExamAttempt::class); }
}
