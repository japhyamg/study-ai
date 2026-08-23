<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\Material;
use App\Models\ProcessingJob;
use App\Models\Question;
use App\Models\StudyGuide;
use App\Services\Learning\TopicLinkerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a material through the AI pipeline and writes the results as rows.
 *
 * Always invoked from a queued job ({@see \App\Jobs\GenerateAiContent}) — the
 * calls take tens of seconds and must never block a request.
 *
 * Generation is idempotent per type: re-running replaces that type's rows
 * rather than appending, so a teacher who regenerates twice does not end up
 * with 40 duplicate flashcards.
 */
class AiContentService
{
    public function __construct(
        private AiService $ai,
        private SrsService $srs,
        private TopicLinkerService $topicLinker,
    ) {}

    /**
     * Run a processing job end to end. `$job->type` decides what is generated.
     */
    public function runJob(ProcessingJob $job): void
    {
        $material = $job->material;

        if (! $material) {
            $job->update([
                'status' => ProcessingJob::STATUS_FAILED,
                'error' => 'Material not found.',
                'completed_at' => now(),
            ]);

            return;
        }

        $job->update([
            'status' => ProcessingJob::STATUS_PROCESSING,
            'started_at' => now(),
            'progress' => 5,
        ]);

        $material->transitionTo(Material::STATE_AI_PROCESSING);

        $content = $material->sourceText();

        if ($content === '') {
            $this->failJob(
                $job,
                $material,
                'This material has no text to work from. Upload a file with selectable text, or paste the content in.'
            );

            return;
        }

        $context = ['userId' => $job->created_by, 'schoolId' => $job->school_id];
        $options = $job->result ?? [];
        $result = ['types' => []];

        try {
            $type = $job->type;
            $wantsAll = $type === ProcessingJob::TYPE_ALL;

            if ($wantsAll || $type === ProcessingJob::TYPE_FLASHCARDS) {
                $job->update(['progress' => 25]);
                $cards = $this->ai->generateStudyContent($content, 'flashcards', [], $context);
                $result['flashcard_count'] = $this->saveFlashcards($material, $cards);
                $result['types'][] = 'flashcards';
            }

            if ($wantsAll || $type === ProcessingJob::TYPE_QUESTIONS) {
                $job->update(['progress' => 50]);
                $questions = $this->ai->generateStudyContent($content, 'questions', [
                    'questionCount' => $options['questionCount'] ?? config('ai.defaults.question_count', 10),
                    'questionTypes' => $options['questionTypes'] ?? config('ai.defaults.question_types', ['multiple-choice']),
                ], $context);
                $result['question_count'] = $this->saveQuestions($material, $questions);
                $result['types'][] = 'questions';
            }

            if ($wantsAll || $type === ProcessingJob::TYPE_STUDY_GUIDE) {
                $job->update(['progress' => 75]);
                $guide = $this->ai->generateStudyGuide($content, $context);
                $this->saveStudyGuide($material, $guide);
                $result['types'][] = 'study_guide';
            }

            // Topic linking is a nice-to-have; never let it fail the job.
            $job->update(['progress' => 90]);
            $result['links_created'] = $this->linkTopics($material);

            $material->transitionTo(Material::STATE_AI_COMPLETED);

            $job->update([
                'status' => ProcessingJob::STATUS_COMPLETED,
                'progress' => 100,
                'result' => $result,
                'completed_at' => now(),
            ]);
        } catch (TokenLimitError $e) {
            // Distinguish "out of budget" from "the model broke" — the teacher
            // can act on the first and not the second.
            $this->failJob($job, $material, $e->getMessage());
        } catch (Throwable $e) {
            Log::error('AI generation failed', [
                'material_id' => $material->id,
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            $this->failJob($job, $material, $e->getMessage());
        }
    }

    private function failJob(ProcessingJob $job, Material $material, string $error): void
    {
        $material->transitionTo(Material::STATE_AI_FAILED);

        $job->update([
            'status' => ProcessingJob::STATUS_FAILED,
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    private function linkTopics(Material $material): int
    {
        try {
            return $this->topicLinker->link($material)['links_created'];
        } catch (Throwable $e) {
            Log::warning('Topic linking skipped', [
                'material_id' => $material->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    // ───────────────────────── persistence ─────────────────────────

    /**
     * @param  array<mixed>|null  $flashcards
     */
    public function saveFlashcards(Material $material, ?array $flashcards): int
    {
        if (! is_array($flashcards)) {
            return 0;
        }

        // A model sometimes wraps the array in {"flashcards": [...]}.
        $flashcards = $flashcards['flashcards'] ?? $flashcards;
        $count = 0;

        DB::transaction(function () use ($material, $flashcards, &$count) {
            // Replace, don't append — regenerating should not duplicate.
            Flashcard::where('material_id', $material->id)->delete();

            foreach ($flashcards as $card) {
                if (! is_array($card)) {
                    continue;
                }

                $front = trim((string) ($card['front'] ?? $card['question'] ?? ''));
                $back = trim((string) ($card['back'] ?? $card['answer'] ?? ''));

                if ($front === '' || $back === '') {
                    continue;
                }

                Flashcard::create([
                    'user_id' => $material->created_by,
                    'material_id' => $material->id,
                    'front' => $front,
                    'back' => $back,
                    'tags' => $this->normaliseTags($card['tags'] ?? null),
                    'review_status' => Material::REVIEW_PENDING,
                    // SM-2 starting position: due immediately, neutral ease.
                    'ease_factor' => 2.5,
                    'interval' => 0,
                    'repetitions' => 0,
                    'lapses' => 0,
                    'due_date' => now(),
                ]);

                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<mixed>|null  $questions
     */
    public function saveQuestions(Material $material, ?array $questions): int
    {
        if (! is_array($questions)) {
            return 0;
        }

        $questions = $questions['questions'] ?? $questions;
        $count = 0;

        DB::transaction(function () use ($material, $questions, &$count) {
            Question::where('material_id', $material->id)->delete();

            foreach ($questions as $question) {
                $prepared = $this->prepareQuestion($question);

                if (! $prepared) {
                    continue;
                }

                Question::create($prepared + [
                    'material_id' => $material->id,
                    'review_status' => Material::REVIEW_PENDING,
                ]);

                $count++;
            }
        });

        return $count;
    }

    /**
     * Validate and normalise one generated question.
     *
     * Models drift on this shape constantly: options arriving as
     * {text, is_correct} objects, correctIdx pointing past the end of the
     * array, duplicate options. Anything unsalvageable is dropped rather than
     * stored as a question with no right answer.
     *
     * @return array<string, mixed>|null
     */
    private function prepareQuestion(mixed $question): ?array
    {
        if (! is_array($question)) {
            return null;
        }

        $text = trim((string) ($question['question'] ?? $question['question_text'] ?? ''));
        $rawOptions = $question['options'] ?? null;

        if ($text === '' || ! is_array($rawOptions) || $rawOptions === []) {
            return null;
        }

        $options = [];
        $correctIdx = null;

        foreach (array_values($rawOptions) as $index => $option) {
            if (is_array($option)) {
                // {"option_text": "...", "is_correct": true}
                $label = trim((string) ($option['option_text'] ?? $option['text'] ?? ''));

                if (! empty($option['is_correct'])) {
                    $correctIdx = $index;
                }
            } else {
                $label = trim((string) $option);
            }

            if ($label === '') {
                return null;
            }

            $options[] = $label;
        }

        // Fall back to the index the model reported.
        $correctIdx ??= (int) ($question['correctIdx'] ?? $question['correct_idx'] ?? 0);

        // An out-of-range index means we cannot tell which answer is right.
        if ($correctIdx < 0 || $correctIdx >= count($options)) {
            return null;
        }

        if (count(array_unique($options)) !== count($options)) {
            return null;
        }

        return [
            'question' => $text,
            'type' => (string) ($question['type'] ?? 'multiple-choice'),
            'options' => $options,
            'correct_idx' => $correctIdx,
            'explanation' => trim((string) ($question['explanation'] ?? '')),
            'difficulty' => $this->normaliseDifficulty($question['difficulty'] ?? 1),
            'tags' => $this->normaliseTags($question['tags'] ?? null),
        ];
    }

    /** Accepts either 1–5 or easy/medium/hard. */
    private function normaliseDifficulty(mixed $difficulty): int
    {
        if (is_numeric($difficulty)) {
            return max(1, min(5, (int) $difficulty));
        }

        return match (strtolower(trim((string) $difficulty))) {
            'easy', 'beginner' => 1,
            'hard', 'advanced', 'difficult' => 3,
            default => 2,
        };
    }

    /** @return list<string>|null */
    private function normaliseTags(mixed $tags): ?array
    {
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        if (! is_array($tags)) {
            return null;
        }

        $clean = [];

        foreach ($tags as $tag) {
            if (is_scalar($tag) && trim((string) $tag) !== '') {
                $clean[] = trim((string) $tag);
            }
        }

        return $clean === [] ? null : array_values(array_unique($clean));
    }

    /**
     * @param  array<string, mixed>|null  $guide
     */
    public function saveStudyGuide(Material $material, ?array $guide): void
    {
        if (! is_array($guide)) {
            return;
        }

        $sections = [];

        foreach ($guide['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? $section['title'] ?? ''));
            $body = $section['body'] ?? $section['content'] ?? '';
            $body = is_array($body) ? implode("\n", array_filter($body, 'is_scalar')) : (string) $body;

            if (trim($body) === '') {
                continue;
            }

            $sections[] = ['heading' => $heading ?: 'Section', 'body' => trim($body)];
        }

        StudyGuide::updateOrCreate(
            ['material_id' => $material->id],
            [
                'title' => $guide['title'] ?? $material->title,
                'summary' => $guide['summary'] ?? null,
                'sections' => $sections,
                'key_terms' => $this->normaliseKeyTerms($guide['keyTerms'] ?? $guide['key_terms'] ?? []),
                // Flat markdown copy, for printing and export.
                'content' => $this->renderMarkdown($guide, $sections),
            ]
        );
    }

    /** @return list<array{term: string, definition: string}> */
    private function normaliseKeyTerms(mixed $terms): array
    {
        if (! is_array($terms)) {
            return [];
        }

        $clean = [];

        foreach ($terms as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $term = trim((string) ($entry['term'] ?? ''));
            $definition = trim((string) ($entry['definition'] ?? ''));

            if ($term !== '' && $definition !== '') {
                $clean[] = ['term' => $term, 'definition' => $definition];
            }
        }

        return $clean;
    }

    /** @param list<array{heading: string, body: string}> $sections */
    private function renderMarkdown(array $guide, array $sections): string
    {
        $lines = [];

        if ($title = $guide['title'] ?? null) {
            $lines[] = '# '.$title;
        }

        if ($summary = $guide['summary'] ?? null) {
            $lines[] = '';
            $lines[] = $summary;
        }

        foreach ($sections as $section) {
            $lines[] = '';
            $lines[] = '## '.$section['heading'];
            $lines[] = '';
            $lines[] = $section['body'];
        }

        return implode("\n", $lines);
    }
}
