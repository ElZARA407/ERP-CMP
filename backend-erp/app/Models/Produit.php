<?php

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
    'colisage', 'poids', 'seuil', 'actif'
)]
class Produit extends Model
{
    use HasFactory, SoftDeletes;

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

    public function bonsProduction(): HasMany
    {
        return $this->hasMany(BonProduction::class);
    }

    /**
     * Tous les stocks de ce produit (toutes locations, toutes qualités)
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'entite_id')
                    ->where('entite_type', 'produit');
    }

    /**
     * Les classements globaux utilisés par ce produit dans ses stocks
     * (remplace l'ancien hasMany classements)
     */
    public function classementsDisponibles()
    {
        return ClassementProduit::whereIn(
            'id',
            Stock::where('entite_type', 'produit')
                 ->where('entite_id', $this->id)
                 ->whereNotNull('classement_id')
                 ->pluck('classement_id')
        )->get();
    }

    // ── Méthodes métier ────────────────────────────────────

    public function stockTotalParQualite(string $qualite): float
    {
        $classement = ClassementProduit::where('qualite', $qualite)->first();

        if (!$classement) return 0;

        return (float) Stock::where('entite_type', 'produit')
                            ->where('entite_id', $this->id)
                            ->where('classement_id', $classement->id)
                            ->sum('stock_total');
    }

    public function stockTotal(): float
    {
        return (float) $this->stocks()->sum('stock_total');
    }
}