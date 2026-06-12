<?php
// app/Models/LigneCommande.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('ligne_commandes')]
#[Fillable(
    'commande_id', 'classement_id',
    'quantite', 'quantite_restante',
    'prix_unitaire', 'etat'
)]
class LigneCommande extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite'         => 'decimal:3',
            'quantite_restante'=> 'decimal:3',
            'prix_unitaire'    => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    public function lignesLivraison(): HasMany
    {
        return $this->hasMany(LigneLivraison::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function totalLigne(): float
    {
        return round(
            (float) $this->quantite * (float) $this->prix_unitaire,
            2
        );
    }

    public function estSoldee(): bool
    {
        return (float) $this->quantite_restante === 0.0;
    }
}