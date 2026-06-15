<?php
// app/Http/Controllers/Api/Recyclage/BonTransformationController.php

namespace App\Http\Controllers\Api\Recyclage;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\BonTransformationResource;
use App\Models\BonTransformation;
use App\Enums\StatutRecyclage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonTransformationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = BonTransformation::with(
            'location', 'matiereBrute', 'matiereBroyee', 'createur'
        );

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
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

        $bts = $query
            ->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            BonTransformationResource::collection($bts)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'              => ['required', 'date'],
            'location_id'       => ['required', 'exists:locations,id'],
            'matiere_brute_id'  => ['required', 'exists:matieres_premieres,id'],
            'matiere_broyee_id' => [
                'required',
                'exists:matieres_premieres,id',
                'different:matiere_brute_id',
            ],
            'machine_broyage'   => ['required', 'string', 'max:100'],
            'quantite_entree'   => ['required', 'numeric', 'min:0.001'],
        ]);

        $bt = BonTransformation::create([
            'numero'     => BonTransformation::generateReference('BT'),
            ...$validated,
            'statut'     => StatutRecyclage::OUVERT->value,
            'created_by' => auth()->id(),
        ]);

        return $this->created(
            new BonTransformationResource(
                $bt->load('location', 'matiereBrute', 'matiereBroyee')
            )
        );
    }

    public function show(BonTransformation $bonsTransformation): JsonResponse
    {
        $bonsTransformation->load(
            'location', 'matiereBrute', 'matiereBroyee', 'createur',
            'sessions.matieres.matiere',
            'sessions.employes.employe',
            'sessions.evenements'
        );

        return $this->success(new BonTransformationResource($bonsTransformation));
    }

    public function update(Request $request, BonTransformation $bonsTransformation): JsonResponse
    {
        if (!$bonsTransformation->statut->estActif()) {
            return $this->error('Ce bon de transformation ne peut plus être modifié.', 422);
        }

        $validated = $request->validate([
            'machine_broyage' => ['sometimes', 'string', 'max:100'],
            'quantite_entree' => ['sometimes', 'numeric', 'min:0.001'],
        ]);

        $bonsTransformation->update($validated);

        return $this->success(
            new BonTransformationResource($bonsTransformation->fresh()),
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
            new BonTransformationResource($bonsTransformation->fresh()),
            'Bon de transformation clôturé.'
        );
    }
}