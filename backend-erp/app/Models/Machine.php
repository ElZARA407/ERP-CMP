<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('machines')]
#[Fillable('nom', 'description', 'actif')]
class Machine extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    public function bonsProduction(): HasMany
    {
        return $this->hasMany(BonProduction::class, 'machine_id');
    }

    public function sessionsProduction(): HasMany
    {
        return $this->hasMany(BpSession::class, 'machine_id');
    }
}