<?php
// app/Enums/TypeEntite.php

namespace App\Enums;

enum TypeEntite: string
{
    case MATIERE = 'matiere';
    case PRODUIT = 'produit';

    public function label(): string
    {
        return match($this) {
            self::MATIERE => 'Matière première',
            self::PRODUIT => 'Produit fini',
        };
    }
}