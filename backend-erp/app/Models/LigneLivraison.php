<?php
// app/Models/LigneLivraison.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('lignes_livraison')]
#[Fillable(
    'livraison_id','produit_id', 'ligne_commande_id',
    'ligne_vente_directe_id', 'classement_id',
    'quantite_livree'
)]
class LigneLivraison extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite_livree' => 'decimal:3',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
    public function livraison(): BelongsTo
    {
        return $this->belongsTo(Livraison::class);
    }

    public function ligneCommande(): BelongsTo
    {
        return $this->belongsTo(LigneCommande::class);
    }

    public function ligneVenteDirecte(): BelongsTo
    {
        return $this->belongsTo(LigneVenteDirecte::class);
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function sourceEstCommande(): bool
    {
        return $this->ligne_commande_id !== null;
    }

    public function sourceEstVenteDirecte(): bool
    {
        return $this->ligne_vente_directe_id !== null;
    }

    public function ligneSource(): LigneCommande|LigneVenteDirecte|null
    {
        if ($this->ligne_commande_id) {
            return $this->ligneCommande;
        }

        if ($this->ligne_vente_directe_id) {
            return $this->ligneVenteDirecte;
        }

        return null;
    }
}