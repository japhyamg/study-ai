<?php

namespace App\Services\Learning;

use App\Models\Material;
use App\Models\Topic;
use App\Models\TopicLink;
use App\Services\AiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the topic graph.
 *
 * After a material is processed we create (or refresh) its topic, then ask the
 * model which of the school's existing topics in the same subject are
 * prerequisites, related, or follow-ups.
 *
 * Two rules keep the graph trustworthy:
 *   - suggestions are matched back to real topics by name; anything the model
 *     invented is dropped rather than created
 *   - teacher-made links are never overwritten by a regeneration
 */
class TopicLinkerService
{
    public function __construct(private AiService $ai) {}

    /**
     * Create/refresh the topic for a material and link it into the graph.
     *
     * Link failures are logged and swallowed: a missing edge should never fail
     * the material's generation job.
     *
     * @return array{topic: Topic, links_created: int}
     */
    public function link(Material $material): array
    {
        $topic = $this->upsertTopic($material);

        $candidates = Topic::where('school_id', $material->school_id)
            ->where('subject_id', $material->subject_id)
            ->whereKeyNot($topic->id)
            ->get(['id', 'name']);

        if ($candidates->isEmpty()) {
            return ['topic' => $topic, 'links_created' => 0];
        }

        try {
            $suggestions = $this->ai->suggestTopicLinks(
                $topic->name,
                $candidates->pluck('name')->all(),
                ['userId' => $material->created_by, 'schoolId' => $material->school_id]
            );
        } catch (Throwable $e) {
            Log::warning('Topic linking failed', [
                'material_id' => $material->id,
                'error' => $e->getMessage(),
            ]);

            return ['topic' => $topic, 'links_created' => 0];
        }

        return [
            'topic' => $topic,
            'links_created' => $this->persistLinks($topic, $candidates, $suggestions),
        ];
    }

    /**
     * Replace this topic's AI-suggested outgoing edges with a fresh set.
     *
     * @param  \Illuminate\Support\Collection<int, Topic>  $candidates
     * @param  list<array{name: string, relationship_type: string, confidence_score: float}>  $suggestions
     */
    private function persistLinks($topic, $candidates, array $suggestions): int
    {
        // Case-insensitive lookup: models rarely echo capitalisation exactly.
        $byName = $candidates->keyBy(fn (Topic $t) => Str::lower($t->name));
        $created = 0;

        DB::transaction(function () use ($topic, $byName, $suggestions, &$created) {
            // Regeneration replaces AI guesses but leaves manual edges alone.
            TopicLink::where('topic_id', $topic->id)
                ->where('is_manual', false)
                ->delete();

            foreach ($suggestions as $suggestion) {
                $match = $byName->get(Str::lower($suggestion['name']));

                if (! $match || $match->id === $topic->id) {
                    continue;
                }

                $link = TopicLink::firstOrCreate(
                    [
                        'topic_id' => $topic->id,
                        'linked_topic_id' => $match->id,
                        'relationship_type' => $suggestion['relationship_type'],
                    ],
                    [
                        'confidence_score' => $suggestion['confidence_score'],
                        'is_manual' => false,
                    ]
                );

                if ($link->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return $created;
    }

    /** A teacher-drawn edge: full confidence, protected from regeneration. */
    public function manualLink(Topic $from, Topic $to, string $relationship = TopicLink::TYPE_RELATED): TopicLink
    {
        $link = TopicLink::updateOrCreate(
            [
                'topic_id' => $from->id,
                'linked_topic_id' => $to->id,
                'relationship_type' => $relationship,
            ],
            ['confidence_score' => 1.0, 'is_manual' => true]
        );

        return $link;
    }

    public function removeLink(Topic $from, Topic $to, ?string $relationship = null): void
    {
        TopicLink::where('topic_id', $from->id)
            ->where('linked_topic_id', $to->id)
            ->when($relationship, fn ($q) => $q->where('relationship_type', $relationship))
            ->delete();
    }

    private function upsertTopic(Material $material): Topic
    {
        return Topic::updateOrCreate(
            ['material_id' => $material->id],
            [
                'school_id' => $material->school_id,
                'subject_id' => $material->subject_id,
                'user_id' => $material->created_by,
                'name' => $material->title,
                'slug' => Str::slug($material->title),
                'description' => $material->description,
                'content' => Str::limit($material->sourceText(), 2000),
            ]
        );
    }
}
