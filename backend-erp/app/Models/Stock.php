<?php
// app/Models/Stock.php

namespace App\Models;

use App\Enums\TypeEntite;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LARAVEL 13 :
 * La méthode entite() utilise le cast TypeEntite::class
 * pour un accès typé au type polymorphique.
 *
 * IMPORTANT : Pas de HasFactory ici car le stock est
 * géré uniquement par StockService — jamais créé manuellement.
 */
#[Table('stocks')]
#[Fillable(
    'location_id', 'entite_type', 'entite_id',
    'classement_id', 'stock_total'
)]
class Stock extends Model
{
    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'stock_total'  => 'decimal:3',
            'entite_type'  => TypeEntite::class,
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeMatieres($query)
    {
        return $query->where('entite_type', TypeEntite::MATIERE->value);
    }

    public function scopeProduits($query)
    {
        return $query->where('entite_type', TypeEntite::PRODUIT->value);
    }

    public function scopeParLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeEnRupture($query)
    {
        return $query->where('stock_total', '<=', 0);
    }

    // ── Relations ──────────────────────────────────────────
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    /**
     * Résolution polymorphique typée avec PHP 8.3 match.
     */
    // Remplacer la méthode entite() actuelle par :

    public function entite(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('entite', 'entite_type', 'entite_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estEnRupture(): bool
    {
        return $this->stock_total <= 0;
    }

    public function estSousSeuilAlerte(float $seuil): bool
    {
        return $this->stock_total < $seuil;
    }
}