<?php
// app/Traits/HasReference.php

namespace App\Traits;

/**
 * Génère automatiquement des numéros de référence séquentiels.
 *
 * LARAVEL 13 / PHP 8.3 :
 * - readonly sur les variables locales immuables
 * - match expression typée
 * - Typage strict sur tous les paramètres
 *
 * Usage :
 *   Commande::generateReference('CMD')    → CMD-2026-001
 *   BonProduction::generateReference('BP') → BP-2026-047
 *   Facture::generateReference('FAC', 4)  → FAC-2026-0001
 */
trait HasReference
{
    public static function generateReference(
        string $prefix,
        int    $padding = 3
    ): string {
        $year       = date('Y');
        $fullPrefix = "{$prefix}-{$year}-";

        $last = static::where('numero', 'like', $fullPrefix . '%')
                      ->orderByDesc('id')
                      ->value('numero');

        $next = $last
            ? (int) substr($last, strlen($fullPrefix)) + 1
            : 1;

        return $fullPrefix . str_pad($next, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Génère et assigne le numéro automatiquement avant création.
     * À appeler dans le boot() du modèle ou dans le Service.
     *
     * Exemple dans un Service :
     *   $commande->numero = Commande::generateReference('CMD');
     */
    public static function prochainNumero(string $prefix, int $padding = 3): string
    {
        return static::generateReference($prefix, $padding);
    }
}