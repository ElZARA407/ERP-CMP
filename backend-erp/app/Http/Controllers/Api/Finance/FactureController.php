<?php

namespace App\Http\Controllers\Api\Finance;

use App\Enums\ModePaiement;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Finance\PayerFactureRequest;
use App\Http\Requests\Finance\StoreFactureRequest;
use App\Http\Resources\FactureResource;
use App\Models\Facture;
use App\Models\Livraison;
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

        $factures = $query
            ->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            FactureResource::collection($factures)->response()->getData(true)
        );
    }

    public function store(StoreFactureRequest $request): JsonResponse
    {
        try {
            $livraison = Livraison::findOrFail($request->livraison_id);
            $facture = $this->factureService->creerDepuisLivraison($livraison, $request->user());
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->created(
            new FactureResource($facture->load('client', 'livraison', 'lignes.produit', 'lignes.classement'))
        );
    }

    public function show(Facture $facture): JsonResponse
    {
        $facture->load('client', 'livraison', 'lignes.produit', 'lignes.classement', 'createur');

        return $this->success(new FactureResource($facture));
    }

    public function update(Request $request, Facture $facture): JsonResponse
    {
        $this->authorize('update', $facture);

        $facture->update($request->only(['notes', 'echeance_paiement']));

        return $this->success(
            new FactureResource($facture->fresh('client', 'livraison', 'lignes.produit', 'lignes.classement')),
            'Facture mise a jour.'
        );
    }

    public function destroy(Facture $facture): JsonResponse
    {
        return $this->forbidden('Les factures ne peuvent pas etre supprimees.');
    }

    public function payer(PayerFactureRequest $request, Facture $facture): JsonResponse
    {
        $this->authorize('payer', $facture);

        try {
            $this->factureService->enregistrerPaiement(
                $facture,
                ModePaiement::from($request->mode_paiement),
                $request->user()
            );
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            new FactureResource($facture->fresh('client', 'livraison', 'lignes.produit', 'lignes.classement')),
            'Paiement enregistre.'
        );
    }

    public function annuler(Request $request, Facture $facture): JsonResponse
    {
        $this->authorize('annuler', $facture);

        try {
            $this->factureService->annuler($facture, $request->user());
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            new FactureResource($facture->fresh('client', 'livraison', 'lignes.produit', 'lignes.classement')),
            'Facture annulee.'
        );
    }

    public function enRetard(): JsonResponse
    {
        $factures = Facture::enRetard()
            ->with('client', 'livraison')
            ->orderBy('echeance_paiement')
            ->get();

        return $this->success(FactureResource::collection($factures));
    }
}