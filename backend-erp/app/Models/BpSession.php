<?php
// app/Models/BpSession.php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bp_sessions')]
#[Fillable(
    'bon_production_id', 'session_numero', 'date_session',
    'machine_production', 'cout_electricite', 'cout_total',
    'statut', 'saisi_by', 'valide_by'
)]
class BpSession extends Model
{
    use HasFactory, HasAuditFields;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date_session'    => 'date',
            'cout_electricite'=> 'decimal:2',
            'cout_total'      => 'decimal:2',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeValidees($query)
    {
        return $query->where('statut', 'validee');
    }

    public function scopeOuvertes($query)
    {
        return $query->where('statut', 'ouverte');
    }

    // ── Relations ──────────────────────────────────────────
    public function bonProduction(): BelongsTo
    {
        return $this->belongsTo(BonProduction::class);
    }

    public function matieres(): HasMany
    {
        return $this->hasMany(BpMp::class);
    }

    public function obtenus(): HasMany
    {
        return $this->hasMany(BpObtenue::class);
    }

    public function employes(): HasMany
    {
        return $this->hasMany(BpEmploye::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(BpEvenement::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function coutMatieresTotal(): float
    {
        return (float) $this->matieres()->sum('cout_matiere');
    }

    public function coutMainOeuvreTotal(): float
    {
        return (float) $this->employes()->sum('cout');
    }

    public function coutTotal(): float
    {
        return round(
            $this->coutMatieresTotal()
            + $this->coutMainOeuvreTotal()
            + (float) $this->cout_electricite,
            2
        );
    }

    public function dureePauses(): float
    {
        return (float) $this->evenements()
            ->where('type_evenement', 'pause')
            ->whereNotNull('heure_fin')
            ->selectRaw('SUM(TIME_TO_SEC(TIMEDIFF(heure_fin, heure_debut)) / 3600) as total')
            ->value('total');
    }
}