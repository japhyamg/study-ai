<?php

namespace App\Policies;

use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClassPolicy
{
    use HandlesAuthorization, StudyAiAuthorizable;

    public function viewAny(User $user): bool
    {
        return $this->inSchool($user, $user->currentSchool()?->id);
    }

    public function view(User $user, ClassModel $class): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $class->school_id);
        }
        if ($this->isTeacher($user)) {
            return $class->teacher_id === $user->id || $class->school_id === $user->currentSchool()?->id;
        }
        // student: enrolled
        return $class->enrollments()->where('user_id', $user->id)->exists()
            || $class->school_id === $user->currentSchool()?->id;
    }

    public function create(User $user): bool
    {
        return $this->isPlatformAdmin($user) || $this->isTeacher($user);
    }

    public function update(User $user, ClassModel $class): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $class->school_id);
        }
        return $this->isTeacher($user) && $class->teacher_id === $user->id;
    }

    public function delete(User $user, ClassModel $class): bool
    {
        return $this->isPlatformAdmin($user) && $this->inSchool($user, $class->school_id);
    }
}
