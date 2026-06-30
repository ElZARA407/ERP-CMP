<?php
// app/Http/Controllers/Api/Finance/FactureController.php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Finance\PayerFactureRequest;
use App\Http\Requests\Finance\StoreFactureRequest;
use App\Http\Resources\FactureResource;
use App\Enums\ModePaiement;
use App\Models\Facture;
use App\Services\FactureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactureController extends BaseApiController
{
    public function __construct(
        private readonly FactureService $factureService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Facture::with('client', 'livraison');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->boolean('en_retard')) {
            $query->enRetard();
        }

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }

        $factures = $query->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            FactureResource::collection($factures)->response()->getData(true)
        );
    }

    public function store(StoreFactureRequest $request): JsonResponse
    {
        try {
            $livraison = \App\Models\Livraison::findOrFail($request->livraison_id);
            $facture = $this->factureService->creerDepuisLivraison($livraison, $request->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created(
            new FactureResource($facture->load('client', 'livraison', 'lignes.classement.produit'))
        );
    }

    public function show(Facture $facture): JsonResponse
    {
        $facture->load('client', 'livraison', 'lignes.classement.produit', 'createur');

        return $this->success(new FactureResource($facture));
    }

    public function update(Request $request, Facture $facture): JsonResponse
    {
        $this->authorize('update', $facture);

        $facture->update($request->only(['notes', 'echeance_paiement']));

        return $this->success(new FactureResource($facture->fresh()), 'Facture mise à jour.');
    }

    public function destroy(Facture $facture): JsonResponse
    {
        return $this->forbidden('Les factures ne peuvent pas être supprimées.');
    }

    // ── POST /factures/{id}/payer ─────────────────────────
    public function payer(PayerFactureRequest $request, Facture $facture): JsonResponse
    {
        $this->authorize('payer', $facture);

        try {
            $this->factureService->enregistrerPaiement(
                $facture,
                ModePaiement::from($request->mode_paiement),
                $request->user()
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new FactureResource($facture->fresh()->load('client', 'livraison', 'lignes.classement.produit')),
            'Paiement enregistré.'
        );
    }

    // ── POST /factures/{id}/annuler ───────────────────────
    public function annuler(Request $request, Facture $facture): JsonResponse
    {
        $this->authorize('annuler', $facture);

        try {
            $this->factureService->annuler($facture, $request->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new FactureResource($facture->fresh()->load('client', 'livraison', 'lignes.classement.produit')),
            'Facture annulée.'
        );
    }

    // ── GET /factures/retards ─────────────────────────────
    public function enRetard(): JsonResponse
    {
        $factures = Facture::enRetard()
            ->with('client', 'livraison')
            ->orderBy('echeance_paiement')
            ->get();

        return $this->success(FactureResource::collection($factures));
    }
}