<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Material extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'school_id', 'class_id', 'subject_id', 'title', 'description',
        'type', 'source_url', 'storage_url', 'content', 'transcript',
        'status', 'review_status', 'published', 'published_at', 'created_by',
    ];

    protected $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_FAILED = 'failed';

    const REVIEW_PENDING = 'pending';
    const REVIEW_APPROVED = 'approved';
    const REVIEW_REJECTED = 'rejected';

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function class(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function classRoom(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function flashcards(): HasMany { return $this->hasMany(Flashcard::class); }
    public function questions(): HasMany { return $this->hasMany(Question::class); }
    public function studyGuide(): HasOne { return $this->hasOne(StudyGuide::class); }
    public function images(): HasMany { return $this->hasMany(MaterialImage::class); }
}
