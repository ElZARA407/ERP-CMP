<?php
// app/Policies/CommandePolicy.php

namespace App\Policies;

use App\Models\Commande;
use App\Models\Role;
use App\Models\Utilisateur;

/**
 * LARAVEL 13 :
 * - Les Policies sont inchangées syntaxiquement
 * - before() permet un bypass admin universel
 * - Chaque méthode retourne bool|null
 *   (null = passer à la règle suivante)
 */
class CommandePolicy
{
    /**
     * L'admin bypass toutes les vérifications.
     */
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
            Role::COMMERCIAL,
            Role::LOGISTIQUE,
            Role::FINANCE,
            Role::RESPONSABLE_PROD,
        ]);
    }

    public function view(Utilisateur $user, Commande $commande): bool
    {
        return in_array($user->role?->nom, [
            Role::COMMERCIAL,
            Role::LOGISTIQUE,
            Role::FINANCE,
            Role::RESPONSABLE_PROD,
        ]);
    }

    public function create(Utilisateur $user): bool
    {
        return $user->role?->nom === Role::COMMERCIAL;
    }

    public function update(Utilisateur $user, Commande $commande): bool
    {
        return $user->role?->nom === Role::COMMERCIAL
            && $commande->statut->estEnCours();
    }

    public function delete(Utilisateur $user, Commande $commande): bool
    {
        return false; // Jamais de suppression — audit trail
    }
}
