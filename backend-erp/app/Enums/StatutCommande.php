<?php
// app/Enums/StatutCommande.php

namespace App\Enums;

enum StatutCommande: string
{
    case LIVREE     = 'livree';
    case NON_LIVREE = 'non_livree';
    case PARTIELLE  = 'partielle';

    public function label(): string
    {
        return match($this) {
            self::LIVREE     => 'Livrée',
            self::NON_LIVREE => 'Non livrée',
            self::PARTIELLE  => 'Partiellement livrée',
        };
    }

    public function estEnCours(): bool
    {
        return in_array($this, [self::NON_LIVREE, self::PARTIELLE]);
    }

    public function couleur(): string
    {
        return match($this) {
            self::LIVREE     => 'green',
            self::NON_LIVREE => 'red',
            self::PARTIELLE  => 'orange',
        };
    }
}