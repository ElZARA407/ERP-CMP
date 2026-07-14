<?php
// app/Models/BpEvenement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bp_evenements')]
#[Fillable(
    'bp_session_id', 'type_evenement',
    'heure_debut', 'heure_fin',
    'description', 'operateur_id'
)]
class BpEvenement extends Model
{
    use HasFactory;

    // ── Relations ──────────────────────────────────────────
    public function session(): BelongsTo
    {
        return $this->belongsTo(BpSession::class, 'bp_session_id');
    }

    public function operateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'operateur_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function dureeEnHeures(): float
    {
        if (!$this->heure_fin) return 0;

        $debut = strtotime($this->heure_debut);
        $fin   = strtotime($this->heure_fin);

        return round(($fin - $debut) / 3600, 2);
    }

    public function estEnCours(): bool
    {
        return $this->heure_fin === null;
    }

    public function estUnePause(): bool
    {
        return $this->type_evenement === 'pause';
    }
}
