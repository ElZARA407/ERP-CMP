<?php
// app/Enums/StatutProduction.php

namespace App\Enums;

enum StatutProduction: string
{
    case OUVERT   = 'ouvert';
    case EN_COURS = 'en_cours';
    case CLOTURE  = 'cloture';
    case ANNULE   = 'annule';

    public function label(): string
    {
        return match($this) {
            self::OUVERT   => 'Ouvert',
            self::EN_COURS => 'En cours',
            self::CLOTURE  => 'Clôturé',
            self::ANNULE   => 'Annulé',
        };
    }

    public function estActif(): bool
    {
        return in_array($this, [self::OUVERT, self::EN_COURS]);
    }
}