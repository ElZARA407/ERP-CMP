<?php
// app/Enums/StatutFacture.php

namespace App\Enums;

enum StatutFacture: string
{
    case EN_ATTENTE          = 'en_attente';
    case EMISE               = 'emise';
    case PARTIELLEMENT_PAYEE = 'partiellement_payee';
    case PAYEE               = 'payee';
    case ANNULEE             = 'annulee';

    public function label(): string
    {
        return match($this) {
            self::EN_ATTENTE          => 'En attente',
            self::EMISE               => 'Émise',
            self::PARTIELLEMENT_PAYEE => 'Partiellement payée',
            self::PAYEE               => 'Payée',
            self::ANNULEE             => 'Annulée',
        };
    }

    public function estPayable(): bool
    {
        return in_array($this, [self::EMISE, self::PARTIELLEMENT_PAYEE]);
    }

    public function couleur(): string
    {
        return match($this) {
            self::EN_ATTENTE          => 'gray',
            self::EMISE               => 'blue',
            self::PARTIELLEMENT_PAYEE => 'orange',
            self::PAYEE               => 'green',
            self::ANNULEE             => 'red',
        };
    }
}