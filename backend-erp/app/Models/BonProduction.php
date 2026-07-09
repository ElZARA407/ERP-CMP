<?php
// app/Models/BonProduction.php

namespace App\Models;

use App\Enums\StatutProduction;
use App\Traits\HasReference;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bon_productions')]
#[Fillable(
    'numero', 'date', 'location_id', 'produit_id',
    'machine_id', 'quantite_cible',
    'statut', 'cout_total', 'created_by'
)]
class BonProduction extends Model
{
    use HasFactory, HasReference, HasAuditFields;

    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'quantite_cible' => 'decimal:3',
            'cout_total'     => 'decimal:2',
            'statut'         => StatutProduction::class,
        ];
    }

    public function scopeActifs($query)
    {
        return $query->whereIn('statut', [
            StatutProduction::OUVERT->value,
            StatutProduction::EN_COURS->value,
        ]);
    }

    public function scopeParLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BpSession::class);
    }

    public function sessionsValidees(): HasMany
    {
        return $this->hasMany(BpSession::class)
                    ->where('statut', 'validee');
    }

    public function quantiteTotaleProduite(): float
    {
        return (float) BpObtenue::whereHas('session', function ($q) {
            $q->where('bon_production_id', $this->id)
              ->where('statut', 'validee');
        })->sum('quantite_produite');
    }

    public function tauxRealisation(): float
    {
        if ($this->quantite_cible == 0) return 0;

        return round(
            ($this->quantiteTotaleProduite() / $this->quantite_cible) * 100,
            2
        );
    }

    public function prochainNumeroSession(): int
    {
        return (int) $this->sessions()->max('session_numero') + 1;
    }
}