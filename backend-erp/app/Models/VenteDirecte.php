<?php
// app/Models/VenteDirecte.php

namespace App\Models;

use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('ventes_directes')]
#[Fillable(
    'numero', 'client_id', 'date', 'location_id',
    'statut', 'total', 'created_by'
)]
class VenteDirecte extends Model
{
    use HasFactory, HasReference;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date'  => 'date',
            'total' => 'decimal:2',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeValidees($query)
    {
        return $query->where('statut', 'validee');
    }

    // ── Relations ──────────────────────────────────────────
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneVenteDirecte::class);
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class, 'source_id')
                    ->where('source_type', 'vente_directe');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function calculerTotal(): float
    {
        return (float) $this->lignes()->sum('total_ligne');
    }
}