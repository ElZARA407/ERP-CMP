<?php
// app/Models/LigneAchat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('lignes_achat')]
#[Fillable(
    'journal_achat_id', 'matiere_id',
    'quantite', 'prix_unitaire',
    'total_ligne', 'observations_ligne'
)]
class LigneAchat extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite'      => 'decimal:3',
            'prix_unitaire' => 'decimal:2',
            'total_ligne'   => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function journalAchat(): BelongsTo
    {
        return $this->belongsTo(JournalAchat::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(MatierePremiere::class, 'matiere_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function calculerTotalLigne(): float
    {
        return round((float) $this->quantite * (float) $this->prix_unitaire, 2);
    }
}