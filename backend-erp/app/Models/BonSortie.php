<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bon_sorties')]
#[Fillable(
    'numero',
    'location_id',
    'destination_location_id',
    'date',
    'motif',
    'motif_detail',
    'client_id',
    'statut',
    'observations',
    'created_by',
    'valide_by'
)]
class BonSortie extends Model
{
    use HasFactory, HasReference, HasAuditFields;

    public const MOTIF_TRANSFERT = 'transfert';
    public const MOTIF_ECHANTILLON = 'echantillon';
    public const MOTIF_PERTE = 'perte';
    public const MOTIF_CASSE = 'casse';
    public const MOTIF_CONSOMMATION_INTERNE = 'consommation_interne';
    public const MOTIF_DON = 'don';
    public const MOTIF_DESTRUCTION = 'destruction';
    public const MOTIF_AUTRE = 'autre';
    public const MOTIF_USAGE_INTERNE = 'usage_interne';

    public const MOTIFS = [
        self::MOTIF_TRANSFERT,
        self::MOTIF_ECHANTILLON,
        self::MOTIF_PERTE,
        self::MOTIF_CASSE,
        self::MOTIF_CONSOMMATION_INTERNE,
        self::MOTIF_DON,
        self::MOTIF_DESTRUCTION,
        self::MOTIF_AUTRE,
        self::MOTIF_USAGE_INTERNE,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneSortie::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'valide_by');
    }

    public function estValidable(): bool
    {
        $statut = strtolower(trim((string) $this->statut));

        return $statut === 'brouillon'
            && $this->lignes()->exists();
    }

    public function motifLibelle(): string
    {
        return match ($this->motif) {
            self::MOTIF_TRANSFERT => 'Transfert',
            self::MOTIF_ECHANTILLON => 'Échantillon',
            self::MOTIF_PERTE => 'Perte',
            self::MOTIF_CASSE => 'Casse',
            self::MOTIF_CONSOMMATION_INTERNE => 'Consommation interne',
            self::MOTIF_DON => 'Don',
            self::MOTIF_DESTRUCTION => 'Destruction',
            self::MOTIF_AUTRE => 'Autre',
            self::MOTIF_USAGE_INTERNE => 'Usage interne',
            default => ucfirst((string) $this->motif),
        };
    }
}