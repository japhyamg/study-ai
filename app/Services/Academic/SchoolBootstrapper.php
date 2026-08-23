<?php

namespace App\Services\Academic;

use App\Models\AcademicSession;
use App\Models\AssessmentType;
use App\Models\ClassLevel;
use App\Models\School;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Gives a brand-new school a working academic structure so an administrator
 * never lands on an empty dashboard.
 *
 * Creates: an academic session with its terms, class levels, subjects and
 * assessment types — all taken from the configured preset
 * ({@see config('academic')}). Everything is editable afterwards; nothing here
 * is structural.
 *
 * Idempotent: safe to re-run against an existing school.
 */
class SchoolBootstrapper
{
    public function bootstrap(School $school, ?string $preset = null): void
    {
        $config = $this->preset($preset);

        DB::transaction(function () use ($school, $config) {
            $session = $this->seedSession($school, $config);
            $this->seedTerms($school, $session, $config);
            $this->seedClassLevels($school, $config);
            $this->seedSubjects($school, $config);
            $this->seedAssessmentTypes($school, $config);

            $school->refresh();
        });
    }

    /** @return array<string, mixed> */
    protected function preset(?string $name = null): array
    {
        $name ??= config('academic.preset', 'nigeria');

        return config("academic.presets.{$name}")
            ?? config('academic.presets.generic')
            ?? [];
    }

    // ── Session & terms ──

    protected function seedSession(School $school, array $config): AcademicSession
    {
        $startMonth = (int) ($config['session_start_month'] ?? 9);
        $now = now();

        // Before the start month we are still in the session that began last year.
        $startYear = $now->month >= $startMonth ? $now->year : $now->year - 1;
        $name = $startYear.'/'.($startYear + 1);

        $session = AcademicSession::firstOrCreate(
            ['school_id' => $school->id, 'name' => $name],
            [
                'start_date' => now()->setDate($startYear, $startMonth, 1)->startOfDay(),
                'end_date' => now()->setDate($startYear + 1, max(1, $startMonth - 2), 28)->endOfDay(),
                'is_current' => true,
            ]
        );

        if (! $school->current_session_id) {
            $session->makeCurrent();
        }

        return $session;
    }

    protected function seedTerms(School $school, AcademicSession $session, array $config): void
    {
        $terms = $config['terms'] ?? [];

        if (empty($terms)) {
            return;
        }

        $spanDays = $session->start_date && $session->end_date
            ? $session->start_date->diffInDays($session->end_date)
            : 300;

        $perTerm = (int) max(1, floor($spanDays / max(1, count($terms))));
        $created = [];

        foreach ($terms as $i => $definition) {
            $start = $session->start_date?->copy()->addDays($perTerm * $i);
            $end = $start?->copy()->addDays($perTerm - 1);

            $created[] = Term::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_session_id' => $session->id,
                    'name' => $definition['name'],
                ],
                [
                    'sequence' => $definition['sequence'] ?? $i + 1,
                    'start_date' => $start,
                    'end_date' => $end,
                    'is_current' => false,
                ]
            );
        }

        if (! $school->fresh()->current_term_id && ! empty($created)) {
            // Prefer the term that actually contains today.
            $active = collect($created)->first(fn (Term $t) => $t->isActive());
            ($active ?? $created[0])->makeCurrent();
        }
    }

    // ── Structure ──

    protected function seedClassLevels(School $school, array $config): void
    {
        foreach ($config['levels'] ?? [] as $i => $level) {
            ClassLevel::firstOrCreate(
                ['school_id' => $school->id, 'code' => $level['code']],
                [
                    'name' => $level['name'],
                    'stage' => $level['stage'] ?? null,
                    'position' => $level['position'] ?? $i + 1,
                ]
            );
        }
    }

    protected function seedSubjects(School $school, array $config): void
    {
        foreach ($config['subjects'] ?? [] as $subject) {
            Subject::firstOrCreate(
                ['school_id' => $school->id, 'code' => $subject['code']],
                [
                    'name' => $subject['name'],
                    'category' => $subject['category'] ?? Subject::CATEGORY_CORE,
                    'applies_to' => $subject['applies_to'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function seedAssessmentTypes(School $school, array $config): void
    {
        foreach ($config['assessment_types'] ?? [] as $i => $type) {
            AssessmentType::firstOrCreate(
                ['school_id' => $school->id, 'code' => $type['code']],
                [
                    'name' => $type['name'],
                    'max_score' => $type['max_score'] ?? 100,
                    'weight_percent' => $type['weight_percent'] ?? 0,
                    'position' => $type['position'] ?? $i + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
