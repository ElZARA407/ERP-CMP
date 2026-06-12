<?php
// app/Models/CategorieProduit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('categorie_produits')]
#[Fillable('nom')]
class CategorieProduit extends Model
{
    use HasFactory;

    // ── Constantes procédés CMP ────────────────────────────
    public const INJ  = 'INJ';  // Injection
    public const HDPE = 'HDPE'; // Extrusion HDPE
    public const PET  = 'PET';  // Soufflage PET
    public const MCH  = 'MCH';  // Moulage

    // ── Relations ──────────────────────────────────────────
    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'categorie_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function totalProduits(): int
    {
        return $this->produits()->where('actif', true)->count();
    }
}