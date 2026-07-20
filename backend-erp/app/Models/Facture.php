<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('factures')]
#[Fillable(
    'numero', 'livraison_id', 'client_id',
    'date', 'total', 'montant_paye', 'statut',
    'echeance_paiement', 'date_paiement',
    'mode_paiement', 'notes', 'created_by'
)]
class Facture extends Model
{
    use HasFactory, HasReference;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'echeance_paiement' => 'date',
            'date_paiement' => 'date',
            'total' => 'decimal:2',
            'montant_paye' => 'decimal:2',
            'statut' => StatutFacture::class,
            'mode_paiement' => ModePaiement::class,
        ];
    }

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

    public function livraison(): BelongsTo
    {
        return $this->belongsTo(Livraison::class);
    }

    public function livraisons(): BelongsToMany
    {
        return $this->belongsToMany(Livraison::class, 'facture_livraisons')
            ->withPivot(['total_livraison', 'lignes_count'])
            ->withTimestamps();
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

    public function estEnRetard(): bool
    {
        return $this->statut->estPayable()
            && $this->echeance_paiement?->isPast();
    }

    public function joursDeRetard(): int
    {
        if (! $this->estEnRetard()) {
            return 0;
        }

        return (int) $this->echeance_paiement->diffInDays(now());
    }

    public function montantPaye(): float
    {
        return (float) $this->montant_paye;
    }

    public function resteAPayer(): float
    {
        return max(0, round((float) $this->total - (float) $this->montant_paye, 2));
    }

    public function peutRecevoirPaiement(): bool
    {
        return $this->statut->estPayable() && $this->resteAPayer() > 0;
    }

    public function payer(ModePaiement $mode, float $montant): void
    {
        $nouveauMontantPaye = round((float) $this->montant_paye + $montant, 2);
        $reste = max(0, round((float) $this->total - $nouveauMontantPaye, 2));

        $this->update([
            'montant_paye' => $nouveauMontantPaye,
            'statut' => $reste <= 0 ? StatutFacture::PAYEE : StatutFacture::PARTIELLEMENT_PAYEE,
            'date_paiement' => now(),
            'mode_paiement' => $mode,
        ]);
    }

    public function calculerTotal(): float
    {
        return (float) $this->lignes()->sum('total_ligne');
    }
}