<?php
// app/Enums/TypeEvenement.php

namespace App\Enums;

enum TypeEvenement: string
{
    case PRODUCTION = 'production';
    case PAUSE      = 'pause';
    case PANNE      = 'panne';
    case AUTRE      = 'autre';
    case BROYAGE    = 'broyage'; // Spécifique au recyclage

    public function label(): string
    {
        return match($this) {
            self::PRODUCTION => 'Production',
            self::PAUSE      => 'Pause',
            self::PANNE      => 'Panne',
            self::AUTRE      => 'Autre',
            self::BROYAGE    => 'Broyage',
        };
    }

    public function deduireDesHeures(): bool
    {
        return $this === self::PAUSE;
    }
}