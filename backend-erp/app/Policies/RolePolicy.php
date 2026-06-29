<?php
// app/Policies/RolePolicy.php

namespace App\Policies;

use App\Models\Role;
use App\Models\Utilisateur;

class RolePolicy
{
    public function before(Utilisateur $user, string $ability): bool|null
    {
        if (!$user->isAdmin()) {
            return null;
        }

        if (in_array($ability, ['update', 'delete'], true)) {
            return null;
        }

        return true;
    }

    public function viewAny(Utilisateur $user): bool
    {
        return $user->isAdmin();
    }

    public function view(Utilisateur $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    public function create(Utilisateur $user): bool
    {
        return $user->isAdmin();
    }

    public function update(Utilisateur $user, Role $role): bool
    {
        return $user->isAdmin() && !$role->estAdmin();
    }

    public function delete(Utilisateur $user, Role $role): bool
    {
        return $user->isAdmin() && !$role->estAdmin();
    }
}