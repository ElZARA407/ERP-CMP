<?php
// app/Policies/ProductionPolicy.php

namespace App\Policies;

use App\Enums\StatutProduction;
use App\Models\BonProduction;
use App\Models\Role;
use App\Models\Utilisateur;

class ProductionPolicy
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
            Role::OPERATEUR_SAISIE,
        ], true);
    }

    public function view(Utilisateur $user, BonProduction $bp): bool
    {
        return $this->viewAny($user);
    }

    public function create(Utilisateur $user): bool
    {
        return $user->role?->nom === Role::RESPONSABLE_PROD;
    }

    public function update(Utilisateur $user, BonProduction $bp): bool
    {
        return $user->role?->nom === Role::RESPONSABLE_PROD
            && $bp->statut->estActif();
    }

    public function saisirSession(Utilisateur $user, BonProduction $bp): bool
    {
        return in_array($user->role?->nom, [
            Role::RESPONSABLE_PROD,
            Role::OPERATEUR_SAISIE,
        ], true) && $bp->statut->estActif();
    }

    public function validerSession(Utilisateur $user, BonProduction $bp): bool
    {
        return $user->role?->nom === Role::RESPONSABLE_PROD
            && $bp->statut->estActif();
    }

    public function cloture(Utilisateur $user, BonProduction $bp): bool
    {
        return $user->role?->nom === Role::RESPONSABLE_PROD
            && $bp->statut === StatutProduction::EN_COURS;
    }

    public function annuler(Utilisateur $user, BonProduction $bp): bool
    {
        return $user->role?->nom === Role::RESPONSABLE_PROD
            && $bp->statut === StatutProduction::OUVERT;
    }
}