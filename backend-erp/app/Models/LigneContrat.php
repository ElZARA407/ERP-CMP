<?php
// app/Models/LigneContrat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ligne_contrats')]
#[Fillable(
    'contrat_id',
    'produit_id',
    'classement_id',
    'quantite_contractuelle',
    'quantite_livree_ytd',
    'frequence',
    'frequence_jours',
    'date_debut',
    'date_fin',
    'statut',
    'prix_unitaire'
)]
class LigneContrat extends Model
{
    use HasFactory;

    public const FREQUENCES = [
        'quotidienne',
        'hebdomadaire',
        'bimensuel',
        'mensuel',
        'tous_x_jours',
        'personnalisee',
    ];

    protected function casts(): array
    {
        return [
            'quantite_contractuelle' => 'decimal:3',
            'quantite_livree_ytd' => 'decimal:3',
            'prix_unitaire' => 'decimal:2',
            'frequence_jours' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    public function quantiteRestante(): float
    {
        return max(
            0,
            (float) $this->quantite_contractuelle - (float) $this->quantite_livree_ytd
        );
    }

    public function estSolde(): bool
    {
        return $this->quantiteRestante() <= 0;
    }

    public function frequenceLibelle(): string
    {
        return match ($this->frequence) {
            'quotidienne' => 'Quotidienne',
            'hebdomadaire' => 'Hebdomadaire',
            'bimensuel' => 'Bimensuel',
            'mensuel' => 'Mensuelle',
            'tous_x_jours' => 'Tous les X jours',
            'personnalisee' => 'Personnalisée',
            default => ucfirst((string) $this->frequence),
        };
    }
}