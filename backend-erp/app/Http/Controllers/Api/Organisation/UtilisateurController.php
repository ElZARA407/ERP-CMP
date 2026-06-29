<?php
// app/Http/Controllers/Api/Organisation/UtilisateurController.php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Organisation\StoreUtilisateurRequest;
use App\Http\Requests\Organisation\UpdateUtilisateurRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtilisateurController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Utilisateur::with('role', 'location');

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $utilisateurs = $query
            ->orderBy('nom')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            UtilisateurResource::collection($utilisateurs)->response()->getData(true)
        );
    }

    public function store(StoreUtilisateurRequest $request): JsonResponse
    {
        $utilisateur = Utilisateur::create($request->validated());

        return $this->created(
            new UtilisateurResource($utilisateur->load('role', 'location'))
        );
    }

    public function show(Utilisateur $utilisateur): JsonResponse
    {
        $utilisateur->load('role', 'location');

        return $this->success(new UtilisateurResource($utilisateur));
    }

    public function update(UpdateUtilisateurRequest $request, Utilisateur $utilisateur): JsonResponse
    {
        $utilisateur->update($request->validated());

        return $this->success(
            new UtilisateurResource($utilisateur->fresh('role', 'location')),
            'Utilisateur mis à jour.'
        );
    }

    public function destroy(Request $request, Utilisateur $utilisateur): JsonResponse
    {
        if ($utilisateur->id === $request->user()->id) {
            return $this->error('Vous ne pouvez pas supprimer votre propre compte.', 422);
        }

        $utilisateur->delete();

        return $this->success(null, 'Utilisateur supprimé.');
    }

    public function toggleActif(Request $request, Utilisateur $utilisateur): JsonResponse
    {
        if ($utilisateur->id === $request->user()->id) {
            return $this->error('Impossible de désactiver votre propre compte.', 422);
        }

        $utilisateur->update(['actif' => !$utilisateur->actif]);

        $message = $utilisateur->fresh()->actif
            ? 'Compte activé.'
            : 'Compte désactivé.';

        return $this->success(
            new UtilisateurResource($utilisateur->fresh('role', 'location')),
            $message
        );
    }
}