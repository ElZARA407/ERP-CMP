<?php

namespace App\Models;

use App\Enums\StatutRecyclage;
use App\Traits\HasAuditFields;
use App\Traits\HasReference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bon_transformations')]
#[Fillable(
    'numero',
    'date',
    'location_id',
    'matiere_brute_id',
    'machine_id',
    'quantite_entree',
    'observations',
    'statut',
    'created_by',
    'saisi_by',
    'valide_by'
)]
class BonTransformation extends Model
{
    use HasFactory, HasReference, HasAuditFields;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantite_entree' => 'decimal:3',
            'statut' => StatutRecyclage::class,
        ];
    }

    public function scopeActifs($query)
    {
        return $query->whereIn('statut', [
            StatutRecyclage::OUVERT->value,
            StatutRecyclage::EN_COURS->value,
        ]);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function matiereBrute(): BelongsTo
    {
        return $this->belongsTo(MatierePremiere::class, 'matiere_brute_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BtSession::class);
    }

    public static function prochainNumero(): string
    {
        return static::generateReference('OT', 4, 'y');
    }

    public function prochainNumeroSession(): string
    {
        return static::generateReference('BT', 4, 'y', BtSession::class, 'session_numero');
    }

    public function quantiteNetteConsommeeTotale(): float
    {
        return (float) BtSessionCalcul::query()
            ->whereHas('session', fn ($query) => $query
                ->where('bon_transformation_id', $this->id)
                ->where('statut', 'validee')
            )
            ->sum('quantite_nette_consomme');
    }

    public function quantiteBroyeeTotale(): float
    {
        return (float) BtSessionCalcul::query()
            ->whereHas('session', fn ($query) => $query
                ->where('bon_transformation_id', $this->id)
                ->where('statut', 'validee')
            )
            ->sum('quantite_broyee_obtenue');
    }

    public function perteTotale(): float
    {
        return max(0, $this->quantiteNetteConsommeeTotale() - $this->quantiteBroyeeTotale());
    }

    public function tauxRendementGlobal(): float
    {
        $nette = $this->quantiteNetteConsommeeTotale();

        if ($nette <= 0) {
            return 0;
        }

        return round(($this->quantiteBroyeeTotale() / $nette) * 100, 2);
    }

    public function tauxPerteGlobal(): float
    {
        $nette = $this->quantiteNetteConsommeeTotale();

        if ($nette <= 0) {
            return 0;
        }

        return round(($this->perteTotale() / $nette) * 100, 2);
    }

    public function tauxAvancement(): float
    {
        $prevue = (float) $this->quantite_entree;

        if ($prevue <= 0) {
            return 0;
        }

        return round(($this->quantiteNetteConsommeeTotale() / $prevue) * 100, 2);
    }
}