<?php
// app/Models/DemandeAchat.php

namespace App\Models;

use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('demandes_achat')]
#[Fillable(
    'numero', 'date_demande', 'demandeur_id',
    'statut', 'observations'
)]
class DemandeAchat extends Model
{
    use HasFactory, HasReference;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date_demande' => 'date',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'soumise');
    }

    public function scopeApprouvees($query)
    {
        return $query->where('statut', 'approuvee');
    }

    // ── Relations ──────────────────────────────────────────
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'demandeur_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneDemande::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estApprovable(): bool
    {
        return $this->statut === 'soumise'
            && $this->lignes()->count() > 0;
    }

    public function approuver(): void
    {
        $this->update(['statut' => 'approuvee']);
    }

    public function rejeter(): void
    {
        $this->update(['statut' => 'rejetee']);
    }
}