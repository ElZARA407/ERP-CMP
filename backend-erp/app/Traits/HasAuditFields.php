<?php
// app/Traits/HasAuditFields.php

namespace App\Traits;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait commun pour les modèles avec created_by / valide_by.
 *
 * Évite la duplication des deux relations d'audit sur
 * les 10+ modèles qui ont ces champs (JournalAchat,
 * BonProduction, BonTransformation, BonSortie, etc.)
 *
 * LARAVEL 13 :
 * - Utilisé avec #[Fillable] sur les modèles → les champs
 *   created_by et valide_by doivent être listés dans #[Fillable]
 *   sur le modèle consommateur
 */
trait HasAuditFields
{
    public function createur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'valide_by');
    }

    /**
     * Assigne le créateur depuis l'utilisateur authentifié.
     * Appeler dans le Service avant save().
     */
    public function assignerCreateur(?int $userId = null): static
    {
        $this->created_by = $userId ?? auth()->id();
        return $this;
    }

    /**
     * Assigne le valideur depuis l'utilisateur authentifié.
     * Appeler dans le Service lors de la validation.
     */
    public function assignerValideur(?int $userId = null): static
    {
        $this->valide_by = $userId ?? auth()->id();
        return $this;
    }
}
