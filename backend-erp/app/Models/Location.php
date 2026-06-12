<?php
// app/Models/Location.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('locations')]
#[Fillable('nom', 'type')]
class Location extends Model
{
    use HasFactory;

    // ── Constantes ─────────────────────────────────────────
    public const TYPE_BUREAU = 'bureau';
    public const TYPE_USINE  = 'usine';

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeUsines($query)
    {
        return $query->where('type', self::TYPE_USINE);
    }

    public function scopeBureaux($query)
    {
        return $query->where('type', self::TYPE_BUREAU);
    }

    // ── Relations ──────────────────────────────────────────
    public function utilisateurs(): HasMany
    {
        return $this->hasMany(Utilisateur::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function bonsProduction(): HasMany
    {
        return $this->hasMany(BonProduction::class);
    }

    public function bonsTransformation(): HasMany
    {
        return $this->hasMany(BonTransformation::class);
    }

    public function journalAchats(): HasMany
    {
        return $this->hasMany(JournalAchat::class);
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estUsine(): bool
    {
        return $this->type === self::TYPE_USINE;
    }

    public function estBureau(): bool
    {
        return $this->type === self::TYPE_BUREAU;
    }
}