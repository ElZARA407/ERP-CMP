<?php
// app/Enums/ModePaiement.php

namespace App\Enums;

enum ModePaiement: string
{
    case ESPECE       = 'espece';
    case VIREMENT     = 'virement';
    case CHEQUE       = 'cheque';
    case MOBILE_MONEY = 'mobile_money';

    public function label(): string
    {
        return match($this) {
            self::ESPECE       => 'Espèces',
            self::VIREMENT     => 'Virement bancaire',
            self::CHEQUE       => 'Chèque',
            self::MOBILE_MONEY => 'Mobile Money (MVola / Orange)',
        };
    }
}