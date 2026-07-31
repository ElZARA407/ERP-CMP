<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bt_session_calculs')]
#[Fillable(
    'bt_session_id',
    'quantite_brute_utilisee',
    'quantite_restituee',
    'quantite_nette_consomme',
    'quantite_broyee_obtenue',
    'perte',
    'rendement',
    'taux_perte',
    'temps_brut',
    'temps_pause',
    'temps_panne',
    'temps_autre',
    'temps_effectif',
    'details_json',
    'calcule_le'
)]
class BtSessionCalcul extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantite_brute_utilisee' => 'decimal:3',
            'quantite_restituee' => 'decimal:3',
            'quantite_nette_consomme' => 'decimal:3',
            'quantite_broyee_obtenue' => 'decimal:3',
            'perte' => 'decimal:3',
            'rendement' => 'decimal:3',
            'taux_perte' => 'decimal:3',
            'temps_brut' => 'decimal:2',
            'temps_pause' => 'decimal:2',
            'temps_panne' => 'decimal:2',
            'temps_autre' => 'decimal:2',
            'temps_effectif' => 'decimal:2',
            'details_json' => 'array',
            'calcule_le' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BtSession::class, 'bt_session_id');
    }
}