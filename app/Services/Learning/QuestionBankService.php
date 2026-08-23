<?php

namespace App\Services\Learning;

use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\DB;

/**
 * Copies approved quiz questions into the subject's question bank.
 *
 * A teacher generates a quiz per study guide, so questions arrive a topic at a
 * time. The bank collects them by subject, which is the unit an exam is
 * actually built from — by the end of a term a Maths teacher has every
 * question they have ever had approved, grouped by the guide it came from.
 *
 * Banking happens on approval rather than generation on purpose: unreviewed AI
 * output is exactly what should not silently accumulate into a pool people
 * later trust.
 */
class QuestionBankService
{
    /**
     * The generator and the bank use different vocabularies for the same
     * ideas. Translating at the boundary keeps `question_bank.type` meaningful
     * for questions that were entered by hand.
     */
    private const TYPE_MAP = [
        'multiple-choice' => QuestionBank::TYPE_MCQ,
        'true-false' => QuestionBank::TYPE_TRUE_FALSE,
        'fill-blank' => QuestionBank::TYPE_FILL_BLANK,
        'short-answer' => QuestionBank::TYPE_SHORT_ANSWER,
        'essay' => QuestionBank::TYPE_ESSAY,
    ];

    /**
     * Bank every question on a material.
     *
     * Idempotent: `source_question_id` is unique, so approving a material more
     * than once — unpublish, revise, approve again — tops the bank up with
     * whatever is new instead of duplicating what is already there.
     *
     * @return int how many questions were added
     */
    public function bankFor(Material $material): int
    {
        // Without a subject there is no bank to file under. An exam is built
        // for a subject, so an unassigned material has nowhere to go.
        if (! $material->subject_id) {
            return 0;
        }

        $questions = $material->questions()->get();

        if ($questions->isEmpty()) {
            return 0;
        }

        $alreadyBanked = QuestionBank::whereIn('source_question_id', $questions->pluck('id'))
            ->pluck('source_question_id')
            ->all();

        $new = $questions->reject(fn (Question $q) => in_array($q->id, $alreadyBanked, true));

        if ($new->isEmpty()) {
            return 0;
        }

        $rows = $new->map(fn (Question $question) => $this->rowFor($material, $question))->all();

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                QuestionBank::create($row);
            }
        });

        return count($rows);
    }

    /**
     * How many of a material's questions are already banked.
     *
     * Used to tell a reviewer what approving will add, rather than making them
     * guess.
     */
    public function pendingCount(Material $material): int
    {
        if (! $material->subject_id) {
            return 0;
        }

        $ids = $material->questions()->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return $ids->count() - QuestionBank::whereIn('source_question_id', $ids)->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFor(Material $material, Question $question): array
    {
        $options = array_values((array) $question->options);
        $correctIndex = (int) $question->correct_idx;

        return [
            'school_id' => $material->school_id,
            'subject_id' => $material->subject_id,
            'material_id' => $material->id,
            'source_question_id' => $question->id,
            // Denormalised: the bank has to outlive the material it came from.
            'topic' => $material->title,
            'question' => (string) $question->question,
            'type' => self::TYPE_MAP[$question->type ?? 'multiple-choice'] ?? QuestionBank::TYPE_MCQ,
            'options' => $options,
            // The bank stores the answer text, not an index — options can be
            // shuffled or edited independently once banked, and an index into
            // a list that has since changed points at the wrong answer.
            'answer' => $options[$correctIndex] ?? '',
            'explanation' => $question->explanation,
            'difficulty' => (int) ($question->difficulty ?: 1),
            'tags' => array_values((array) ($question->tags ?? [])),
            'created_by' => $material->created_by,
        ];
    }
}
