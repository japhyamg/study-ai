<?php

namespace App\Support\Members;

use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Models\Teacher;

/**
 * Map of school member types → profile models.
 *
 * Admins, teachers and students each live in their own table; this map is the
 * single place that knows which model belongs to which type key.
 */
class MemberTypes
{
    public const TYPES = [
        'admin' => SchoolAdmin::class,
        'teacher' => Teacher::class,
        'student' => Student::class,
    ];

    /** @return class-string|null */
    public static function model(?string $type): ?string
    {
        return self::TYPES[$type] ?? null;
    }

    public static function label(string $type): string
    {
        return match ($type) {
            'admin' => 'Administrator',
            'teacher' => 'Teacher',
            'student' => 'Student',
            default => ucfirst($type),
        };
    }

    public static function valid(): array
    {
        return array_keys(self::TYPES);
    }
}
