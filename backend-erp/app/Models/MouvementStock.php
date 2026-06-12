<?php
// app/Models/MouvementStock.php

namespace App\Models;

use App\Enums\TypeMouvement;
use App\Enums\TypeEntite;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * JOURNAL IMMUABLE — aucun UPDATE ni DELETE autorisé.
 *
 * LARAVEL 13 :
 * - public const UPDATED_AT = null : indique à Eloquent
 *   qu'il n'y a pas de colonne updated_at sur cette table
 * - Les événements boot() bloquent toute tentative de
 *   modification ou suppression au niveau du modèle
 */
#[Table('mouvements_stock')]
#[Fillable(
    'location_id', 'entite_type', 'entite_id',
    'classement_id', 'type', 'quantite',
    'reference_type', 'reference_id',
    'utilisateur_id', 'date_mouvement'
)]
class MouvementStock extends Model
{
    // Pas de updated_at — journal immuable
    public const UPDATED_AT = null;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'type'           => TypeMouvement::class,
            'entite_type'    => TypeEntite::class,
            'quantite'       => 'decimal:3',
            'date_mouvement' => 'datetime',
        ];
    }

    // ── Immuabilité stricte ─────────────────────────────────
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new LogicException(
                'Les mouvements de stock sont immuables — aucune modification autorisée.'
            );
        });

        static::deleting(function () {
            throw new LogicException(
                'Les mouvements de stock sont immuables — aucune suppression autorisée.'
            );
        });
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeEntrees($query)
    {
        return $query->where('type', TypeMouvement::ENTREE->value);
    }

    public function scopeSorties($query)
    {
        return $query->where('type', TypeMouvement::SORTIE->value);
    }

    public function scopePeriode($query, string $debut, string $fin)
    {
        return $query->whereBetween('date_mouvement', [$debut, $fin]);
    }

    public function scopeParEntite($query, string $type, int $id)
    {
        return $query->where('entite_type', $type)
                     ->where('entite_id', $id);
    }

    public function scopeParReference($query, string $type, int $id)
    {
        return $query->where('reference_type', $type)
                     ->where('reference_id', $id);
    }

    // ── Relations ──────────────────────────────────────────
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function impactStock(): float
    {
        return $this->type->estEntree()
            ? (float) $this->quantite
            : -(float) $this->quantite;
    }
}