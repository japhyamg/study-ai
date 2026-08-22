<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaterialPolicy
{
    use HandlesAuthorization, StudyAiAuthorizable;

    public function viewAny(User $user): bool
    {
        return $this->inSchool($user, $user->currentSchool()?->id);
    }

    public function view(User $user, Material $material): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $material->school_id);
        }
        if ($this->isTeacher($user)) {
            return $material->created_by === $user->id
                || $material->school_id === $user->currentSchool()?->id;
        }
        return $material->published
            && $material->school_id === $user->currentSchool()?->id;
    }

    public function create(User $user): bool
    {
        return $this->isPlatformAdmin($user) || $this->isTeacher($user);
    }

    public function update(User $user, Material $material): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $material->school_id);
        }
        return $this->isTeacher($user) && $material->created_by === $user->id;
    }

    public function review(User $user, Material $material): bool
    {
        return $this->isPlatformAdmin($user) && $this->inSchool($user, $material->school_id);
    }

    public function delete(User $user, Material $material): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return $this->inSchool($user, $material->school_id);
        }
        return $this->isTeacher($user) && $material->created_by === $user->id;
    }
}
