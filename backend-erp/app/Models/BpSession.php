<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('bt_sessions')]
#[Fillable(
    'bon_transformation_id',
    'session_numero',
    'date_session',
    'machine_id',
    'machine_broyage',
    'ecarts',
    'statut',
    'saisi_by',
    'valide_by'
)]
class BtSession extends Model
{
    use HasFactory, HasAuditFields;

    protected function casts(): array
    {
        return [
            'date_session' => 'date',
            'ecarts' => 'decimal:2',
        ];
    }

    public function bonTransformation(): BelongsTo
    {
        return $this->belongsTo(BonTransformation::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function matieres(): HasMany
    {
        return $this->hasMany(BtMp::class);
    }

    public function employes(): HasMany
    {
        return $this->hasMany(BtEmploye::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(BtEvenement::class);
    }

    public function calcul(): HasOne
    {
        return $this->hasOne(BtSessionCalcul::class, 'bt_session_id');
    }

    public function sorties(): HasMany
    {
        return $this->matieres()->where('type', 'sortie');
    }

    public function entrees(): HasMany
    {
        return $this->matieres()->where('type', 'entree');
    }

    public function quantiteSortie(): float
    {
        return (float) $this->matieres()
            ->where('type', 'sortie')
            ->sum('quantite');
    }

    public function quantiteRestituee(): float
    {
        return (float) $this->matieres()
            ->where('type', 'sortie')
            ->sum('quantite_restituee');
    }

    public function quantiteNetteConsommee(): float
    {
        return max(0, $this->quantiteSortie() - $this->quantiteRestituee());
    }

    public function quantiteEntree(): float
    {
        return (float) $this->matieres()
            ->where('type', 'entree')
            ->sum('quantite');
    }
}