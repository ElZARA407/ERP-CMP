<?php
// app/Models/Fournisseur.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('fournisseurs')]
#[Fillable(
    'nom', 'reference', 'NIF', 'STAT',
    'adresse', 'email', 'contact',
    'interlocutaire', 'code_compta', 'actif','est_divers',
)]
class Fournisseur extends Model
{
    use HasFactory, SoftDeletes;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'est_divers' => 'boolean',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    // ── Relations ──────────────────────────────────────────
    public function journalAchats(): HasMany
    {
        return $this->hasMany(JournalAchat::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function totalAchatsAnnuel(int $annee): float
    {
        return (float) $this->journalAchats()
            ->where('statut', 'valide')
            ->whereYear('date', $annee)
            ->sum('total');
    }
}