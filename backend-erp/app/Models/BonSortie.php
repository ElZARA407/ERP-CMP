<?php
// app/Models/BonSortie.php

namespace App\Models;

use App\Traits\HasReference;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bon_sorties')]
#[Fillable(
    'numero', 'location_id', 'date', 'motif',
    'client_id', 'statut', 'observations',
    'created_by', 'valide_by'
)]
class BonSortie extends Model
{
    use HasFactory, HasReference, HasAuditFields;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneSortie::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estValidable(): bool
    {
        return $this->statut === 'brouillon'
            && $this->lignes()->count() > 0;
    }
}