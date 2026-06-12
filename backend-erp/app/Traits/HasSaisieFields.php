<?php
// app/Traits/HasSaisieFields.php

namespace App\Traits;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait pour les modèles avec saisi_by / valide_by
 * (BpSession, BtSession, BonTransformation).
 *
 * Distinct de HasAuditFields qui gère created_by / valide_by.
 * Les sessions n'ont pas de created_by mais ont saisi_by.
 */
trait HasSaisieFields
{
    public function saisiteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'saisi_by');
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'valide_by');
    }

    public function assignerSaisiteur(?int $userId = null): static
    {
        $this->saisi_by = $userId ?? auth()->id();
        return $this;
    }

    public function assignerValideur(?int $userId = null): static
    {
        $this->valide_by = $userId ?? auth()->id();
        return $this;
    }
}
