<?php
// app/Http/Controllers/Api/Achat/DemandeAchatController.php

namespace App\Http\Controllers\Api\Achat;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\DemandeAchatResource;
use App\Models\DemandeAchat;
use App\Models\LigneDemande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DemandeAchatController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = DemandeAchat::with('demandeur', 'lignes');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('demandeur_id')) {
            $query->where('demandeur_id', $request->demandeur_id);
        }

        if ($request->filled('date_debut')) {
            $query->where('date_demande', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date_demande', '<=', $request->date_fin);
        }

        $demandes = $query
            ->orderByDesc('date_demande')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            DemandeAchatResource::collection($demandes)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_demande'           => ['required', 'date'],
            'observations'           => ['nullable', 'string'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.entite_type'   => ['required', 'in:matiere,produit'],
            'lignes.*.entite_id'     => ['required', 'integer'],
            'lignes.*.quantite'      => ['required', 'numeric', 'min:0.001'],
            'lignes.*.observation_ligne' => ['nullable', 'string'],
        ]);

        $demande = DB::transaction(function () use ($validated) {
            $lignes = $validated['lignes'];
            unset($validated['lignes']);

            $demande = DemandeAchat::create([
                'numero'       => DemandeAchat::generateReference('DA'),
                ...$validated,
                'demandeur_id' => auth()->id(),
                'statut'       => 'brouillon',
            ]);

            foreach ($lignes as $ligne) {
                LigneDemande::create([
                    'demande_achat_id' => $demande->id,
                    ...$ligne,
                ]);
            }

            return $demande->load('demandeur', 'lignes');
        });

        return $this->created(new DemandeAchatResource($demande));
    }

    public function show(DemandeAchat $demande): JsonResponse
    {
        $demande->load('demandeur', 'lignes');

        return $this->success(new DemandeAchatResource($demande));
    }

    public function update(Request $request, DemandeAchat $demande): JsonResponse
    {
        if ($demande->statut !== 'brouillon') {
            return $this->error('Cette demande ne peut plus être modifiée.', 422);
        }

        $demande->update($request->only(['date_demande', 'observations']));

        return $this->success(
            new DemandeAchatResource($demande->fresh()),
            'Demande mise à jour.'
        );
    }

    public function destroy(DemandeAchat $demande): JsonResponse
    {
        if (!in_array($demande->statut, ['brouillon', 'rejetee'])) {
            return $this->error('Cette demande ne peut pas être supprimée.', 422);
        }

        $demande->delete();

        return $this->success(null, 'Demande supprimée.');
    }

    public function soumettre(DemandeAchat $demande): JsonResponse
    {
        if ($demande->statut !== 'brouillon') {
            return $this->error('Seule une demande en brouillon peut être soumise.', 422);
        }

        if ($demande->lignes()->count() === 0) {
            return $this->error('Impossible de soumettre une demande sans lignes.', 422);
        }

        $demande->update(['statut' => 'soumise']);

        return $this->success(
            new DemandeAchatResource($demande->fresh()),
            'Demande soumise pour approbation.'
        );
    }

    public function approuver(DemandeAchat $demande): JsonResponse
    {
        if (!$demande->estApprovable()) {
            return $this->error('Cette demande ne peut pas être approuvée.', 422);
        }

        $demande->approuver();

        return $this->success(
            new DemandeAchatResource($demande->fresh()),
            'Demande approuvée.'
        );
    }

    public function rejeter(Request $request, DemandeAchat $demande): JsonResponse
    {
        if ($demande->statut !== 'soumise') {
            return $this->error('Seule une demande soumise peut être rejetée.', 422);
        }

        $demande->rejeter();

        return $this->success(
            new DemandeAchatResource($demande->fresh()),
            'Demande rejetée.'
        );
    }
}