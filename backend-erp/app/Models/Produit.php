<?php
// app/Models/Produit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('produits')]
#[Fillable(
    'nomencla', 'designation', 'categorie_id',
    'contenance', 'format', 'unite',
    'colisage',  'poids', 'seuil', 'actif'
)]
class Produit extends Model
{
    use HasFactory, SoftDeletes;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'colisage' => 'decimal:2',
            'seuil'    => 'decimal:2',
            'actif'    => 'boolean',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    public function scopeParCategorie($query, int $categorieId)
    {
        return $query->where('categorie_id', $categorieId);
    }

    // ── Relations ──────────────────────────────────────────
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieProduit::class, 'categorie_id');
    }

    public function classements(): HasMany
    {
        return $this->hasMany(ClassementProduit::class);
    }

    public function bonsProduction(): HasMany
    {
        return $this->hasMany(BonProduction::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function classementParQualite(string $qualite): ?ClassementProduit
    {
        return $this->classements()
                    ->where('qualite', $qualite)
                    ->where('actif', true)
                    ->first();
    }

    public function classementPremier(): ?ClassementProduit
    {
        return $this->classementParQualite('1er');
    }

    public function stockTotalParQualite(string $qualite): float
    {
        $classement = $this->classementParQualite($qualite);

        if (!$classement) return 0;

        return (float) Stock::where('entite_type', 'produit')
                            ->where('entite_id', $this->id)
                            ->where('classement_id', $classement->id)
                            ->sum('stock_total');
    }
}