<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'exam_id', 'user_id', 'score', 'max_score', 'percentage',
        'passed', 'start_time', 'end_time', 'submitted', 'answers',
    ];

    protected $casts = [
        'answers' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'submitted' => 'boolean',
        'passed' => 'boolean',
        'score' => 'float',
        'max_score' => 'float',
        'percentage' => 'float',
    ];

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
