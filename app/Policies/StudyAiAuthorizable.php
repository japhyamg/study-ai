<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared authorization helpers.
 * Rules:
 *  - super_admin: access to everything (platform-wide)
 *  - admin:       access within their school
 *  - teacher:     own records (created_by / teacher_id) within their school
 *  - student:     enrolled records within their school
 */
trait StudyAiAuthorizable
{
    protected function isPlatformAdmin(User $user): bool
    {
        return $user->isAdmin(); // super_admin or admin
    }

    protected function isTeacher(User $user): bool
    {
        return $user->highestRole() === User::ROLE_TEACHER;
    }

    protected function isStudent(User $user): bool
    {
        return $user->highestRole() === User::ROLE_STUDENT;
    }

    /** True if user belongs (any role) to the given school. */
    protected function inSchool(User $user, ?string $schoolId): bool
    {
        if (! $schoolId) {
            return false;
        }

        return $user->belongsToSchool($schoolId);
    }
}
