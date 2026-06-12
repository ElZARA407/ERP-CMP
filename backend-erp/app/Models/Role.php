<?php
// app/Models/Role.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('roles')]
#[Fillable('nom', 'description')]
class Role extends Model
{
    use HasFactory;

    // ── Constantes de rôles CMP ────────────────────────────
    public const ADMIN              = 'admin';
    public const RESPONSABLE_PROD   = 'responsable_prod';
    public const OPERATEUR_SAISIE   = 'operateur_saisie';
    public const COMMERCIAL         = 'commercial';
    public const LOGISTIQUE         = 'logistique';
    public const FINANCE            = 'finance';
    public const RESPONSABLE_ACHAT  = 'responsable_achat';

    // ── Relations ──────────────────────────────────────────
    public function utilisateurs(): HasMany
    {
        return $this->hasMany(Utilisateur::class);
    }

    // ── Méthodes métier ────────────────────────────────────
    public function estAdmin(): bool
    {
        return $this->nom === self::ADMIN;
    }
}