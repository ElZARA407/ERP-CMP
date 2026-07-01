<?php

namespace App\Models;

use App\Enums\QualiteProduit;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('classement_produits')]
#[Fillable('qualite', 'libelle', 'actif')]
class ClassementProduit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qualite' => QualiteProduit::class,
            'actif'   => 'boolean',
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
    // Plus de relation produit() — le lien se fait via stocks

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'classement_id');
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

    // ── Méthodes métier ────────────────────────────────────

    /**
     * Tous les produits distincts qui ont un stock avec ce classement
     */
    public function produits()
    {
        return Produit::whereHas('stocks', function ($q) {
            $q->where('classement_id', $this->id);
        });
    }

    public function stockDisponible(?int $locationId = null): float
    {
        $query = $this->stocks();

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return (float) $query->sum('stock_total');
    }

    public function label(): string
    {
        return $this->libelle ?? $this->qualite->label();
    }
}