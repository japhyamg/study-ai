<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingJob extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'type', 'status', 'school_id', 'material_id', 'exam_id',
        'input_url', 'input_text', 'progress', 'error', 'result',
        'created_by', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'integer',
    ];

    const TYPE_EXTRACT = 'extract_content';
    const TYPE_FLASHCARDS = 'generate_flashcards';
    const TYPE_QUESTIONS = 'generate_questions';
    const TYPE_STUDY_GUIDE = 'generate_study_guide';
    const TYPE_ALL = 'generate_all';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
