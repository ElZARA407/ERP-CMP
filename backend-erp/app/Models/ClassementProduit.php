<?php
// app/Models/ClassementProduit.php

namespace App\Models;

use App\Enums\QualiteProduit;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LARAVEL 13 :
 * Le cast QualiteProduit::class sur l'enum PHP 8.3 permet d'écrire :
 *   $classement->qualite === QualiteProduit::PREMIER
 * au lieu de :
 *   $classement->qualite === '1er'
 */
#[Table('classement_produits')]
#[Fillable('produit_id', 'qualite', 'prix_specifique', 'actif')]
class ClassementProduit extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'qualite'         => QualiteProduit::class,
            'prix_specifique' => 'decimal:2',
            'actif'           => 'boolean',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    public function scopePremierQualite($query)
    {
        return $query->where('qualite', QualiteProduit::PREMIER->value);
    }

    // ── Relations ──────────────────────────────────────────
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function lignesCommande(): HasMany
    {
        return $this->hasMany(LigneCommande::class, 'classement_id');
    }

    public function lignesContrat(): HasMany
    {
        return $this->hasMany(LigneContrat::class, 'classement_id');
    }

    public function lignesLivraison(): HasMany
    {
        return $this->hasMany(LigneLivraison::class, 'classement_id');
    }

    public function lignesVenteDirecte(): HasMany
    {
        return $this->hasMany(LigneVenteDirecte::class, 'classement_id');
    }

    public function lignesFacture(): HasMany
    {
        return $this->hasMany(LigneFacture::class, 'classement_id');
    }

    public function bpObtenues(): HasMany
    {
        return $this->hasMany(BpObtenue::class, 'classement_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'classement_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function prixEffectif(): float
    {
        return (float) ($this->prix_specifique ?? 0);
    }

    public function designation(): string
    {
        return $this->produit->designation
            . ' ('
            . $this->qualite->label()
            . ')';
    }

    public function stockDisponible(?int $locationId = null): float
    {
        $query = $this->stocks();

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return (float) $query->sum('stock_total');
    }
}