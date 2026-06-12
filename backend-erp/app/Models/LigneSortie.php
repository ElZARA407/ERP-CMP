<?php
// app/Models/LigneSortie.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ligne_sorties')]
#[Fillable('bon_sortie_id', 'classement_id', 'quantite')]
class LigneSortie extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function bonSortie(): BelongsTo
    {
        return $this->belongsTo(BonSortie::class);
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }
}