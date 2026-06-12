<?php
// app/Models/LigneDemande.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ligne_demandes')]
#[Fillable(
    'demande_achat_id', 'entite_type',
    'entite_id', 'quantite', 'observation_ligne'
)]
class LigneDemande extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function demandeAchat(): BelongsTo
    {
        return $this->belongsTo(DemandeAchat::class);
    }

    public function entite()
    {
        return match($this->entite_type) {
            'matiere' => $this->belongsTo(MatierePremiere::class, 'entite_id'),
            'produit' => $this->belongsTo(Produit::class, 'entite_id'),
            default   => null,
        };
    }
}