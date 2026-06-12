<?php
// app/Enums/TypeMouvement.php

namespace App\Enums;

enum TypeMouvement: string
{
    case ENTREE     = 'entree';
    case SORTIE     = 'sortie';
    case RETOUR     = 'retour';
    case INVENTAIRE = 'inventaire';

    public function label(): string
    {
        return match($this) {
            self::ENTREE     => 'Entrée',
            self::SORTIE     => 'Sortie',
            self::RETOUR     => 'Retour',
            self::INVENTAIRE => 'Inventaire',
        };
    }

    public function estEntree(): bool
    {
        return in_array($this, [self::ENTREE, self::RETOUR]);
    }

    public function estSortie(): bool
    {
        return $this === self::SORTIE;
    }
}