<?php
// app/Http/Controllers/Api/BaseApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
/**
 * Contrôleur de base — réponses JSON standardisées.
 *
 * Toutes les réponses API suivent le format :
 * {
 *   "success": true,
 *   "data": {...},
 *   "message": "...",
 *   "meta": {...}     // pagination, etc.
 * }
 */
abstract class BaseApiController extends Controller
{
    use AuthorizesRequests; 
    // ── Réponse succès ─────────────────────────────────────
    protected function success(
        mixed  $data    = null,
        string $message = 'Succès',
        int    $status  = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    // ── Réponse création ───────────────────────────────────
    protected function created(
        mixed  $data,
        string $message = 'Créé avec succès'
    ): JsonResponse {
        return $this->success($data, $message, 201);
    }

    // ── Réponse erreur ─────────────────────────────────────
    protected function error(
        string $message = 'Erreur',
        int    $status  = 400,
        mixed  $errors  = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    // ── Réponse 404 ────────────────────────────────────────
    protected function notFound(string $message = 'Ressource introuvable'): JsonResponse
    {
        return $this->error($message, 404);
    }

    // ── Réponse 403 ────────────────────────────────────────
    protected function forbidden(string $message = 'Accès non autorisé'): JsonResponse
    {
        return $this->error($message, 403);
    }

    // ── Réponse sans contenu ───────────────────────────────
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}