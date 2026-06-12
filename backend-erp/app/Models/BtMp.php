<?php
// app/Models/BtMp.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('bt_mps')]
#[Fillable(
    'bt_session_id', 'matiere_id',
    'type', 'quantite', 'quantite_restituee'
)]
class BtMp extends Model
{
    use HasFactory;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'quantite'           => 'decimal:3',
            'quantite_restituee' => 'decimal:3',
        ];
    }

    // ── Relations ──────────────────────────────────────────
    public function session(): BelongsTo
    {
        return $this->belongsTo(BtSession::class, 'bt_session_id');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(MatierePremiere::class, 'matiere_id');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estEntree(): bool
    {
        return $this->type === 'entree';
    }

    public function estSortie(): bool
    {
        return $this->type === 'sortie';
    }
}