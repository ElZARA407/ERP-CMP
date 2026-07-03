<?php
// app/Models/LigneFacture.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ligne_factures')]
#[Fillable(
    'facture_id', 'classement_id','produit_id',
    'quantite', 'prix_unitaire', 'total_ligne'
)]
class LigneFacture extends Model
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
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function calculerTotalLigne(): float
    {
        return round(
            (float) $this->quantite * (float) $this->prix_unitaire,
            2
        );
    }
}