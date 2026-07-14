<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bp_session_calculs')]
#[Fillable(
    'bp_session_id',
    'temps_brut',
    'temps_pause',
    'temps_panne',
    'temps_effectif',
    'quantite_totale_produite',
    'cout_matieres_total',
    'cout_main_oeuvre_total',
    'cout_electricite',
    'cout_global',
    'cout_unitaire',
    'details_json',
    'calcule_le'
)]
class BpSessionCalcul extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'temps_brut' => 'decimal:2',
            'temps_pause' => 'decimal:2',
            'temps_panne' => 'decimal:2',
            'temps_effectif' => 'decimal:2',
            'quantite_totale_produite' => 'decimal:3',
            'cout_matieres_total' => 'decimal:2',
            'cout_main_oeuvre_total' => 'decimal:2',
            'cout_electricite' => 'decimal:2',
            'cout_global' => 'decimal:2',
            'cout_unitaire' => 'decimal:4',
            'details_json' => 'array',
            'calcule_le' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BpSession::class, 'bp_session_id');
    }
}
