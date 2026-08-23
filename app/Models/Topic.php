<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A named concept, usually derived from a material.
 *
 * Topics form a directed graph via {@see TopicLink}: "Quadratic Equations"
 * has "Algebra" as a prerequisite, which means Algebra also has Quadratic
 * Equations as an inbound edge. Both directions are queryable — the backlinks
 * are what let the student UI answer "what does this prepare me for?".
 */
class Topic extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'subject_id', 'material_id', 'user_id',
        'name', 'slug', 'description', 'content',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $topic) {
            $topic->slug = $topic->slug ?: Str::slug($topic->name);
        });
    }

    // ── Relations ──

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Edges pointing out of this topic. */
    public function links(): HasMany
    {
        return $this->hasMany(TopicLink::class, 'topic_id');
    }

    /** Edges pointing at this topic from elsewhere. */
    public function backlinks(): HasMany
    {
        return $this->hasMany(TopicLink::class, 'linked_topic_id');
    }

    // ── Scopes ──

    public function scopeForSchool(Builder $query, ?string $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    // ── Graph helpers ──

    /**
     * Topics this one builds on. Confidence-ordered so the strongest
     * suggestion is first.
     *
     * @return Collection<int, Topic>
     */
    public function prerequisites(): Collection
    {
        return $this->relatedByType(TopicLink::TYPE_PREREQUISITE);
    }

    /** @return Collection<int, Topic> */
    public function relatedTopics(): Collection
    {
        return $this->relatedByType(TopicLink::TYPE_RELATED);
    }

    /** @return Collection<int, Topic> */
    public function followUps(): Collection
    {
        return $this->relatedByType(TopicLink::TYPE_FOLLOW_UP);
    }

    /**
     * Topics that name this one as a prerequisite — i.e. what studying this
     * unlocks. This is the reverse edge, which is why it reads backlinks.
     *
     * @return Collection<int, Topic>
     */
    public function unlocks(): Collection
    {
        return $this->backlinks()
            ->where('relationship_type', TopicLink::TYPE_PREREQUISITE)
            ->with('topic')
            ->orderByDesc('confidence_score')
            ->get()
            ->pluck('topic')
            ->filter()
            ->values();
    }

    /** @return Collection<int, Topic> */
    private function relatedByType(string $type): Collection
    {
        return $this->links()
            ->where('relationship_type', $type)
            ->with('linkedTopic')
            ->orderByDesc('confidence_score')
            ->get()
            ->pluck('linkedTopic')
            ->filter()
            ->values();
    }
}
