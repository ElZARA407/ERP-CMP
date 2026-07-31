<?php

namespace App\Http\Controllers\Api\Recyclage;

use App\Enums\StatutRecyclage;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\BonTransformationResource;
use App\Models\BonTransformation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonTransformationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = BonTransformation::with('location', 'matiereBrute', 'machine', 'createur');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('matiereBrute', fn ($matiere) => $matiere
                        ->where('nom', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                    )
                    ->orWhereHas('machine', fn ($machine) => $machine
                        ->where('nom', 'like', "%{$search}%")
                    );
            });
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->location_id);
        }

        if ($request->filled('matiere_brute_id')) {
            $query->where('matiere_brute_id', (int) $request->matiere_brute_id);
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_id', (int) $request->machine_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date', '<=', $request->date_fin);
        }

        $bts = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', config('api.per_page')));

        return $this->success(
            BonTransformationResource::collection($bts)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'matiere_brute_id' => ['required', 'exists:matieres_premieres,id'],
            'machine_id' => ['required', 'exists:machines,id'],
            'quantite_entree' => ['required', 'numeric', 'min:0.001'],
            'observations' => ['nullable', 'string'],
        ]);

        $bt = BonTransformation::create([
            'numero' => BonTransformation::generateReference('BT', 4, 'y'),
            'machine_broyage' => null,
            ...$validated,
            'statut' => StatutRecyclage::OUVERT->value,
            'created_by' => auth()->id(),
        ]);

        return $this->created(
            new BonTransformationResource(
                $bt->load('location', 'matiereBrute', 'machine')
            )
        );
    }

    public function show(BonTransformation $bonsTransformation): JsonResponse
    {
        $bonsTransformation->load(
            'location',
            'matiereBrute',
            'machine',
            'createur',
            'sessions.machine',
            'sessions.matieres.matiere',
            'sessions.employes.employe.poste',
            'sessions.evenements',
            'sessions.calcul'
        );

        return $this->success(new BonTransformationResource($bonsTransformation));
    }

    public function update(Request $request, BonTransformation $bonsTransformation): JsonResponse
    {
        if (!$bonsTransformation->statut->estActif()) {
            return $this->error('Ce bon de transformation ne peut plus être modifié.', 422);
        }

        $validated = $request->validate([
            'machine_id' => ['sometimes', 'exists:machines,id'],
            'quantite_entree' => ['sometimes', 'numeric', 'min:0.001'],
            'observations' => ['nullable', 'string'],
        ]);

        $bonsTransformation->update($validated);

        return $this->success(
            new BonTransformationResource($bonsTransformation->fresh(['location', 'matiereBrute', 'machine'])),
            'Bon de transformation mis à jour.'
        );
    }

    public function destroy(BonTransformation $bonsTransformation): JsonResponse
    {
        return $this->forbidden('Les bons de transformation ne peuvent pas être supprimés.');
    }

    public function cloture(BonTransformation $bonsTransformation): JsonResponse
    {
        if (!$bonsTransformation->statut->estActif()) {
            return $this->error('Ce BT ne peut pas être clôturé.', 422);
        }

        $bonsTransformation->update(['statut' => StatutRecyclage::CLOTURE->value]);

        return $this->success(
            new BonTransformationResource($bonsTransformation->fresh(['location', 'matiereBrute', 'machine'])),
            'Bon de transformation clôturé.'
        );
    }
}