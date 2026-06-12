<?php
// app/Enums/StatutAchat.php

namespace App\Enums;

enum StatutAchat: string
{
    case BROUILLON = 'brouillon';
    case VALIDE    = 'valide';

    public function label(): string
    {
        return match($this) {
            self::BROUILLON => 'Brouillon',
            self::VALIDE    => 'Validé',
        };
    }
}