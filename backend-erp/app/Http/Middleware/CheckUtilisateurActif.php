<?php
// app/Http/Middleware/CheckUtilisateurActif.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie que l'utilisateur authentifié est actif.
 * Un compte désactivé ne peut pas accéder à l'API
 * même avec un token Sanctum valide.
 *
 * LARAVEL 13 :
 * - Enregistré via alias dans bootstrap/app.php
 */
class CheckUtilisateurActif
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$request->user()->actif) {
            return response()->json([
                'message' => 'Compte désactivé. Contactez l\'administrateur.',
                'code'    => 'ACCOUNT_DISABLED',
            ], 403);
        }

        return $next($request);
    }
}