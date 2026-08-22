<?php

namespace App\Services;

/**
 * Spaced-repetition scheduling (FSRS + SM-2).
 * Ported from src/lib/srs/fsrs.ts and src/lib/srs/sm2.ts.
 */
class SrsService
{
    // FSRS default weights
    private const W = [1.14, 1.01, 5.13, 14.48, 6.46, 2.64, 1.0, 2.65, 2.96, 2.25, 4.70, 2.42, 2.69, 2.90, 1.35, 1.92, 0.27, 2.07, 0.28, 1.47, 0.06];
    private const REQUEST_RETENTION = 0.9;
    private const MAX_INTERVAL = 36500;
    private const EASY_BONUS = 1.3;
    private const HARD_INTERVAL = 1.2;

    public const STATE_NEW = 0;
    public const STATE_LEARNING = 1;
    public const STATE_REVIEW = 2;
    public const STATE_RELEARNING = 3;

    public const RATING_AGAIN = 1;
    public const RATING_HARD = 2;
    public const RATING_GOOD = 3;
    public const RATING_EASY = 4;

    private const DAY = 86400000;   // ms
    private const MINUTE = 60000;   // ms

    /**
     * SM-2 algorithm (Anki-style). Returns [easeFactor, intervalDays, repetitions].
     */
    public function calculateSm2(int $quality, float $easeFactor = 2.5, int $interval = 0, int $repetitions = 0): array
    {
        $newEase = max(1.3, $easeFactor + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02)));

        if ($quality < 3) {
            $newReps = 0;
            $newInterval = 1;
        } else {
            if ($repetitions === 0) {
                $newInterval = 1;
            } elseif ($repetitions === 1) {
                $newInterval = 6;
            } else {
                $newInterval = (int) round($interval * $easeFactor);
            }
            $newReps = $repetitions + 1;
        }

        return [
            'ease_factor' => round($newEase, 2),
            'interval' => $newInterval,
            'repetitions' => $newReps,
        ];
    }

    public function getNextReviewDate(int $intervalDays): \DateTime
    {
        return now()->addDays($intervalDays);
    }

    /**
     * FSRS scheduling. $card = ['ease_factor','interval','repetitions','lapses','state','due_date','last_review'].
     * $rating is one of RATING_*.
     * Returns updated card array (interval in days for storage convenience + raw ms in 'interval_ms').
     */
    public function calculateFsrs(array $card, int $rating): array
    {
        $w = self::W;
        $EF = (float) ($card['ease_factor'] ?? 2.5);
        $state = (int) ($card['state'] ?? self::STATE_NEW);
        $repetitions = (int) ($card['repetitions'] ?? 0);
        $lapses = (int) ($card['lapses'] ?? 0);
        $intervalMs = (float) (($card['interval'] ?? 0) * self::DAY); // interval stored as days -> ms
        $now = now();

        $newState = $state;
        $newEF = $EF;
        $newReps = $repetitions;
        $newLapses = $lapses;
        $newIntervalMs = $intervalMs;

        if (in_array($state, [self::STATE_NEW, self::STATE_LEARNING], true)) {
            if ($rating === self::RATING_AGAIN || $rating === self::RATING_HARD) {
                $newIntervalMs = 1 * self::DAY;
                $newState = self::STATE_LEARNING;
            } elseif ($rating === self::RATING_GOOD) {
                $newIntervalMs = 10 * self::MINUTE;
                $newState = self::STATE_LEARNING;
            } elseif ($rating === self::RATING_EASY) {
                $newIntervalMs = 1 * self::DAY;
                $newState = self::STATE_REVIEW;
            }
        } elseif ($state === self::STATE_RELEARNING) {
            if ($rating === self::RATING_AGAIN) {
                $newLapses += 1;
                $newIntervalMs = 1 * self::MINUTE;
                $newState = self::STATE_RELEARNING;
            } elseif ($rating === self::RATING_HARD) {
                $newIntervalMs = 6 * self::MINUTE;
                $newState = self::STATE_RELEARNING;
            } elseif ($rating === self::RATING_GOOD) {
                $newIntervalMs = $this->getNextInterval($repetitions, $EF, $intervalMs, $rating) * self::DAY;
                $newState = self::STATE_REVIEW;
            } elseif ($rating === self::RATING_EASY) {
                $newIntervalMs = $this->getNextInterval($repetitions, $EF, $intervalMs, $rating) * self::DAY * self::EASY_BONUS;
                $newState = self::STATE_REVIEW;
            }
        } else { // Review
            if ($rating === self::RATING_AGAIN) {
                $newLapses += 1;
                $newReps = 0;
                $newIntervalMs = 1 * self::DAY;
                $newState = self::STATE_RELEARNING;
                $newEF = max(1.3, $EF - 0.2);
            } elseif ($rating === self::RATING_HARD) {
                $newReps += 1;
                $newIntervalMs = $this->getNextInterval($repetitions, $EF, $intervalMs, $rating) * self::DAY * self::HARD_INTERVAL;
                $newState = self::STATE_REVIEW;
                $newEF = max(1.3, $EF - 0.15);
            } elseif ($rating === self::RATING_GOOD) {
                $newReps += 1;
                $newIntervalMs = $this->getNextInterval($repetitions, $EF, $intervalMs, $rating) * self::DAY;
                $newState = self::STATE_REVIEW;
            } elseif ($rating === self::RATING_EASY) {
                $newReps += 1;
                $newIntervalMs = $this->getNextInterval($repetitions, $EF, $intervalMs, $rating) * self::DAY * self::EASY_BONUS;
                $newState = self::STATE_REVIEW;
                $newEF = $EF + 0.15;
            }
        }

        return [
            'ease_factor' => round($newEF, 2),
            'interval' => max(1, (int) round($newIntervalMs / self::DAY)), // store as days
            'interval_ms' => $newIntervalMs,
            'repetitions' => $newReps,
            'lapses' => $newLapses,
            'state' => $newState,
            'due_date' => $now->copy()->addMilliseconds((int) $newIntervalMs),
            'last_review' => $now,
        ];
    }

    private function getNextInterval(int $repetitions, float $EF, float $intervalMs, int $rating): float
    {
        $I = $intervalMs / self::DAY;

        if (in_array($repetitions, [self::STATE_NEW, self::STATE_LEARNING], true) ||
            in_array($repetitions, [self::STATE_NEW, self::STATE_RELEARNING], true)) {
            // treat as learning/relearning by repetitions count
        }

        // FSRS uses repetitions (review count) for scheduling step
        if ($repetitions === 0) {
            if ($rating === self::RATING_HARD) return 1 * self::DAY;
            if ($rating === self::RATING_GOOD) return 1 * self::DAY;
            if ($rating === self::RATING_EASY) return 4 * self::DAY;
            return 1 * self::DAY;
        } elseif ($repetitions === 1) {
            if ($rating === self::RATING_HARD) return 1 * self::DAY;
            if ($rating === self::RATING_GOOD) return 6 * self::DAY;
            if ($rating === self::RATING_EASY) return 10 * self::DAY;
            return 1 * self::DAY;
        } else {
            return $I * $EF;
        }
    }
}
