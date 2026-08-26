<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A directed edge between two topics.
 *
 * `confidence_score` is the AI's own estimate (0.00–1.00); a teacher-created
 * link is stored at 1.00 with is_manual = true so human judgement always sorts
 * above a suggestion.
 */
class TopicLink extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'topic_id', 'linked_topic_id', 'relationship_type',
        'confidence_score', 'is_manual',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'is_manual' => 'boolean',
    ];

    const TYPE_PREREQUISITE = 'prerequisite';

    const TYPE_RELATED = 'related';

    const TYPE_FOLLOW_UP = 'follow_up';

    public const TYPES = [self::TYPE_PREREQUISITE, self::TYPE_RELATED, self::TYPE_FOLLOW_UP];

    public const TYPE_LABELS = [
        self::TYPE_PREREQUISITE => 'Prerequisite',
        self::TYPE_RELATED => 'Related',
        self::TYPE_FOLLOW_UP => 'Follow-up',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function linkedTopic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'linked_topic_id');
    }

    public function label(): string
    {
        return self::TYPE_LABELS[$this->relationship_type] ?? ucfirst((string) $this->relationship_type);
    }

    /** Confidence as a rounded percentage, for display. */
    public function confidencePercent(): int
    {
        return (int) round($this->confidence_score * 100);
    }
}
