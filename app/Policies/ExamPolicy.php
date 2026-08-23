<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExamPolicy
{
    use HandlesAuthorization, StudyAiAuthorizable;

    public function viewAny(User $user): bool
    {
        return $this->inSchool($user, $user->currentSchool()?->id);
    }

    public function view(User $user, Exam $exam): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $exam->school_id);
        }
        if ($this->isTeacher($user)) {
            return $exam->school_id === $user->currentSchool()?->id;
        }
        return $exam->school_id === $user->currentSchool()?->id;
    }

    public function create(User $user): bool
    {
        return $this->isPlatformAdmin($user) || $this->isTeacher($user);
    }

    public function update(User $user, Exam $exam): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $exam->school_id);
        }
        return $this->isTeacher($user) && $exam->school_id === $user->currentSchool()?->id;
    }

    public function delete(User $user, Exam $exam): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $exam->school_id);
        }

        // A teacher can remove an exam they set, which is the only way to clear
        // up a mistaken draft. Whether it is safe to delete once students have
        // sat it is a separate question, handled in the controller.
        return $this->isTeacher($user)
            && $exam->created_by === $user->id
            && $exam->school_id === $user->currentSchool()?->id;
    }

    public function take(User $user, Exam $exam): bool
    {
        if ($exam->status !== Exam::STATUS_PUBLISHED) {
            return false;
        }
        if ($this->isPlatformAdmin($user) || $this->isTeacher($user)) {
            return $this->inSchool($user, $exam->school_id);
        }
        // student must be enrolled in the exam's class (if any)
        if ($exam->class_arm_id) {
            return $exam->classArm?->enrollments()->where('user_id', $user->id)->exists() ?? false;
        }
        return $this->inSchool($user, $exam->school_id);
    }
}
