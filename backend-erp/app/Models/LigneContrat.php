<?php
// app/Models/LigneContrat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ligne_contrats')]
#[Fillable(
    'contrat_id', 'classement_id',
    'quantite_contractuelle', 'quantite_livree_ytd',
    'frequence', 'statut', 'prix_unitaire'
)]
class LigneContrat extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite_contractuelle' => 'decimal:3',
            'quantite_livree_ytd'    => 'decimal:3',
            'prix_unitaire'          => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function quantiteRestante(): float
    {
        return max(
            0,
            (float) $this->quantite_contractuelle
            - (float) $this->quantite_livree_ytd
        );
    }

    public function estSolde(): bool
    {
        return $this->quantiteRestante() === 0.0;
    }
}