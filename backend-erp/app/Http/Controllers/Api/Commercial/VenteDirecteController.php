<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\VenteDirecteResource;
use App\Models\Utilisateur;
use App\Models\VenteDirecte;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VenteDirecteController extends BaseApiController
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = VenteDirecte::with('client', 'location', 'createur');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }

        $ventes = $query
            ->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            VenteDirecteResource::collection($ventes)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'exists:produits,id'],
            'lignes.*.classement_id' => ['required', 'exists:classement_produits,id'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.001'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $vente = DB::transaction(function () use ($validated, $request) {
            $lignes = $validated['lignes'];
            unset($validated['lignes']);

            $total = array_sum(array_map(
                fn ($ligne) => round($ligne['quantite'] * $ligne['prix_unitaire'], 2),
                $lignes
            ));

            $vente = VenteDirecte::create([
                'numero' => VenteDirecte::generateReference('VD'),
                ...$validated,
                'statut' => 'brouillon',
                'total' => $total,
                'created_by' => $request->user()->id,
            ]);

            foreach ($lignes as $ligne) {
                $vente->lignes()->create([
                    'produit_id' => $ligne['produit_id'],
                    'classement_id' => $ligne['classement_id'],
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'total_ligne' => round($ligne['quantite'] * $ligne['prix_unitaire'], 2),
                ]);
            }

            return $vente->load('client', 'location', 'lignes.produit', 'lignes.classement');
        });

        return $this->created(new VenteDirecteResource($vente));
    }

    public function show(VenteDirecte $venteDirecte): JsonResponse
    {
        $venteDirecte->load(
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'createur',
            'livraisons'
        );

        return $this->success(new VenteDirecteResource($venteDirecte));
    }

    public function update(Request $request, VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Cette vente ne peut plus etre modifiee.', 422);
        }

        $venteDirecte->update($request->only(['date', 'location_id']));

        return $this->success(
            new VenteDirecteResource(
                $venteDirecte->fresh('client', 'location', 'lignes.produit', 'lignes.classement', 'createur', 'livraisons')
            ),
            'Vente directe mise a jour.'
        );
    }

    public function destroy(VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Seule une vente en brouillon peut etre supprimee.', 422);
        }

        $venteDirecte->delete();

        return $this->success(null, 'Vente directe supprimee.');
    }

    public function valider(Request $request, VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Cette vente est deja validee.', 422);
        }

        $operateur = $request->user();

        if (! $operateur instanceof Utilisateur) {
            return $this->error('Utilisateur authentifie invalide.', 422);
        }

        DB::transaction(function () use ($venteDirecte, $operateur) {
            $venteDirecte->loadMissing('lignes.produit', 'lignes.classement');

            if ($venteDirecte->lignes->isEmpty()) {
                throw new \DomainException('Impossible de valider une vente sans lignes.');
            }

            foreach ($venteDirecte->lignes as $ligne) {
                if (! $ligne->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de vente.');
                }

                $this->stockService->sortie(
                    locationId: $venteDirecte->location_id,
                    entiteType: 'produit',
                    entiteId: (int) $ligne->produit_id,
                    quantite: (float) $ligne->quantite,
                    referenceType: 'vente_directe',
                    referenceId: $venteDirecte->id,
                    operateur: $operateur,
                    classementId: $ligne->classement_id
                );
            }

            $venteDirecte->update([
                'statut' => 'validee',
            ]);
        });

        return $this->success(
            new VenteDirecteResource(
                $venteDirecte->fresh('client', 'location', 'lignes.produit', 'lignes.classement', 'createur', 'livraisons')
            ),
            'Vente directe validee. Stocks decrementes.'
        );
    }

    public function annuler(Request $request, VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'validee') {
            return $this->error('Seule une vente validee peut etre annulee.', 422);
        }

        if ($venteDirecte->livraisons()->exists()) {
            return $this->error('Cette vente ne peut pas etre annulee car un BL est deja rattache.', 422);
        }

        $operateur = $request->user();

        if (! $operateur instanceof Utilisateur) {
            return $this->error('Utilisateur authentifie invalide.', 422);
        }

        DB::transaction(function () use ($venteDirecte, $operateur) {
            $venteDirecte->loadMissing('lignes.produit', 'lignes.classement');

            if ($venteDirecte->lignes->isEmpty()) {
                throw new \DomainException('Impossible d annuler une vente sans lignes.');
            }

            foreach ($venteDirecte->lignes as $ligne) {
                if (! $ligne->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de vente.');
                }

                $this->stockService->retour(
                    locationId: $venteDirecte->location_id,
                    entiteType: 'produit',
                    entiteId: (int) $ligne->produit_id,
                    quantite: (float) $ligne->quantite,
                    referenceType: 'vente_directe_annulee',
                    referenceId: $venteDirecte->id,
                    operateur: $operateur,
                    classementId: $ligne->classement_id
                );
            }

            $venteDirecte->update([
                'statut' => 'annulee',
            ]);
        });

        return $this->success(
            new VenteDirecteResource(
                $venteDirecte->fresh('client', 'location', 'lignes.produit', 'lignes.classement', 'createur', 'livraisons')
            ),
            'Vente directe annulee. Stocks recredites.'
        );
    }
}