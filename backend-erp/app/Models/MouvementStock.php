<?php
// app/Models/MouvementStock.php

namespace App\Models;

use App\Enums\TypeMouvement;
use App\Enums\TypeEntite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Table('mouvements_stock')]
#[Fillable(
    'location_id', 'entite_type', 'entite_id',
    'classement_id', 'type', 'quantite',
    'reference_type', 'reference_id',
    'utilisateur_id', 'date_mouvement',
    'motif', 'stock_theorique', 'stock_physique', 'ecart'
)]
class MouvementStock extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => TypeMouvement::class,
            'entite_type' => TypeEntite::class,
            'quantite' => 'decimal:3',
            'stock_theorique' => 'decimal:3',
            'stock_physique' => 'decimal:3',
            'ecart' => 'decimal:3',
            'date_mouvement' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new LogicException(
                'Les mouvements de stock sont immuables - aucune modification autorisee.'
            );
        });

        static::deleting(function () {
            throw new LogicException(
                'Les mouvements de stock sont immuables - aucune suppression autorisee.'
            );
        });
    }

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

    public function entite(): MorphTo
    {
        return $this->morphTo('entite', 'entite_type', 'entite_id');
    }

    public function impactStock(): float
    {
        if ($this->type === TypeMouvement::INVENTAIRE) {
            return (float) ($this->ecart ?? $this->quantite);
        }

        return $this->type->estEntree()
            ? (float) $this->quantite
            : -(float) $this->quantite;
    }
}