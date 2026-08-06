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
    // app/Models/Poste.php

    public function tauxHoraireCalcule(?Employe $employe = null): float
    {
        // 1. Priorité au salaire individuel de l'employé
        if ($employe && $employe->salaire_mensuel !== null && (float) $employe->salaire_mensuel > 0) {
            return round(((float) $employe->salaire_mensuel) / 173.33, 2);
        }

        // 2. Sinon, fallback sur le salaire du poste
        if ($this->salaire_mensuel !== null && (float) $this->salaire_mensuel > 0) {
            return round(((float) $this->salaire_mensuel) / 173.33, 2);
        }

        // 3. Dernier fallback : taux horaire fixe du poste
        return round((float) $this->taux_horaire, 2);
    }

    public function coutJournalier(?Employe $employe = null, float $heuresParJour = 8.0): float
    {
        return round($this->tauxHoraireCalcule($employe) * $heuresParJour, 2);
    }

    public function coutMensuel(?Employe $employe = null, float $heuresParMois = 173.33): float
    {
        if ($employe && $employe->salaire_mensuel !== null && (float) $employe->salaire_mensuel > 0) {
            return round((float) $employe->salaire_mensuel, 2);
        }

        if ($this->salaire_mensuel !== null && (float) $this->salaire_mensuel > 0) {
            return round((float) $this->salaire_mensuel, 2);
        }

        return round($this->tauxHoraireCalcule($employe) * $heuresParMois, 2);
    }
}