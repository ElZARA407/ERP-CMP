<?php
// app/Models/Utilisateur.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LARAVEL 13 :
 * - #[Table] remplace protected $table = 'utilisateurs'
 * - #[Fillable] remplace protected $fillable = [...]
 * - #[Hidden] remplace protected $hidden = [...]
 * - app/Http/Kernel.php supprimé → Sanctum configuré dans bootstrap/app.php
 */
#[Table('utilisateurs')]
#[Fillable('nom', 'email', 'password', 'role_id', 'location_id', 'actif')]
#[Hidden('password', 'remember_token')]
class Utilisateur extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // ── Casts ──────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'actif'             => 'boolean',
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    public function scopeParRole($query, string $role)
    {
        return $query->whereHas('role', fn($q) => $q->where('nom', $role));
    }

    public function scopeParLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    // ── Relations ──────────────────────────────────────────
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'utilisateur_id');
    }

    public function commandesCreees(): HasMany
    {
        return $this->hasMany(Commande::class, 'created_by');
    }

    public function facturesCreees(): HasMany
    {
        return $this->hasMany(Facture::class, 'created_by');
    }

    // ── Méthodes métier ────────────────────────────────────
    public function hasRole(string $roleName): bool
    {
        return $this->role?->nom === $roleName;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function peutValider(): bool
    {
        return in_array($this->role?->nom, [
            Role::ADMIN,
            Role::RESPONSABLE_PROD,
            Role::RESPONSABLE_ACHAT,
        ]);
    }
}