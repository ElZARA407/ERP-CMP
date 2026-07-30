<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Utilisateur;

class ReportVisibilityService
{
    public function canSeeGlobalCommercial(?Utilisateur $user): bool
    {
        $role = $user?->role?->nom;

        return in_array($role, [
            Role::ADMIN,
            Role::FINANCE,
            Role::LOGISTIQUE,
        ], true);
    }

    public function restrictCommercialTable($query, string $table, ?Utilisateur $user)
    {
        if (!$user || $this->canSeeGlobalCommercial($user)) {
            return $query;
        }

        if ($user->role?->nom === Role::COMMERCIAL) {
            return $query->where("{$table}.created_by", $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function restrictCommercialEloquent($query, string $table, ?Utilisateur $user)
    {
        return $this->restrictCommercialTable($query, $table, $user);
    }

    public function restrictFacturesFromCommercialScope($query, ?Utilisateur $user)
    {
        if (!$user || $this->canSeeGlobalCommercial($user)) {
            return $query;
        }

        if ($user->role?->nom !== Role::COMMERCIAL) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($scope) use ($user) {
            $scope
                ->where('factures.created_by', $user->id)
                ->orWhereExists(function ($exists) use ($user) {
                    $exists->selectRaw('1')
                        ->from('facture_livraisons')
                        ->join('livraisons', 'facture_livraisons.livraison_id', '=', 'livraisons.id')
                        ->whereColumn('facture_livraisons.facture_id', 'factures.id')
                        ->where('livraisons.created_by', $user->id);
                });
        });
    }

    public function restrictLivraisonsFromCommercialScope($query, ?Utilisateur $user)
    {
        if (!$user || $this->canSeeGlobalCommercial($user)) {
            return $query;
        }

        if ($user->role?->nom === Role::COMMERCIAL) {
            return $query->where('livraisons.created_by', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canAccessCreatedRecord(object $model, ?Utilisateur $user): bool
    {
        if (!$user || $this->canSeeGlobalCommercial($user)) {
            return true;
        }

        if ($user->role?->nom !== Role::COMMERCIAL) {
            return false;
        }

        return (int) ($model->created_by ?? 0) === (int) $user->id;
    }
}