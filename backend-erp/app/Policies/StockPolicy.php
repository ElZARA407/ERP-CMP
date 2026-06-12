<?php
// app/Policies/StockPolicy.php

namespace App\Policies;

use App\Models\Role;
use App\Models\Utilisateur;

class StockPolicy
{
    public function before(Utilisateur $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(Utilisateur $user): bool
    {
        return in_array($user->role?->nom, [
            Role::RESPONSABLE_PROD,
            Role::COMMERCIAL,
            Role::LOGISTIQUE,
            Role::RESPONSABLE_ACHAT,
        ]);
    }

    public function ajustement(Utilisateur $user): bool
    {
        return $user->role?->nom === Role::RESPONSABLE_PROD;
    }
}
