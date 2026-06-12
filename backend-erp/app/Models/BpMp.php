<?php
// app/Models/BpMp.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bp_mps')]
#[Fillable(
    'bp_session_id', 'matiere_id',
    'quantite_utilisee', 'quantite_restituee', 'cout_matiere'
)]
class BpMp extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite_utilisee'  => 'decimal:3',
            'quantite_restituee' => 'decimal:3',
            'cout_matiere'       => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function session(): BelongsTo
    {
        return $this->belongsTo(BpSession::class, 'bp_session_id');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(MatierePremiere::class, 'matiere_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function quantiteNette(): float
    {
        return max(
            0,
            (float) $this->quantite_utilisee
            - (float) $this->quantite_restituee
        );
    }
}