<?php
// app/Enums/QualiteProduit.php

namespace App\Enums;

enum QualiteProduit: string
{
    case PREMIER  = '1er';
    case DEUXIEME = '2e';
    case CASSE    = 'casse';

    public function label(): string
    {
        return match($this) {
            self::PREMIER  => '1ère qualité',
            self::DEUXIEME => '2ème qualité',
            self::CASSE    => 'Casse',
        };
    }

    public function coefficient(): float
    {
        return match($this) {
            self::PREMIER  => 1.0,
            self::DEUXIEME => 0.7,
            self::CASSE    => 0.2,
        };
    }
}