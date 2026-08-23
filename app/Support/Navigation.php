<?php

namespace App\Support;

use App\Models\SchoolMember;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar navigation, resolved from the principal's role.
 *
 * Keeping this in PHP rather than the Blade template means each role sees a
 * purpose-built menu (no `@if` pyramid in the layout) and the active state is
 * decided in one place.
 */
final class Navigation
{
    /**
     * @return array<string, array<int, array{label:string,url:string,icon:string,active:bool}>>
     */
    public static function for(User|SuperAdmin|null $principal, bool $isPlatform = false): array
    {
        if ($principal === null) {
            return [];
        }

        $sections = $isPlatform
            ? self::platform()
            : match ($principal->roleInSchool()) {
                SchoolMember::ROLE_ADMIN => self::admin(),
                SchoolMember::ROLE_TEACHER => self::teacher(),
                SchoolMember::ROLE_STUDENT => self::student(),
                default => [],
            };

        // Drop links whose route is not registered, then drop empty sections.
        return array_filter(
            array_map(static fn (array $links) => array_values(array_filter($links)), $sections)
        );
    }

    private static function platform(): array
    {
        return [
            'Platform' => [
                self::link('Overview', 'super-admin.dashboard', 'home'),
                self::link('Schools', 'super-admin.schools', 'building', 'super-admin.schools*'),
                self::link('Analytics', 'super-admin.analytics', 'chart'),
            ],
            'AI' => [
                self::link('Providers', 'super-admin.ai-providers', 'sparkles', 'super-admin.ai-providers*'),
                self::link('Token limits', 'super-admin.token-limits', 'gauge', 'super-admin.token-limits*'),
                self::link('Token usage', 'super-admin.token-usage', 'activity', 'super-admin.token-usage*'),
            ],
        ];
    }

    private static function admin(): array
    {
        return [
            'School' => [
                self::link('Dashboard', 'admin.dashboard', 'home'),
                self::link('Analytics', 'admin.analytics', 'chart'),
            ],
            'People' => [
                self::link('Teachers', 'admin.teachers', 'presentation'),
                self::link('Students', 'admin.students', 'academic-cap'),
                self::link('Administrators', 'admin.administrators', 'shield'),
            ],
            'Academics' => [
                self::link('Overview', 'admin.academic.index', 'academic-cap', 'admin.academic.*'),
                self::link('Classes', 'admin.classes.index', 'users', 'admin.classes.*'),
                self::link('Class levels', 'admin.levels.index', 'layers', 'admin.levels.*'),
                self::link('Subjects', 'admin.subjects.index', 'book', 'admin.subjects.*'),
                self::link('Sessions & terms', 'admin.terms.index', 'calendar', 'admin.terms.*'),
                self::link('Assessments', 'admin.assessment-types.index', 'clipboard', 'admin.assessment-types.*'),
            ],
            'Content' => [
                self::link('Study guides', 'learning.review', 'document', ['learning.review*', 'learning.materials.*']),
            ],
            'Manage' => [
                self::link('Settings', 'admin.settings', 'cog', 'admin.settings*'),
            ],
        ];
    }

    private static function teacher(): array
    {
        return [
            'Teaching' => [
                self::link('Dashboard', 'teacher.dashboard', 'home'),
                self::link('My classes', 'teacher.classes.index', 'users', 'teacher.classes.*'),
            ],
            'Content' => [
                self::link('Study guides', 'teacher.materials.index', 'document', ['teacher.materials.*', 'learning.materials.*']),
                self::link('Exams', 'teacher.exams.index', 'clipboard', 'teacher.exams.*'),
                self::link('Question bank', 'teacher.question-bank.index', 'database', 'teacher.question-bank.*'),
            ],
        ];
    }

    private static function student(): array
    {
        return [
            'Learn' => [
                self::link('Dashboard', 'student.dashboard', 'home'),
                self::link('My classes', 'student.classes', 'users', 'student.classes*'),
                self::link('Materials', 'student.materials', 'document', 'student.materials*'),
            ],
            'Practice' => [
                self::link('Study', 'student.study.index', 'sparkles', 'student.study*'),
                self::link('Flashcards', 'student.flashcards', 'layers'),
                self::link('Exams', 'student.exams', 'clipboard', 'student.exams*'),
                self::link('Topics', 'student.topics.index', 'book', 'student.topics.*'),
            ],
        ];
    }

    /**
     * @return array{label:string,url:string,icon:string,active:bool}|null
     */
    /**
     * @param  string|list<string>|null  $activePattern  one or more patterns that
     *                                                   should light this item up
     */
    private static function link(string $label, string $route, string $icon, string|array|null $activePattern = null): ?array
    {
        if (! Route::has($route)) {
            return null;
        }

        $patterns = (array) ($activePattern ?? $route);

        return [
            'label' => $label,
            'url' => route($route),
            'icon' => $icon,
            'active' => request()->routeIs(...$patterns),
        ];
    }
}
