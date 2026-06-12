<?php
// app/Models/Commande.php

namespace App\Models;

use App\Enums\StatutCommande;
use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('commandes')]
#[Fillable(
    'numero', 'client_id', 'date',
    'date_livraison_prevue', 'location_id',
    'statut', 'echeance', 'created_by'
)]
class Commande extends Model
{
    use HasFactory, HasReference;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date'                  => 'date',
            'date_livraison_prevue' => 'date',
            'statut'                => StatutCommande::class,
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeNonLivrees($query)
    {
        return $query->whereIn('statut', [
            StatutCommande::NON_LIVREE->value,
            StatutCommande::PARTIELLE->value,
        ]);
    }

    public function scopeEnRetard($query)
    {
        return $query->nonLivrees()
                     ->where('date_livraison_prevue', '<', now());
    }

    public function scopeParClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
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
        return $this->hasMany(LigneCommande::class);
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class, 'source_id')
                    ->where('source_type', 'commande');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function totalCommande(): float
    {
        return (float) $this->lignes()
            ->selectRaw('SUM(quantite * prix_unitaire) as total')
            ->value('total');
    }

    public function estEnRetard(): bool
    {
        return $this->date_livraison_prevue
            && $this->date_livraison_prevue->isPast()
            && $this->statut !== StatutCommande::LIVREE;
    }

    public function estLivree(): bool
    {
        return $this->statut === StatutCommande::LIVREE;
    }
}