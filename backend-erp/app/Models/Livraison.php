<?php
// app/Models/Livraison.php

namespace App\Models;

use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    protected function casts(): array
    {
        return [
            'date_livraison' => 'date',
        ];
    }

    public function scopePreparees($query)
    {
        return $query->where('statut', 'prepare');
    }

    public function scopeLivrees($query)
    {
        return $query->where('statut', 'livre');
    }

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

    public function factures(): BelongsToMany
    {
        return $this->belongsToMany(Facture::class, 'facture_livraisons')
            ->withPivot(['total_livraison', 'lignes_count'])
            ->withTimestamps();
    }

    public function source()
    {
        return match ($this->source_type) {
            'commande' => $this->belongsTo(Commande::class, 'source_id'),
            'vente_directe' => $this->belongsTo(VenteDirecte::class, 'source_id'),
            default => null,
        };
    }

    public function estFacturee(): bool
    {
        return $this->facture()->exists() || $this->factures()->exists();
    }

    public function totalLivre(): float
    {
        return (float) $this->lignes()->sum('quantite_livree');
    }
}