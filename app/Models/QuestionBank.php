<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBank extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'question_bank';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'school_id', 'subject_id', 'material_id', 'source_question_id', 'topic',
        'question', 'type', 'options',
        'answer', 'explanation', 'difficulty', 'tags', 'created_by',
    ];

    protected $casts = [
        'options' => 'array',
        'tags' => 'array',
        'difficulty' => 'integer',
    ];

    const TYPE_MCQ = 'mcq';
    const TYPE_TRUE_FALSE = 'true_false';
    const TYPE_FILL_BLANK = 'fill_blank';
    const TYPE_SHORT_ANSWER = 'short_answer';
    const TYPE_ESSAY = 'essay';

    /** Every question type the bank and exams accept. */
    public static function types(): array
    {
        return [
            self::TYPE_MCQ,
            self::TYPE_TRUE_FALSE,
            self::TYPE_FILL_BLANK,
            self::TYPE_SHORT_ANSWER,
            self::TYPE_ESSAY,
        ];
    }

    public function school(): BelongsTo { return $this->belongsTo(School::class); }

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }

    /** Null once the study guide is deleted; `topic` keeps the label. */
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }

    /**
     * Restrict to the subjects a teacher actually teaches.
     *
     * A bank is a subject's accumulated work, so access follows the subject
     * assignment: a Maths teacher sees every Maths question their school has
     * approved, and nothing from Chemistry. Admins are not scoped — they
     * oversee the whole school.
     */
    public function scopeForTeacher(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn(
            'subject_id',
            ClassSubjectAssignment::where('teacher_id', $user->id)->select('subject_id')
        );
    }
}
