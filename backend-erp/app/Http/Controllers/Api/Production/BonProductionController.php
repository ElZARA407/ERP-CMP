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

        $query = BonProduction::with('location', 'produit', 'machine', 'createur');

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
            'numero' => BonProduction::generateReference('OF',3,'y'),
            ...$request->validated(),
            'statut' => StatutProduction::OUVERT->value,
            'cout_total' => 0,
            'created_by' => auth()->id(),
        ]);

        return $this->created(
            new BonProductionResource($bp->load('location', 'produit', 'machine'))
        );
    }

    public function show(BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('view', $bonsProduction);

        $bonsProduction->load(
            'location',
            'produit',
            'machine',
            'createur',
            'sessions.machine',
            'sessions.matieres.matiere',
            'sessions.obtenus.produit',
            'sessions.obtenus.classement',
            'sessions.obtenus.destination',
            'sessions.employes.employe.poste',
            'sessions.evenements',
            'sessions.calcul'
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
            'machine_id' => ['sometimes', 'exists:machines,id'],
            'quantite_cible' => ['sometimes', 'numeric', 'min:0.001'],
        ]);

        $bonsProduction->update($validated);

        return $this->success(
            new BonProductionResource($bonsProduction->fresh(['location', 'produit', 'machine'])),
            'Bon de production mis à jour.'
        );
    }

    public function destroy(BonProduction $bonsProduction): JsonResponse
    {
        return $this->forbidden('Les bons de production ne peuvent pas être supprimés.');
    }

    // public function cloture(BonProduction $bonsProduction): JsonResponse
    // {
    //     $this->authorize('cloture', $bonsProduction);

    //     try {
    //         $this->productionService->cloturerBP($bonsProduction, auth()->user());
    //     } catch (\DomainException $e) {
    //         return $this->error($e->getMessage(), 422);
    //     }

    //     return $this->success(
    //         new BonProductionResource($bonsProduction->fresh(['location', 'produit', 'machine'])),
    //         'Bon de production clôturé.'
    //     );
    // }
    public function cloture(BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('cloture', $bonsProduction);

        $bp = BonProduction::query()
            ->withCount('sessions')
            ->whereKey($bonsProduction->id)
            ->firstOrFail();

        $statut = (string) $bp->getRawOriginal('statut');

        if ($statut !== StatutProduction::EN_COURS->value) {
            return $this->error('Seul un BP en cours peut être clôturé.', 422);
        }

        if ($bp->quantiteTotaleProduite() < (float) $bp->quantite_cible) {
            return $this->error('La quantité cible du BP n’est pas encore atteinte.', 422);
        }

        $this->productionService->cloturerBP($bp, auth()->user());

        return $this->success(
            new BonProductionResource($bp->fresh(['location', 'produit', 'machine'])),
            'Bon de production clôturé.'
        );
    }

    public function annuler(BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('annuler', $bonsProduction);

        $bp = BonProduction::query()
            ->withCount('sessions')
            ->whereKey($bonsProduction->id)
            ->firstOrFail();

        $statut = (string) $bp->getRawOriginal('statut');

        if ($statut !== StatutProduction::OUVERT->value) {
            return $this->error('Seul un BP ouvert peut être annulé.', 422);
        }

        if ((int) $bp->sessions_count > 0) {
            return $this->error('Ce BP contient déjà des sessions et ne peut plus être annulé.', 422);
        }

        $bp->update(['statut' => StatutProduction::ANNULE->value]); 

        return $this->success(
            new BonProductionResource($bp->fresh(['location', 'produit', 'machine'])),
            'Bon de production annulé.'
        );
    }
}
