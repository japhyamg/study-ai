<?php

namespace App\Policies;

use App\Models\ClassArm;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClassPolicy
{
    use HandlesAuthorization, StudyAiAuthorizable;

    public function viewAny(User $user): bool
    {
        return $this->inSchool($user, $user->currentSchool()?->id);
    }

    public function view(User $user, ClassArm $class): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $class->school_id);
        }
        if ($this->isTeacher($user)) {
            return $this->teaches($user, $class) || $class->school_id === $user->currentSchool()?->id;
        }
        // student: enrolled
        return $class->enrollments()->where('user_id', $user->id)->exists()
            || $class->school_id === $user->currentSchool()?->id;
    }

    public function create(User $user): bool
    {
        return $this->isPlatformAdmin($user) || $this->isTeacher($user);
    }

    public function update(User $user, ClassArm $class): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $class->school_id);
        }
        return $this->isTeacher($user) && $this->teaches($user, $class);
    }

    /** A teacher "owns" an arm if they are its form teacher or teach a subject in it. */
    private function teaches(User $user, ClassArm $class): bool
    {
        return $class->form_teacher_id === $user->id
            || $class->subjectAssignments()->where('teacher_id', $user->id)->exists();
    }

    public function delete(User $user, ClassArm $class): bool
    {
        return $this->isPlatformAdmin($user) && $this->inSchool($user, $class->school_id);
    }
}
