<?php
// app/Models/Contrat.php

namespace App\Models;

use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('contrats')]
#[Fillable('numero', 'client_id', 'mois', 'actif')]
class Contrat extends Model
{
    use HasFactory, HasReference;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    public function scopeDuMois($query, string $mois)
    {
        return $query->where('mois', $mois);
    }

    // ── Relations ──────────────────────────────────────────
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneContrat::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function totalContractuel(): float
    {
        return (float) $this->lignes()
            ->selectRaw('SUM(quantite_contractuelle * prix_unitaire) as total')
            ->value('total');
    }

    public function tauxExecution(): float
    {
        $total    = $this->lignes()->sum('quantite_contractuelle');
        $livree   = $this->lignes()->sum('quantite_livree_ytd');

        if ($total == 0) return 0;

        return round(($livree / $total) * 100, 2);
    }
}