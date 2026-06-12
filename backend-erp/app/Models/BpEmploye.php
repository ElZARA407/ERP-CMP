<?php
// app/Models/BpEmploye.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bp_employes')]
#[Fillable(
    'bp_session_id', 'employe_id',
    'heures_brutes', 'heures_effectives',
    'taux_horaire', 'cout'
)]
class BpEmploye extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'heures_brutes'     => 'decimal:2',
            'heures_effectives' => 'decimal:2',
            'taux_horaire'      => 'decimal:2',
            'cout'              => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function session(): BelongsTo
    {
        return $this->belongsTo(BpSession::class, 'bp_session_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function calculerCout(): float
    {
        return round(
            (float) $this->heures_effectives * (float) $this->taux_horaire,
            2
        );
    }
}