<?php
// app/Models/BtEvenement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bt_evenements')]
#[Fillable(
    'bt_session_id', 'type_evenement',
    'heure_debut', 'heure_fin',
    'description', 'operateur_id'
)]
class BtEvenement extends Model
{
    use HasFactory;

    // ── Relations ──────────────────────────────────────────
    public function session(): BelongsTo
    {
        return $this->belongsTo(BtSession::class, 'bt_session_id');
    }

    public function operateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'operateur_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function dureeEnHeures(): float
    {
        if (!$this->heure_fin) return 0;

        return round(
            (strtotime($this->heure_fin) - strtotime($this->heure_debut)) / 3600,
            2
        );
    }

    public function estEnCours(): bool
    {
        return $this->heure_fin === null;
    }
}