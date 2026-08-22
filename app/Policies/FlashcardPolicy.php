<?php

namespace App\Policies;

use App\Models\Flashcard;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FlashcardPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentSchool()?->id !== null;
    }

    public function view(User $user, Flashcard $flashcard): bool
    {
        return $flashcard->material?->school_id === $user->currentSchool()?->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->highestRole() === 'teacher';
    }

    public function update(User $user, Flashcard $flashcard): bool
    {
        if ($user->isAdmin()) {
            return $flashcard->material?->school_id === $user->currentSchool()?->id;
        }
        return $user->highestRole() === 'teacher'
            && $flashcard->material?->created_by === $user->id;
    }

    public function delete(User $user, Flashcard $flashcard): bool
    {
        if ($user->isAdmin()) {
            return $flashcard->material?->school_id === $user->currentSchool()?->id;
        }
        return $user->highestRole() === 'teacher'
            && $flashcard->material?->created_by === $user->id;
    }
}
