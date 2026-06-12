<?php
// app/Models/Poste.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('postes')]
#[Fillable('nom', 'taux_horaire', 'salaire_mensuel')]
class Poste extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'taux_horaire'    => 'decimal:2',
            'salaire_mensuel' => 'decimal:2',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function coutJournalier(float $heuresParJour = 8.0): float
    {
        return round($this->taux_horaire * $heuresParJour, 2);
    }

    public function coutMensuel(float $heuresParMois = 173.33): float
    {
        return $this->salaire_mensuel
            ?? round($this->taux_horaire * $heuresParMois, 2);
    }
}