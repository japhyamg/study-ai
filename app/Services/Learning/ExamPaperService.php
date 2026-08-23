<?php

namespace App\Services\Learning;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use Illuminate\Support\Collection;

/**
 * Builds the paper a student sits, and decides what counts as correct.
 *
 * Shuffling and grading are the same concern, so they live together. The
 * moment options can be reordered, an answer recorded as "option 2" stops
 * meaning anything, so everything here works in answer *text* and position is
 * treated as presentation only.
 */
class ExamPaperService
{
    /**
     * The questions for one attempt, in the order that attempt should see them.
     *
     * The order is shuffled from a seed derived from the attempt, never from
     * random(): taking an exam is a GET the student can refresh, reach with the
     * back button, or reopen after a dropped connection, and a paper that
     * reshuffles underneath them would lose their place every time.
     */
    public function questionsFor(Exam $exam, ExamAttempt $attempt): Collection
    {
        $questions = $exam->questions()->orderBy('order')->get();

        $seed = crc32((string) $attempt->id);

        if ($exam->shuffle_questions) {
            $questions = $this->seededShuffle($questions->all(), $seed);
        }

        if ($exam->shuffle_options) {
            $questions->each(function (ExamQuestion $question, int $i) use ($seed) {
                $options = $question->options;

                if (! is_array($options) || count($options) < 2) {
                    return;
                }

                // True/false reads as a fixed pair; scrambling it to
                // "False, True" just makes the paper look broken.
                if ($question->type === 'true_false') {
                    return;
                }

                // Offset per question so every question in one paper gets a
                // different permutation rather than all the same one.
                $question->setAttribute(
                    'options',
                    $this->seededShuffle($options, $seed + ($i * 31))->all()
                );
            });
        }

        return $questions;
    }

    /**
     * The correct answer as text.
     *
     * Questions banked by the approval flow store the answer text, but older
     * hand-written ones stored the index of the correct option. Both have to
     * keep grading correctly, so a numeric answer that lands inside the option
     * list is read as an index and resolved.
     */
    public function correctAnswer(ExamQuestion $question): string
    {
        $answer = (string) $question->answer;
        $options = is_array($question->options) ? array_values($question->options) : [];

        if ($options !== [] && is_numeric($answer)) {
            $index = (int) $answer;

            // Only treat it as an index when the text itself is not one of the
            // options — a question whose options are years would otherwise have
            // "2024" resolved to the wrong entry.
            if (! in_array($answer, $options, true) && array_key_exists($index, $options)) {
                return (string) $options[$index];
            }
        }

        return $answer;
    }

    /**
     * Whether a submitted response is correct.
     *
     * Choice answers must match an option exactly. Written answers are compared
     * with surrounding whitespace and case ignored, because "  Paris" and
     * "paris" are the same answer to everyone except a string comparison.
     */
    public function isCorrect(ExamQuestion $question, ?string $given): bool
    {
        if ($given === null || $given === '') {
            return false;
        }

        $correct = $this->correctAnswer($question);

        if ($correct === '') {
            return false;
        }

        $choiceBased = is_array($question->options) && $question->options !== [];

        if ($choiceBased) {
            return $given === $correct;
        }

        return mb_strtolower(trim($given)) === mb_strtolower(trim($correct));
    }

    /**
     * Fisher-Yates driven by a seeded generator.
     *
     * shuffle() and Collection::shuffle() reseed globally, so they cannot give
     * the same order twice for the same attempt.
     */
    private function seededShuffle(array $items, int $seed): Collection
    {
        $items = array_values($items);
        $state = $seed !== 0 ? abs($seed) : 1;

        for ($i = count($items) - 1; $i > 0; $i--) {
            // Lehmer / Park-Miller: deterministic, no global state touched.
            $state = ($state * 48271) % 2147483647;
            $j = $state % ($i + 1);

            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return collect($items);
    }
}
