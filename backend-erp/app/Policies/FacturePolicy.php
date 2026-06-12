<?php
// app/Policies/FacturePolicy.php

namespace App\Policies;

use App\Models\Facture;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Enums\StatutFacture;

class FacturePolicy
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
            Role::FINANCE,
            Role::COMMERCIAL,
        ]);
    }

    public function view(Utilisateur $user, Facture $facture): bool
    {
        return in_array($user->role?->nom, [
            Role::FINANCE,
            Role::COMMERCIAL,
        ]);
    }

    public function create(Utilisateur $user): bool
    {
        return $user->role?->nom === Role::FINANCE;
    }

    public function update(Utilisateur $user, Facture $facture): bool
    {
        return $user->role?->nom === Role::FINANCE
            && $facture->statut !== StatutFacture::ANNULEE
            && $facture->statut !== StatutFacture::PAYEE;
    }

    public function payer(Utilisateur $user, Facture $facture): bool
    {
        return $user->role?->nom === Role::FINANCE
            && $facture->statut->estPayable();
    }

    public function annuler(Utilisateur $user, Facture $facture): bool
    {
        return $user->role?->nom === Role::FINANCE
            && $facture->statut === StatutFacture::EMISE;
    }
}
