<?php
// app/Models/MatierePremiere.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('matieres_premieres')]
#[Fillable(
    'reference', 'nom', 'type', 'description',
    'unite', 'prix_moyen', 'seuil', 'actif'
)]
class MatierePremiere extends Model
{
    use HasFactory, SoftDeletes;

    // ── Constantes types de matières CMP ──────────────────
    public const TYPE_PREFORMES = 'preformes';
    public const TYPE_BROYEE    = 'broyee';
    public const TYPE_BRUTE     = 'brute';
    public const TYPE_VIERGE    = 'vierge';
    public const TYPE_COLORANT  = 'colorant';
    public const TYPE_AUTRE     = 'autre';

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'prix_moyen' => 'decimal:2',
            'seuil'      => 'decimal:12,3',
            'actif'      => 'boolean',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    public function scopeParType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Relations ──────────────────────────────────────────
    public function lignesAchat(): HasMany
    {
        return $this->hasMany(LigneAchat::class, 'matiere_id');
    }

    public function bpMp(): HasMany
    {
        return $this->hasMany(BpMp::class, 'matiere_id');
    }

    public function btMp(): HasMany
    {
        return $this->hasMany(BtMp::class, 'matiere_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'entite_id')
                    ->where('entite_type', 'matiere');
    }

    public function bonsTransformationBrute(): HasMany
    {
        return $this->hasMany(BonTransformation::class, 'matiere_brute_id');
    }

    public function bonsTransformationBroyee(): HasMany
    {
        return $this->hasMany(BonTransformation::class, 'matiere_broyee_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    /**
     * Calcule le nouveau Prix Moyen Pondéré (PMP) après réception.
     *
     * PMP = (Stock actuel × Prix actuel + Qté reçue × Prix unitaire)
     *       / (Stock actuel + Qté reçue)
     */
    public function calculerNouveauPMP(
        float $quantiteRecue,
        float $prixUnitaire
    ): float {
        $stockActuel = (float) $this->stocks()->sum('stock_total');

        if (($stockActuel + $quantiteRecue) == 0) {
            return $prixUnitaire;
        }

        return round(
            ($stockActuel * $this->prix_moyen + $quantiteRecue * $prixUnitaire)
            / ($stockActuel + $quantiteRecue),
            2
        );
    }

    public function stockTotal(): float
    {
        return (float) $this->stocks()->sum('stock_total');
    }

    public function estEnRupture(): bool
    {
        return $this->stockTotal() <= 0;
    }
}