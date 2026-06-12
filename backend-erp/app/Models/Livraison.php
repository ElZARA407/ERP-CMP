<?php
// app/Models/Livraison.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\HasReference;

#[Table('livraisons')]
#[Fillable(
    'numero', 'source_type', 'source_id', 'client_id',
    'reference_bc', 'reference_facture',
    'date_livraison', 'statut',
    'chauffeur', 'vehicule', 'observations', 'created_by'
)]
class Livraison extends Model
{
    use HasFactory, HasReference;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date_livraison' => 'date',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopePreparees($query)
    {
        return $query->where('statut', 'prepare');
    }

    public function scopeLivrees($query)
    {
        return $query->where('statut', 'livre');
    }

    // ── Relations ──────────────────────────────────────────
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneLivraison::class);
    }

    public function facture(): HasOne
    {
        return $this->hasOne(Facture::class);
    }

    /**
     * Résolution polymorphique de la source.
     */
    public function source()
    {
        return match($this->source_type) {
            'commande'      => $this->belongsTo(Commande::class, 'source_id'),
            'vente_directe' => $this->belongsTo(VenteDirecte::class, 'source_id'),
            default         => null,
        };
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estFacturee(): bool
    {
        return $this->facture()->exists();
    }

    public function totalLivre(): float
    {
        return (float) $this->lignes()->sum('quantite_livree');
    }
}