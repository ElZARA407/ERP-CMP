<?php
// app/Models/Client.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('clients')]
#[Fillable(
    'nom', 'reference', 'NIF', 'STAT',
    'adresse', 'email', 'contact',
    'interlocutaire', 'code_compta',
    'facturation', 'actif'
)]
class Client extends Model
{
    use HasFactory, SoftDeletes;

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

    // ── Relations ──────────────────────────────────────────
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class);
    }

    public function ventesDirectes(): HasMany
    {
        return $this->hasMany(VenteDirecte::class);
    }

    public function bonsSortie(): HasMany
    {
        return $this->hasMany(BonSortie::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function encoursTotalImpaye(): float
    {
        return (float) $this->factures()
            ->whereIn('statut', [
                StatutFacture::EMISE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])
            ->sum('total');
    }

    public function chiffreAffairesAnnuel(int $annee): float
    {
        return (float) $this->factures()
            ->where('statut', StatutFacture::PAYEE->value)
            ->whereYear('date', $annee)
            ->sum('total');
    }

    public function contratDuMois(string $mois): ?Contrat
    {
        return $this->contrats()
                    ->where('mois', $mois)
                    ->where('actif', true)
                    ->first();
    }
}