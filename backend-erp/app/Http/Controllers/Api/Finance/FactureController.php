<?php

namespace App\Http\Controllers\Api\Finance;

use App\Enums\ModePaiement;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Finance\PayerFactureRequest;
use App\Http\Requests\Finance\StoreFactureRequest;
use App\Http\Resources\FactureResource;
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
        $query = Facture::with('client', 'livraison', 'livraisons');

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

    public function preview(StoreFactureRequest $request): JsonResponse
    {
        try {
            $aperçu = $this->factureService->previsualiserDepuisLivraisons(
                $this->getLivraisonIds($request),
                $this->getLignesOverrides($request)
            );
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($aperçu, 'Aperçu facture calculé.');
    }

    public function store(StoreFactureRequest $request): JsonResponse
    {
        try {
            $facture = $this->factureService->creerDepuisLivraisons(
                $this->getLivraisonIds($request),
                $request->user(),
                $this->getLignesOverrides($request)
            );
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->created(
            new FactureResource(
                $facture->load(
                    'client',
                    'livraison',
                    'livraisons',
                    'lignes.produit',
                    'lignes.classement'
                )
            )
        );
    }

    public function show(Facture $facture): JsonResponse
    {
        $facture->load(
            'client',
            'livraison',
            'livraisons',
            'lignes.produit',
            'lignes.classement',
            'createur'
        );

        return $this->success(new FactureResource($facture));
    }

    public function update(Request $request, Facture $facture): JsonResponse
    {
        $this->authorize('update', $facture);

        $facture->update($request->only(['notes', 'echeance_paiement']));

        return $this->success(
            new FactureResource(
                $facture->fresh(
                    'client',
                    'livraison',
                    'livraisons',
                    'lignes.produit',
                    'lignes.classement'
                )
            ),
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
                (float) $request->montant_paye,
                $request->user()
            );
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            new FactureResource(
                $facture->fresh(
                    'client',
                    'livraison',
                    'livraisons',
                    'lignes.produit',
                    'lignes.classement'
                )
            ),
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
            new FactureResource(
                $facture->fresh(
                    'client',
                    'livraison',
                    'livraisons',
                    'lignes.produit',
                    'lignes.classement'
                )
            ),
            'Facture annulee.'
        );
    }

    public function enRetard(): JsonResponse
    {
        $factures = Facture::enRetard()
            ->with('client', 'livraison', 'livraisons')
            ->orderBy('echeance_paiement')
            ->get();

        return $this->success(FactureResource::collection($factures));
    }

    private function getLivraisonIds(StoreFactureRequest $request): array
    {
        $validated = $request->validated();

        if (! empty($validated['livraison_ids']) && is_array($validated['livraison_ids'])) {
            return array_values(array_map('intval', $validated['livraison_ids']));
        }

        if (! empty($validated['livraison_id'])) {
            return [(int) $validated['livraison_id']];
        }

        return [];
    }

    private function getLignesOverrides(StoreFactureRequest $request): array
    {
        $validated = $request->validated();

        return isset($validated['lignes']) && is_array($validated['lignes'])
            ? $validated['lignes']
            : [];
    }
}