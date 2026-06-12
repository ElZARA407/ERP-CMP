<?php
// app/Http/Controllers/Api/Commercial/ClientController.php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Commercial\StoreClientRequest;
use App\Http\Requests\Commercial\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends BaseApiController
{
    // ── GET /clients ───────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        // Filtres
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('reference', 'like', "%{$request->search}%")
                  ->orWhere('contact', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        $clients = $query
            ->orderBy('nom')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            ClientResource::collection($clients)->response()->getData(true)
        );
    }

    // ── POST /clients ──────────────────────────────────────
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return $this->created(new ClientResource($client));
    }

    // ── GET /clients/{id} ──────────────────────────────────
    public function show(Client $client): JsonResponse
    {
        return $this->success(new ClientResource($client));
    }

    // ── PUT /clients/{id} ─────────────────────────────────
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return $this->success(
            new ClientResource($client->fresh()),
            'Client mis à jour.'
        );
    }

    // ── DELETE /clients/{id} ──────────────────────────────
    public function destroy(Client $client): JsonResponse
    {
        $client->delete(); // Soft delete

        return $this->success(null, 'Client désactivé.');
    }

    // ── GET /clients/{id}/encours ─────────────────────────
    public function encours(Client $client): JsonResponse
    {
        return $this->success([
            'client_id'      => $client->id,
            'client_nom'     => $client->nom,
            'encours_total'  => $client->encoursTotalImpaye(),
            'factures'       => $client->factures()
                ->whereIn('statut', ['emise', 'partiellement_payee'])
                ->with('livraison')
                ->get()
                ->map(fn($f) => [
                    'numero'            => $f->numero,
                    'date'              => $f->date?->toDateString(),
                    'total'             => (float) $f->total,
                    'echeance'          => $f->echeance_paiement?->toDateString(),
                    'jours_retard'      => $f->joursDeRetard(),
                ]),
        ]);
    }

    // ── GET /clients/{id}/historique ──────────────────────
    public function historique(Client $client, Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        return $this->success([
            'client_id'          => $client->id,
            'annee'              => $annee,
            'ca_annuel'          => $client->chiffreAffairesAnnuel($annee),
            'nb_commandes'       => $client->commandes()->whereYear('date', $annee)->count(),
            'nb_factures'        => $client->factures()->whereYear('date', $annee)->count(),
        ]);
    }
}