<?php
// app/Models/BonTransformation.php

namespace App\Models;

use App\Enums\StatutRecyclage;
use App\Traits\HasReference;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bon_transformations')]
#[Fillable(
    'numero', 'date', 'location_id',
    'matiere_brute_id', 'matiere_broyee_id',
    'machine_broyage', 'quantite_entree',
    'statut', 'created_by', 'saisi_by', 'valide_by'
)]
class BonTransformation extends Model
{
    use HasFactory, HasReference, HasAuditFields;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'quantite_entree' => 'decimal:3',
            'statut'          => StatutRecyclage::class,
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->whereIn('statut', [
            StatutRecyclage::OUVERT->value,
            StatutRecyclage::EN_COURS->value,
        ]);
    }

    // ── Relations ──────────────────────────────────────────
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function matiereBrute(): BelongsTo
    {
        return $this->belongsTo(MatierePremiere::class, 'matiere_brute_id');
    }

    public function matiereBroyee(): BelongsTo
    {
        return $this->belongsTo(MatierePremiere::class, 'matiere_broyee_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BtSession::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function quantiteBroyeeTotale(): float
    {
        return (float) BtMp::whereHas('session', function ($q) {
            $q->where('bon_transformation_id', $this->id)
              ->where('statut', 'validee');
        })->where('type', 'sortie')->sum('quantite');
    }

    public function tauxRendementGlobal(): float
    {
        if ($this->quantite_entree == 0) return 0;

        return round(
            ($this->quantiteBroyeeTotale() / $this->quantite_entree) * 100,
            2
        );
    }

    public function tauxPerteGlobal(): float
    {
        return round(100 - $this->tauxRendementGlobal(), 2);
    }
}