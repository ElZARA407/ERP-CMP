<?php
// app/Models/JournalAchat.php

namespace App\Models;

use App\Traits\HasReference;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('journal_achats')]
#[Fillable(
    'numero', 'fournisseur_id', 'date', 'location_id',
    'vehicule', 'statut', 'total', 'observations',
    'created_by', 'valide_by'
)]
class JournalAchat extends Model
{
    use HasFactory, HasReference, HasAuditFields;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date'  => 'date',
            'total' => 'decimal:2',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeBrouillons($query)
    {
        return $query->where('statut', 'brouillon');
    }

    public function scopeValides($query)
    {
        return $query->where('statut', 'valide');
    }

    // ── Relations ──────────────────────────────────────────
    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneAchat::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estValidable(): bool
    {
        return $this->statut === 'brouillon'
            && $this->lignes()->count() > 0;
    }

    public function calculerTotal(): float
    {
        return (float) $this->lignes()->sum('total_ligne');
    }

    public function nombreLignes(): int
    {
        return $this->lignes()->count();
    }
}