<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A teacher's source material and everything AI derives from it.
 *
 * The lifecycle lives in {@see $workflow_state}:
 *
 *   draft ──► ai_processing ──► ai_completed ──► submitted ──► under_review
 *                    │                                              │
 *                    └──► ai_failed                                 ├──► approved ──► published
 *                                                                   ├──► changes_requested ──► submitted
 *                                                                   └──► rejected
 *
 * `status` / `review_status` / `published` are kept in sync on transition so
 * older queries keep working, but `workflow_state` is the source of truth.
 */
class Material extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'class_arm_id', 'subject_id', 'title', 'description',
        'type', 'source_url', 'storage_url', 'file_path', 'file_name',
        'file_type', 'file_size', 'content', 'transcript', 'generation_config',
        'status', 'workflow_state', 'review_status', 'review_notes',
        'published', 'published_at', 'ai_processed_at', 'submitted_at',
        'reviewed_at', 'reviewed_by', 'created_by',
    ];

    protected $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
        'ai_processed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'generation_config' => 'array',
        'file_size' => 'integer',
    ];

    // ── Legacy status columns (kept in sync, no longer authoritative) ──

    const STATUS_DRAFT = 'draft';

    const STATUS_PROCESSING = 'processing';

    const STATUS_READY = 'ready';

    const STATUS_FAILED = 'failed';

    const REVIEW_PENDING = 'pending';

    const REVIEW_APPROVED = 'approved';

    const REVIEW_REJECTED = 'rejected';

    // ── Workflow states ──

    const STATE_DRAFT = 'draft';

    const STATE_AI_PROCESSING = 'ai_processing';

    const STATE_AI_COMPLETED = 'ai_completed';

    const STATE_AI_FAILED = 'ai_failed';

    const STATE_SUBMITTED = 'submitted';

    const STATE_UNDER_REVIEW = 'under_review';

    const STATE_CHANGES_REQUESTED = 'changes_requested';

    const STATE_APPROVED = 'approved';

    const STATE_REJECTED = 'rejected';

    const STATE_PUBLISHED = 'published';

    /**
     * Legal transitions. Anything not listed is rejected by
     * {@see canTransitionTo()} — this is what stops a draft being published
     * without ever passing review.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATE_DRAFT => [self::STATE_AI_PROCESSING, self::STATE_SUBMITTED],
        self::STATE_AI_PROCESSING => [self::STATE_AI_COMPLETED, self::STATE_AI_FAILED],
        self::STATE_AI_COMPLETED => [self::STATE_SUBMITTED, self::STATE_AI_PROCESSING, self::STATE_DRAFT],
        self::STATE_AI_FAILED => [self::STATE_AI_PROCESSING, self::STATE_DRAFT],
        self::STATE_SUBMITTED => [self::STATE_UNDER_REVIEW, self::STATE_APPROVED, self::STATE_CHANGES_REQUESTED, self::STATE_REJECTED],
        self::STATE_UNDER_REVIEW => [self::STATE_APPROVED, self::STATE_CHANGES_REQUESTED, self::STATE_REJECTED],
        self::STATE_CHANGES_REQUESTED => [self::STATE_SUBMITTED, self::STATE_AI_PROCESSING, self::STATE_DRAFT],
        self::STATE_APPROVED => [self::STATE_PUBLISHED, self::STATE_CHANGES_REQUESTED],
        self::STATE_REJECTED => [self::STATE_DRAFT, self::STATE_SUBMITTED],
        self::STATE_PUBLISHED => [self::STATE_APPROVED, self::STATE_CHANGES_REQUESTED],
    ];

    /** Human labels for the UI. */
    public const STATE_LABELS = [
        self::STATE_DRAFT => 'Draft',
        self::STATE_AI_PROCESSING => 'AI processing',
        self::STATE_AI_COMPLETED => 'AI complete',
        self::STATE_AI_FAILED => 'AI failed',
        self::STATE_SUBMITTED => 'Submitted',
        self::STATE_UNDER_REVIEW => 'Under review',
        self::STATE_CHANGES_REQUESTED => 'Changes requested',
        self::STATE_APPROVED => 'Approved',
        self::STATE_REJECTED => 'Rejected',
        self::STATE_PUBLISHED => 'Published',
    ];

    // ── Relations ──

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class, 'class_arm_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function studyGuide(): HasOne
    {
        return $this->hasOne(StudyGuide::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(MaterialImage::class);
    }

    public function topic(): HasOne
    {
        return $this->hasOne(Topic::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SubmissionNote::class)->latest();
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    // ── Scopes ──

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('workflow_state', self::STATE_PUBLISHED);
    }

    /** Waiting on an admin: submitted or actively being reviewed. */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->whereIn('workflow_state', [self::STATE_SUBMITTED, self::STATE_UNDER_REVIEW]);
    }

    /** Back in the teacher's court. */
    public function scopeNeedsTeacherAction(Builder $query): Builder
    {
        return $query->whereIn('workflow_state', [
            self::STATE_DRAFT, self::STATE_AI_COMPLETED,
            self::STATE_AI_FAILED, self::STATE_CHANGES_REQUESTED,
        ]);
    }

    // ── Workflow ──

    public function canTransitionTo(string $state): bool
    {
        return in_array($state, self::TRANSITIONS[$this->workflow_state] ?? [], true);
    }

    /**
     * Move to a new state, keeping the legacy columns consistent.
     *
     * Returns false rather than throwing when the transition is illegal, so
     * callers can surface a message instead of a 500.
     */
    public function transitionTo(string $state, ?User $actor = null): bool
    {
        if ($this->workflow_state === $state) {
            return true;
        }

        if (! $this->canTransitionTo($state)) {
            return false;
        }

        $attributes = ['workflow_state' => $state] + $this->legacyColumnsFor($state);

        match ($state) {
            self::STATE_AI_COMPLETED, self::STATE_AI_FAILED => $attributes['ai_processed_at'] = now(),
            self::STATE_SUBMITTED => $attributes['submitted_at'] = now(),
            self::STATE_APPROVED, self::STATE_REJECTED, self::STATE_CHANGES_REQUESTED => $attributes = $attributes + [
                'reviewed_at' => now(),
                'reviewed_by' => $actor?->id,
            ],
            self::STATE_PUBLISHED => $attributes['published_at'] = now(),
            default => null,
        };

        $this->update($attributes);

        return true;
    }

    /**
     * Mirror of the new state onto the old status/review_status/published
     * columns, so pre-Phase-2 queries keep returning sane results.
     *
     * @return array<string, mixed>
     */
    private function legacyColumnsFor(string $state): array
    {
        return match ($state) {
            self::STATE_DRAFT => ['status' => self::STATUS_DRAFT, 'review_status' => self::REVIEW_PENDING, 'published' => false],
            self::STATE_AI_PROCESSING => ['status' => self::STATUS_PROCESSING, 'review_status' => self::REVIEW_PENDING, 'published' => false],
            self::STATE_AI_COMPLETED => ['status' => self::STATUS_READY, 'review_status' => self::REVIEW_PENDING, 'published' => false],
            self::STATE_AI_FAILED => ['status' => self::STATUS_FAILED, 'review_status' => self::REVIEW_PENDING, 'published' => false],
            self::STATE_SUBMITTED, self::STATE_UNDER_REVIEW => ['status' => self::STATUS_READY, 'review_status' => self::REVIEW_PENDING, 'published' => false],
            self::STATE_CHANGES_REQUESTED => ['status' => self::STATUS_READY, 'review_status' => self::REVIEW_PENDING, 'published' => false],
            self::STATE_APPROVED => ['status' => self::STATUS_READY, 'review_status' => self::REVIEW_APPROVED, 'published' => false],
            self::STATE_REJECTED => ['status' => self::STATUS_READY, 'review_status' => self::REVIEW_REJECTED, 'published' => false],
            self::STATE_PUBLISHED => ['status' => self::STATUS_READY, 'review_status' => self::REVIEW_APPROVED, 'published' => true],
            default => [],
        };
    }

    // ── Helpers ──

    public function isPublished(): bool
    {
        return $this->workflow_state === self::STATE_PUBLISHED;
    }

    public function isProcessing(): bool
    {
        return $this->workflow_state === self::STATE_AI_PROCESSING;
    }

    public function hasGeneratedContent(): bool
    {
        return $this->flashcards()->exists()
            || $this->questions()->exists()
            || $this->studyGuide()->exists();
    }

    public function stateLabel(): string
    {
        return self::STATE_LABELS[$this->workflow_state] ?? ucfirst((string) $this->workflow_state);
    }

    /** Badge tone for the UI. */
    public function stateTone(): string
    {
        return match ($this->workflow_state) {
            self::STATE_PUBLISHED, self::STATE_APPROVED => 'ok',
            self::STATE_AI_FAILED, self::STATE_REJECTED => 'danger',
            self::STATE_CHANGES_REQUESTED, self::STATE_SUBMITTED, self::STATE_UNDER_REVIEW => 'warn',
            default => '',
        };
    }

    /** The text AI should work from, in order of preference. */
    public function sourceText(): string
    {
        return trim((string) ($this->content ?: $this->transcript ?: $this->description ?: ''));
    }
}
