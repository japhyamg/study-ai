<?php

namespace App\Models;

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
        'school_id', 'subject_id', 'question', 'type', 'options',
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

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
}
