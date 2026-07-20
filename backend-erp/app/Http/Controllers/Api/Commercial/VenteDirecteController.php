<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
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
        $query = VenteDirecte::query()->with([
            'client',
            'location',
            'createur',
            'lignes.produit',
            'lignes.classement',
            'lignes.lignesLivraison',
        ]);

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
            ->paginate($request->integer('per_page', (int) config('api.per_page')));

        $items = $ventes->getCollection()
            ->map(fn (VenteDirecte $vente) => $this->formatVenteDirecte($vente))
            ->values();

        return $this->success([
            'data' => $items,
            'current_page' => $ventes->currentPage(),
            'last_page' => $ventes->lastPage(),
            'per_page' => $ventes->perPage(),
            'total' => $ventes->total(),
            'from' => $ventes->firstItem() ?? 0,
            'to' => $ventes->lastItem() ?? 0,
        ]);
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

            $vente->load([
                'client',
                'location',
                'lignes.produit',
                'lignes.classement',
                'livraisons',
            ]);

            return $vente;
        });

        return $this->created($this->formatVenteDirecte($vente));
    }

    public function show(int|string $venteDirecte): JsonResponse
    {
        $vente = $this->findVenteDirecteOrFail($venteDirecte, [
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'createur',
            'livraisons',
            'lignes.lignesLivraison',
        ]);

        return $this->success($this->formatVenteDirecte($vente));
    }

    public function update(Request $request, int|string $venteDirecte): JsonResponse
    {
        $vente = $this->findVenteDirecteOrFail($venteDirecte, [
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'createur',
            'livraisons',
        ]);

        if ($vente->statut !== 'brouillon') {
            return $this->error('Cette vente ne peut plus etre modifiee.', 422);
        }

        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'location_id' => ['sometimes', 'exists:locations,id'],
        ]);

        $vente->update($validated);
        $vente->load([
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'createur',
            'livraisons',
        ]);

        return $this->success(
            $this->formatVenteDirecte($vente),
            'Vente directe mise a jour.'
        );
    }

    public function destroy(int|string $venteDirecte): JsonResponse
    {
        $vente = $this->findVenteDirecteOrFail($venteDirecte);

        if ($vente->statut !== 'brouillon') {
            return $this->error('Seule une vente en brouillon peut etre supprimee.', 422);
        }

        $vente->delete();

        return $this->success(null, 'Vente directe supprimee.');
    }

    public function valider(Request $request, int|string $venteDirecte): JsonResponse
    {
        $vente = $this->findVenteDirecteOrFail($venteDirecte, [
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'livraisons',
        ]);

        if ($vente->statut !== 'brouillon') {
            return $this->error('Cette vente est deja validee.', 422);
        }

        $operateur = $request->user();

        if (! $operateur instanceof Utilisateur) {
            return $this->error('Utilisateur authentifie invalide.', 422);
        }

        DB::transaction(function () use ($vente, $operateur) {
            $vente->loadMissing('lignes.produit', 'lignes.classement');

            if ($vente->lignes->isEmpty()) {
                throw new \DomainException('Impossible de valider une vente sans lignes.');
            }

            foreach ($vente->lignes as $ligne) {
                if (! $ligne->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de vente.');
                }

                $this->stockService->sortie(
                    locationId: $vente->location_id,
                    entiteType: 'produit',
                    entiteId: (int) $ligne->produit_id,
                    quantite: (float) $ligne->quantite,
                    referenceType: 'vente_directe',
                    referenceId: $vente->id,
                    operateur: $operateur,
                    classementId: $ligne->classement_id
                );
            }

            $vente->update([
                'statut' => 'validee',
            ]);
        });

        $vente->load([
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'createur',
            'livraisons',
        ]);

        return $this->success(
            $this->formatVenteDirecte($vente),
            'Vente directe validee. Stocks decrementes.'
        );
    }

    public function annuler(Request $request, int|string $venteDirecte): JsonResponse
    {
        $vente = $this->findVenteDirecteOrFail($venteDirecte, [
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'livraisons',
        ]);

        if ($vente->statut !== 'validee') {
            return $this->error('Seule une vente validee peut etre annulee.', 422);
        }

        if ($vente->livraisons()->exists()) {
            return $this->error('Cette vente ne peut pas etre annulee car un BL est deja rattache.', 422);
        }

        $operateur = $request->user();

        if (! $operateur instanceof Utilisateur) {
            return $this->error('Utilisateur authentifie invalide.', 422);
        }

        DB::transaction(function () use ($vente, $operateur) {
            $vente->loadMissing('lignes.produit', 'lignes.classement');

            if ($vente->lignes->isEmpty()) {
                throw new \DomainException('Impossible d annuler une vente sans lignes.');
            }

            foreach ($vente->lignes as $ligne) {
                if (! $ligne->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de vente.');
                }

                $this->stockService->retour(
                    locationId: $vente->location_id,
                    entiteType: 'produit',
                    entiteId: (int) $ligne->produit_id,
                    quantite: (float) $ligne->quantite,
                    referenceType: 'vente_directe_annulee',
                    referenceId: $vente->id,
                    operateur: $operateur,
                    classementId: $ligne->classement_id
                );
            }

            $vente->update([
                'statut' => 'annulee',
            ]);
        });

        $vente->load([
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'createur',
            'livraisons',
        ]);

        return $this->success(
            $this->formatVenteDirecte($vente),
            'Vente directe annulee. Stocks recredites.'
        );
    }

    private function findVenteDirecteOrFail(int|string $venteDirecte, array $relations = []): VenteDirecte
    {
        $id = (int) $venteDirecte;

        if ($id <= 0) {
            abort(404, 'Vente directe introuvable.');
        }

        $query = VenteDirecte::query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    private function formatVenteDirecte(VenteDirecte $venteDirecte): array
    {
        $venteDirecte->loadMissing([
            'client',
            'location',
            'lignes.produit',
            'lignes.classement',
            'livraisons',
        ]);

        return [
            'id' => $venteDirecte->id,
            'numero' => $venteDirecte->numero,
            'date' => $this->formatDateValue($venteDirecte->date),
            'statut' => $venteDirecte->statut,
            'total' => (float) $venteDirecte->total,

            'client' => $venteDirecte->client ? [
                'id' => $venteDirecte->client->id,
                'nom' => $venteDirecte->client->nom,
            ] : null,

            'location' => $venteDirecte->location ? [
                'id' => $venteDirecte->location->id,
                'nom' => $venteDirecte->location->nom,
            ] : null,
            
            'lignes' => $venteDirecte->lignes->map(function ($ligne) {
                $produit = $ligne->produit;
                $classement = $ligne->classement;

                $quantiteLivree = $ligne->relationLoaded('lignesLivraison')
                    ? (float) $ligne->lignesLivraison->sum('quantite_livree')
                    : (float) $ligne->lignesLivraison()->sum('quantite_livree');

                $quantiteRestante = max(0, (float) $ligne->quantite - $quantiteLivree);

                return [
                    'id' => $ligne->id,
                    'produit_id' => $ligne->produit_id,
                    'classement_id' => $ligne->classement_id,
                    'quantite' => (float) $ligne->quantite,
                    'quantite_restante' => $quantiteRestante,
                    'prix_unitaire' => (float) $ligne->prix_unitaire,
                    'total_ligne' => (float) $ligne->total_ligne,

                    'produit' => $produit ? [
                        'id' => $produit->id,
                        'nomencla' => $produit->nomencla,
                        'designation' => $produit->designation,
                    ] : null,

                    'classement' => $classement ? [
                        'id' => $classement->id,
                        'qualite' => is_object($classement->qualite) && property_exists($classement->qualite, 'value')
                            ? $classement->qualite->value
                            : $classement->qualite,
                        'libelle' => $classement->libelle,
                        'designation' => method_exists($classement, 'label')
                            ? $classement->label()
                            : ($classement->libelle ?? null),
                    ] : null,
                ];
            })->values(),

            'livraisons' => $venteDirecte->livraisons->map(function ($livraison) {
                return [
                    'id' => $livraison->id,
                    'numero' => $livraison->numero,
                    'source_type' => $livraison->source_type,
                    'source_id' => $livraison->source_id,
                    'date_livraison' => $this->formatDateValue($livraison->date_livraison),
                    'statut' => $livraison->statut,
                    'est_facturee' => $livraison->estFacturee(),
                    'created_at' => $this->formatDateTimeValue($livraison->created_at),
                ];
            })->values(),

            'created_at' => $this->formatDateTimeValue($venteDirecte->created_at),
        ];
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        return null;
    }

    private function formatDateTimeValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}

// namespace App\Http\Controllers\Api\Commercial;

// use App\Http\Controllers\Api\BaseApiController;
// use App\Http\Resources\VenteDirecteResource;
// use App\Models\Utilisateur;
// use App\Models\VenteDirecte;
// use App\Services\StockService;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;

// class VenteDirecteController extends BaseApiController
// {
//     public function __construct(
//         private readonly StockService $stockService
//     ) {}

//     public function index(Request $request): JsonResponse
//     {
//         $query = VenteDirecte::with('client', 'location', 'createur');

//         if ($request->filled('client_id')) {
//             $query->where('client_id', $request->client_id);
//         }

//         if ($request->filled('statut')) {
//             $query->where('statut', $request->statut);
//         }

//         if ($request->filled('date_debut')) {
//             $query->where('date', '>=', $request->date_debut);
//         }

//         if ($request->filled('date_fin')) {
//             $query->where('date', '<=', $request->date_fin);
//         }

//         $ventes = $query
//             ->orderByDesc('date')
//             ->paginate($request->get('per_page', config('api.per_page')));

//         return $this->success(
//             VenteDirecteResource::collection($ventes)->response()->getData(true)
//         );
//     }

//     public function store(Request $request): JsonResponse
//     {
//         $validated = $request->validate([
//             'client_id' => ['required', 'exists:clients,id'],
//             'date' => ['required', 'date'],
//             'location_id' => ['required', 'exists:locations,id'],
//             'lignes' => ['required', 'array', 'min:1'],
//             'lignes.*.produit_id' => ['required', 'exists:produits,id'],
//             'lignes.*.classement_id' => ['required', 'exists:classement_produits,id'],
//             'lignes.*.quantite' => ['required', 'numeric', 'min:0.001'],
//             'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
//         ]);

//         $vente = DB::transaction(function () use ($validated, $request) {
//             $lignes = $validated['lignes'];
//             unset($validated['lignes']);

//             $total = array_sum(array_map(
//                 fn ($ligne) => round($ligne['quantite'] * $ligne['prix_unitaire'], 2),
//                 $lignes
//             ));

//             $vente = VenteDirecte::create([
//                 'numero' => VenteDirecte::generateReference('VD'),
//                 ...$validated,
//                 'statut' => 'brouillon',
//                 'total' => $total,
//                 'created_by' => $request->user()->id,
//             ]);

//             foreach ($lignes as $ligne) {
//                 $vente->lignes()->create([
//                     'produit_id' => $ligne['produit_id'],
//                     'classement_id' => $ligne['classement_id'],
//                     'quantite' => $ligne['quantite'],
//                     'prix_unitaire' => $ligne['prix_unitaire'],
//                     'total_ligne' => round($ligne['quantite'] * $ligne['prix_unitaire'], 2),
//                 ]);
//             }

//             return $vente->load('client', 'location', 'lignes.produit', 'lignes.classement');
//         });

//         return $this->created(new VenteDirecteResource($vente));
//     }

//     public function show(VenteDirecte $venteDirecte): JsonResponse
//     {
//         $venteDirecte->load([
//             'client',
//             'location',
//             'lignes.produit',
//             'lignes.classement',
//             'createur',
//             'livraisons',
//         ]);

//         return $this->success(new VenteDirecteResource($venteDirecte));
//     }

//     public function update(Request $request, VenteDirecte $venteDirecte): JsonResponse
//     {
//         if ($venteDirecte->statut !== 'brouillon') {
//             return $this->error('Cette vente ne peut plus etre modifiee.', 422);
//         }

//         $venteDirecte->update($request->only(['date', 'location_id']));

//         return $this->success(
//             new VenteDirecteResource(
//                 $venteDirecte->fresh('client', 'location', 'lignes.produit', 'lignes.classement', 'createur', 'livraisons')
//             ),
//             'Vente directe mise a jour.'
//         );
//     }

//     public function destroy(VenteDirecte $venteDirecte): JsonResponse
//     {
//         if ($venteDirecte->statut !== 'brouillon') {
//             return $this->error('Seule une vente en brouillon peut etre supprimee.', 422);
//         }

//         $venteDirecte->delete();

//         return $this->success(null, 'Vente directe supprimee.');
//     }

//     public function valider(Request $request, VenteDirecte $venteDirecte): JsonResponse
//     {
//         if ($venteDirecte->statut !== 'brouillon') {
//             return $this->error('Cette vente est deja validee.', 422);
//         }

//         $operateur = $request->user();

//         if (! $operateur instanceof Utilisateur) {
//             return $this->error('Utilisateur authentifie invalide.', 422);
//         }

//         DB::transaction(function () use ($venteDirecte, $operateur) {
//             $venteDirecte->loadMissing('lignes.produit', 'lignes.classement');

//             if ($venteDirecte->lignes->isEmpty()) {
//                 throw new \DomainException('Impossible de valider une vente sans lignes.');
//             }

//             foreach ($venteDirecte->lignes as $ligne) {
//                 if (! $ligne->produit_id) {
//                     throw new \DomainException('Produit manquant sur une ligne de vente.');
//                 }

//                 $this->stockService->sortie(
//                     locationId: $venteDirecte->location_id,
//                     entiteType: 'produit',
//                     entiteId: (int) $ligne->produit_id,
//                     quantite: (float) $ligne->quantite,
//                     referenceType: 'vente_directe',
//                     referenceId: $venteDirecte->id,
//                     operateur: $operateur,
//                     classementId: $ligne->classement_id
//                 );
//             }

//             $venteDirecte->update([
//                 'statut' => 'validee',
//             ]);
//         });

//         return $this->success(
//             new VenteDirecteResource(
//                 $venteDirecte->fresh('client', 'location', 'lignes.produit', 'lignes.classement', 'createur', 'livraisons')
//             ),
//             'Vente directe validee. Stocks decrementes.'
//         );
//     }

//     public function annuler(Request $request, VenteDirecte $venteDirecte): JsonResponse
//     {
//         if ($venteDirecte->statut !== 'validee') {
//             return $this->error('Seule une vente validee peut etre annulee.', 422);
//         }

//         if ($venteDirecte->livraisons()->exists()) {
//             return $this->error('Cette vente ne peut pas etre annulee car un BL est deja rattache.', 422);
//         }

//         $operateur = $request->user();

//         if (! $operateur instanceof Utilisateur) {
//             return $this->error('Utilisateur authentifie invalide.', 422);
//         }

//         DB::transaction(function () use ($venteDirecte, $operateur) {
//             $venteDirecte->loadMissing('lignes.produit', 'lignes.classement');

//             if ($venteDirecte->lignes->isEmpty()) {
//                 throw new \DomainException('Impossible d annuler une vente sans lignes.');
//             }

//             foreach ($venteDirecte->lignes as $ligne) {
//                 if (! $ligne->produit_id) {
//                     throw new \DomainException('Produit manquant sur une ligne de vente.');
//                 }

//                 $this->stockService->retour(
//                     locationId: $venteDirecte->location_id,
//                     entiteType: 'produit',
//                     entiteId: (int) $ligne->produit_id,
//                     quantite: (float) $ligne->quantite,
//                     referenceType: 'vente_directe_annulee',
//                     referenceId: $venteDirecte->id,
//                     operateur: $operateur,
//                     classementId: $ligne->classement_id
//                 );
//             }

//             $venteDirecte->update([
//                 'statut' => 'annulee',
//             ]);
//         });

//         return $this->success(
//             new VenteDirecteResource(
//                 $venteDirecte->fresh('client', 'location', 'lignes.produit', 'lignes.classement', 'createur', 'livraisons')
//             ),
//             'Vente directe annulee. Stocks recredites.'
//         );
//     }
// }