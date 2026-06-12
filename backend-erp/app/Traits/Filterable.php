<?php
// app/Traits/Filterable.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait de filtrage dynamique pour les requêtes API REST.
 *
 * Permet d'appliquer des filtres depuis les query params
 * sans polluer les contrôleurs.
 *
 * Usage dans un contrôleur :
 *   $commandes = Commande::filter($request->validated())->paginate(20);
 *
 * Usage dans un modèle :
 *   public function scopeFilter($query, array $filters): Builder
 *   {
 *       return $query->filterBy($filters);
 *   }
 *
 * LARAVEL 13 / PHP 8.3 :
 * - array_filter() avec callback null supprime les valeurs falsy
 * - match() typé pour les opérateurs
 */
trait Filterable
{
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach (array_filter($filters, fn($v) => $v !== null && $v !== '') as $key => $value) {
            if (method_exists($this, 'filterBy' . ucfirst($key))) {
                $this->{'filterBy' . ucfirst($key)}($query, $value);
            } elseif ($query->getModel()->isFillable($key)) {
                $query->where($key, $value);
            }
        }

        return $query;
    }
}
