<?php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\BonSortieResource;
use App\Models\BonSortie;
use App\Models\LigneSortie;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonSortieController extends BaseApiController
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = BonSortie::with('location', 'client', 'createur');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('motif')) {
            $query->where('motif', $request->motif);
        }

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }

        $bons = $query
            ->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            BonSortieResource::collection($bons)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'date' => ['required', 'date'],
            'motif' => ['required', 'in:usage_interne,perte,echantillon,don,autre'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'observations' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'exists:produits,id'],
            'lignes.*.classement_id' => ['required', 'exists:classement_produits,id'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.001'],
        ]);

        $bon = DB::transaction(function () use ($validated) {
            $lignes = $validated['lignes'];
            unset($validated['lignes']);

            $bon = BonSortie::create([
                'numero' => BonSortie::generateReference('BS'),
                ...$validated,
                'statut' => 'brouillon',
                'created_by' => auth()->id(),
            ]);

            foreach ($lignes as $ligne) {
                LigneSortie::create([
                    'bon_sortie_id' => $bon->id,
                    ...$ligne,
                ]);
            }

            return $bon->load('location', 'client', 'lignes.produit', 'lignes.classement');
        });

        return $this->created(new BonSortieResource($bon));
    }

    public function show(BonSortie $bonsSortie): JsonResponse
    {
        $bonsSortie->load('location', 'client', 'lignes.produit', 'lignes.classement', 'createur');

        return $this->success(new BonSortieResource($bonsSortie));
    }

    public function update(Request $request, BonSortie $bonsSortie): JsonResponse
    {
        if ($bonsSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus etre modifie.', 422);
        }

        $bonsSortie->update($request->only(['observations', 'motif', 'client_id']));

        return $this->success(
            new BonSortieResource($bonsSortie->fresh('location', 'client', 'lignes.produit', 'lignes.classement')),
            'Bon de sortie mis a jour.'
        );
    }

    public function destroy(BonSortie $bonsSortie): JsonResponse
    {
        if ($bonsSortie->statut !== 'brouillon') {
            return $this->error('Seul un BS en brouillon peut etre supprime.', 422);
        }

        $bonsSortie->delete();

        return $this->success(null, 'Bon de sortie supprime.');
    }

    public function valider(BonSortie $bonsSortie): JsonResponse
    {
        if (!$bonsSortie->estValidable()) {
            return $this->error('Ce bon de sortie ne peut pas etre valide.', 422);
        }

        DB::transaction(function () use ($bonsSortie) {
            $bonsSortie->loadMissing('lignes.produit', 'lignes.classement');

            foreach ($bonsSortie->lignes as $ligne) {
                if (!$ligne->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de sortie.');
                }

                $this->stockService->sortie(
                    locationId: $bonsSortie->location_id,
                    entiteType: 'produit',
                    entiteId: $ligne->produit_id,
                    quantite: (float) $ligne->quantite,
                    referenceType: 'bon_sortie',
                    referenceId: $bonsSortie->id,
                    operateur: auth()->user(),
                    classementId: $ligne->classement_id
                );
            }

            $bonsSortie->update([
                'statut' => 'valide',
                'valide_by' => auth()->id(),
            ]);
        });

        return $this->success(
            new BonSortieResource($bonsSortie->fresh('location', 'client', 'lignes.produit', 'lignes.classement')),
            'Bon de sortie valide. Stocks decrementes.'
        );
    }
}