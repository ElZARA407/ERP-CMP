<?php
// app/Models/BpObtenue.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bp_obtenues')]
#[Fillable(
    'bp_session_id', 'classement_id',
    'quantite_produite', 'destination_location_id'
)]
class BpObtenue extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite_produite' => 'decimal:3',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function session(): BelongsTo
    {
        return $this->belongsTo(BpSession::class, 'bp_session_id');
    }

    public function classement(): BelongsTo
    {
        return $this->belongsTo(ClassementProduit::class, 'classement_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}