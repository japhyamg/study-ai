<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentSchool()?->id !== null;
    }

    public function view(User $user, Question $question): bool
    {
        return $question->material?->school_id === $user->currentSchool()?->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->highestRole() === 'teacher';
    }

    public function update(User $user, Question $question): bool
    {
        if ($user->isAdmin()) {
            return $question->material?->school_id === $user->currentSchool()?->id;
        }
        return $user->highestRole() === 'teacher'
            && $question->material?->created_by === $user->id;
    }

    public function delete(User $user, Question $question): bool
    {
        if ($user->isAdmin()) {
            return $question->material?->school_id === $user->currentSchool()?->id;
        }
        return $user->highestRole() === 'teacher'
            && $question->material?->created_by === $user->id;
    }
}
