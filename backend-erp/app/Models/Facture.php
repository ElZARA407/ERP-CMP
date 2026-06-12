<?php
// app/Models/Facture.php

namespace App\Models;

use App\Enums\StatutFacture;
use App\Enums\ModePaiement;
use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LARAVEL 13 :
 * Les deux Enums StatutFacture et ModePaiement sont castés
 * nativement via PHP 8.3 — accès typé depuis le contrôleur :
 *   $facture->statut->estPayable()
 *   $facture->mode_paiement->label()
 */
#[Table('factures')]
#[Fillable(
    'numero', 'livraison_id', 'client_id',
    'date', 'total', 'statut',
    'echeance_paiement', 'date_paiement',
    'mode_paiement', 'notes', 'created_by'
)]
class Facture extends Model
{
    use HasFactory, HasReference;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'date'              => 'date',
            'echeance_paiement' => 'date',
            'date_paiement'     => 'date',
            'total'             => 'decimal:2',
            'statut'            => StatutFacture::class,
            'mode_paiement'     => ModePaiement::class,
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeImpayees($query)
    {
        return $query->whereIn('statut', [
            StatutFacture::EMISE->value,
            StatutFacture::PARTIELLEMENT_PAYEE->value,
        ]);
    }

    public function scopeEnRetard($query)
    {
        return $query->impayees()
                     ->where('echeance_paiement', '<', now());
    }

    public function scopeParClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopePayees($query)
    {
        return $query->where('statut', StatutFacture::PAYEE->value);
    }

    // ── Relations ──────────────────────────────────────────
    public function livraison(): BelongsTo
    {
        return $this->belongsTo(Livraison::class);
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
        return $this->hasMany(LigneFacture::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estEnRetard(): bool
    {
        return $this->statut->estPayable()
            && $this->echeance_paiement?->isPast();
    }

    public function joursDeRetard(): int
    {
        if (!$this->estEnRetard()) return 0;

        return (int) $this->echeance_paiement->diffInDays(now());
    }

    public function payer(ModePaiement $mode): void
    {
        $this->update([
            'statut'          => StatutFacture::PAYEE,
            'date_paiement'   => now(),
            'mode_paiement'   => $mode,
        ]);
    }

    public function calculerTotal(): float
    {
        return (float) $this->lignes()->sum('total_ligne');
    }
}