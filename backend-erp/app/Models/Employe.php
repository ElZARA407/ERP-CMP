<?php
// app/Models/Employe.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('employes')]
#[Fillable(
    'matricule', 'nom', 'prenom', 'poste_id',
    'date_embauche', 'date_depart', 'actif'
)]
class Employe extends Model
{
    use HasFactory, SoftDeletes;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date_embauche' => 'date',
            'date_depart'   => 'date',
            'actif'         => 'boolean',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    public function scopeParPoste($query, int $posteId)
    {
        return $query->where('poste_id', $posteId);
    }

    // ── Relations ──────────────────────────────────────────
    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function bpEmployes(): HasMany
    {
        return $this->hasMany(BpEmploye::class, 'employe_id');
    }

    public function btEmployes(): HasMany
    {
        return $this->hasMany(BtEmploye::class, 'employe_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    public function tauxHoraireActuel(): float
    {
        return (float) $this->poste->taux_horaire;
    }

    public function estEnPoste(): bool
    {
        return $this->actif && $this->date_depart === null;
    }

    public function anciennete(): int
    {
        return (int) $this->date_embauche->diffInYears(now());
    }
}