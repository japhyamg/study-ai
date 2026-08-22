<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\Material;
use App\Models\ProcessingJob;
use App\Models\Question;
use App\Models\StudyGuide;
use Throwable;

/**
 * Orchestrates AI content generation from a Material into DB rows
 * (flashcards, exam questions, study guide). Used by the async job + controllers.
 */
class AiContentService
{
    public function __construct(
        private AiService $ai,
        private SrsService $srs
    ) {}

    /**
     * Run a processing job end-to-end. $job->type decides what to generate.
     */
    public function runJob(ProcessingJob $job): void
    {
        $job->update([
            'status' => ProcessingJob::STATUS_PROCESSING,
            'started_at' => now(),
            'progress' => 5,
        ]);

        $material = $job->material;
        if (!$material) {
            $job->update(['status' => ProcessingJob::STATUS_FAILED, 'error' => 'Material not found', 'completed_at' => now()]);
            return;
        }

        $context = [
            'userId' => $job->created_by,
            'schoolId' => $job->school_id,
        ];

        // question settings (threaded from teacher upload form via $job->result)
        $jobOpts = $job->result ?? [];
        $questionCount = $jobOpts['questionCount'] ?? 10;
        $questionTypes = $jobOpts['questionTypes'] ?? ['multiple-choice'];

        $content = $material->content ?: $material->transcript ?: $material->description ?: '';
        $result = ['types' => []];

        try {
            $type = $job->type;

            if (in_array($type, [ProcessingJob::TYPE_FLASHCARDS, ProcessingJob::TYPE_ALL], true)) {
                $job->update(['progress' => 30]);
                $flashcards = $this->ai->generateStudyContent($content, 'flashcards', [], $context);
                $this->saveFlashcards($material, $flashcards);
                $result['types'][] = 'flashcards';
                $result['flashcard_count'] = count($flashcards ?? []);
            }

            if (in_array($type, [ProcessingJob::TYPE_QUESTIONS, ProcessingJob::TYPE_ALL], true)) {
                $job->update(['progress' => 60]);
                $questions = $this->ai->generateStudyContent($content, 'questions', [
                    'questionCount' => $questionCount,
                    'questionTypes' => $questionTypes,
                ], $context);
                $this->saveQuestions($material, $questions);
                $result['types'][] = 'questions';
                $result['question_count'] = count($questions ?? []);
            }

            if (in_array($type, [ProcessingJob::TYPE_STUDY_GUIDE, ProcessingJob::TYPE_ALL], true)) {
                $job->update(['progress' => 85]);
                $guide = $this->ai->generateStudyGuide($content, $context);
                $this->saveStudyGuide($material, $guide);
                $result['types'][] = 'study_guide';
            }

            $material->update([
                'status' => Material::STATUS_READY,
                'review_status' => Material::REVIEW_PENDING,
            ]);

            $job->update([
                'status' => ProcessingJob::STATUS_COMPLETED,
                'progress' => 100,
                'result' => $result,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $material->update(['status' => Material::STATUS_FAILED]);
            $job->update([
                'status' => ProcessingJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    public function saveFlashcards(Material $material, ?array $flashcards): int
    {
        if (!is_array($flashcards)) {
            return 0;
        }
        $count = 0;
        foreach ($flashcards as $f) {
            if (!is_array($f) || empty($f['front']) || empty($f['back'])) {
                continue;
            }
            Flashcard::create([
                'user_id' => $material->created_by,
                'material_id' => $material->id,
                'front' => $f['front'],
                'back' => $f['back'],
                'tags' => $f['tags'] ?? null,
                'review_status' => 'pending',
                'ease_factor' => 2.5,
                'interval' => 0,
                'repetitions' => 0,
                'lapses' => 0,
                'due_date' => now(),
            ]);
            $count++;
        }
        return $count;
    }

    public function saveQuestions(Material $material, ?array $questions): int
    {
        if (!is_array($questions)) {
            return 0;
        }
        $count = 0;
        foreach ($questions as $q) {
            if (!is_array($q) || empty($q['question']) || !isset($q['options']) || !is_array($q['options'])) {
                continue;
            }
            $correctIdx = (int) ($q['correctIdx'] ?? 0);
            Question::create([
                'material_id' => $material->id,
                'question' => $q['question'],
                'type' => $q['type'] ?? 'multiple-choice',
                'options' => $q['options'],
                'correct_idx' => $correctIdx,
                'explanation' => $q['explanation'] ?? null,
                'difficulty' => $q['difficulty'] ?? 1,
                'tags' => $q['tags'] ?? null,
                'review_status' => 'pending',
            ]);
            $count++;
        }
        return $count;
    }

    public function saveStudyGuide(Material $material, ?array $guide): void
    {
        if (!is_array($guide)) {
            return;
        }
        StudyGuide::updateOrCreate(
            ['material_id' => $material->id],
            ['content' => $guide]
        );
    }
}
