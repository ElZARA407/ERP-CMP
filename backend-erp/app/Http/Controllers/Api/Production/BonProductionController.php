<?php
// app/Http/Controllers/Api/Production/BonProductionController.php

namespace App\Http\Controllers\Api\Production;

use App\Enums\StatutProduction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Production\StoreBonProductionRequest;
use App\Http\Resources\BonProductionResource;
use App\Models\BonProduction;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonProductionController extends BaseApiController
{
    public function __construct(
        private readonly ProductionService $productionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BonProduction::class);

        $query = BonProduction::with('location', 'produit', 'createur');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('produit_id')) {
            $query->where('produit_id', $request->produit_id);
        }

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }

        $bps = $query
            ->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            BonProductionResource::collection($bps)->response()->getData(true)
        );
    }

    public function store(StoreBonProductionRequest $request): JsonResponse
    {
        $this->authorize('create', BonProduction::class);

        $bp = BonProduction::create([
            'numero'             => BonProduction::generateReference('BP'),
            ...$request->validated(),
            'statut'             => StatutProduction::OUVERT->value,
            'cout_total'         => 0,
            'created_by'         => auth()->id(),
        ]);

        return $this->created(
            new BonProductionResource($bp->load('location', 'produit'))
        );
    }

    public function show(BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('view', $bonsProduction);

        $bonsProduction->load(
            'location',
            'produit',
            'createur',
            'sessions.matieres.matiere',
            'sessions.obtenus.classement.produit',
            'sessions.employes.employe',
            'sessions.evenements'
        );

        return $this->success(new BonProductionResource($bonsProduction));
    }

    public function update(Request $request, BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('update', $bonsProduction);

        if (!$bonsProduction->statut->estActif()) {
            return $this->error('Ce bon de production ne peut plus être modifié.', 422);
        }

        $validated = $request->validate([
            'machine_production' => ['sometimes', 'string', 'max:100'],
            'quantite_cible'     => ['sometimes', 'numeric', 'min:0.001'],
        ]);

        $bonsProduction->update($validated);

        return $this->success(
            new BonProductionResource($bonsProduction->fresh(['location', 'produit'])),
            'Bon de production mis à jour.'
        );
    }

    public function destroy(BonProduction $bonsProduction): JsonResponse
    {
        return $this->forbidden('Les bons de production ne peuvent pas être supprimés.');
    }

    public function cloture(BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('cloture', $bonsProduction);

        try {
            $this->productionService->cloturerBP($bonsProduction, auth()->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new BonProductionResource($bonsProduction->fresh(['location', 'produit'])),
            'Bon de production clôturé.'
        );
    }

    public function annuler(BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('annuler', $bonsProduction);

        if ($bonsProduction->statut !== StatutProduction::OUVERT) {
            return $this->error('Seul un BP ouvert peut être annulé.', 422);
        }

        $bonsProduction->update(['statut' => StatutProduction::ANNULE->value]);

        return $this->success(
            new BonProductionResource($bonsProduction->fresh(['location', 'produit'])),
            'Bon de production annulé.'
        );
    }
}