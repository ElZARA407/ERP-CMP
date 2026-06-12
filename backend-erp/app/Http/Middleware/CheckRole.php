<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de vérification de rôle.
 *
 * Usage dans les routes :
 *   Route::middleware('role:admin,commercial')->group(...)
 *
 * LARAVEL 13 :
 * - Enregistré via alias dans bootstrap/app.php
 *   (plus dans Kernel.php)
 */
class CheckRole
{
    public function handle(
        Request $request,
        Closure $next,
        string  ...$roles
    ): Response {
        $user = $request->user();

        if (!$user || !in_array($user->role?->nom, $roles)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
                'code'    => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
