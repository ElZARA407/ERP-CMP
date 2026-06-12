<?php
// app/Models/BtSession.php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bt_sessions')]
#[Fillable(
    'bon_transformation_id', 'session_numero',
    'date_session', 'machine_broyage',
    'ecarts', 'statut', 'saisi_by', 'valide_by'
)]
class BtSession extends Model
{
    use HasFactory, HasAuditFields;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date_session' => 'date',
            'ecarts'       => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function bonTransformation(): BelongsTo
    {
        return $this->belongsTo(BonTransformation::class);
    }

    public function matieres(): HasMany
    {
        return $this->hasMany(BtMp::class);
    }

    public function employes(): HasMany
    {
        return $this->hasMany(BtEmploye::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(BtEvenement::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function quantiteEntree(): float
    {
        return (float) $this->matieres()
            ->where('type', 'entree')
            ->sum('quantite');
    }

    public function quantiteSortie(): float
    {
        return (float) $this->matieres()
            ->where('type', 'sortie')
            ->sum('quantite');
    }

    public function calculerEcarts(): float
    {
        $entree = $this->quantiteEntree();
        if ($entree == 0) return 0;

        return round(
            (($entree - $this->quantiteSortie()) / $entree) * 100,
            2
        );
    }
}