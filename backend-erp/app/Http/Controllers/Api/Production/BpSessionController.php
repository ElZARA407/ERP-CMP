<?php
// app/Http/Controllers/Api/Production/BpSessionController.php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Production\StoreBpSessionRequest;
use App\Http\Resources\BpSessionResource;
use App\Models\BonProduction;
use App\Models\BpEvenement;
use App\Models\BpEmploye;
use App\Models\BpMp;
use App\Models\BpObtenue;
use App\Models\BpSession;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BpSessionController extends BaseApiController
{
    public function __construct(
        private readonly ProductionService $productionService
    ) {}

    public function index(BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('viewAny', BonProduction::class);

        $sessions = $bonsProduction->sessions()
            ->with('matieres.matiere', 'obtenus.classement', 'employes.employe')
            ->orderBy('session_numero')
            ->get();

        return $this->success(BpSessionResource::collection($sessions));
    }

    public function store(StoreBpSessionRequest $request, BonProduction $bonsProduction): JsonResponse
    {
        $this->authorize('saisirSession', $bonsProduction);

        if (!$bonsProduction->statut->estActif()) {
            return $this->error('Ce BP ne peut plus recevoir de sessions.', 422);
        }

        $session = BpSession::create([
            'bon_production_id' => $bonsProduction->id,
            'session_numero'    => $bonsProduction->prochainNumeroSession(),
            ...$request->validated(),
            'statut'            => 'ouverte',
            'cout_electricite'  => $request->cout_electricite ?? 0,
            'saisi_by'          => auth()->id(),
        ]);

        return $this->created(new BpSessionResource($session));
    }

    public function show(BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('view', $session->bonProduction);

        $session->load(
            'matieres.matiere',
            'obtenus.classement.produit',
            'employes.employe.poste',
            'evenements.operateur',
            'bonProduction'
        );

        return $this->success(new BpSessionResource($session));
    }

    public function update(Request $request, BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('saisirSession', $session->bonProduction);

        if ($session->statut === 'validee') {
            return $this->error('Une session validée ne peut pas être modifiée.', 422);
        }

        $validated = $request->validate([
            'machine_production' => ['sometimes', 'string', 'max:100'],
            'cout_electricite'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $session->update($validated);

        return $this->success(new BpSessionResource($session->fresh()), 'Session mise à jour.');
    }

    public function destroy(BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('saisirSession', $session->bonProduction);

        if ($session->statut === 'validee') {
            return $this->error('Une session validée ne peut pas être supprimée.', 422);
        }

        $session->delete();

        return $this->success(null, 'Session supprimée.');
    }

    public function valider(BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('validerSession', $session->bonProduction);

        try {
            $this->productionService->validerSession($session, auth()->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new BpSessionResource($session->fresh()->load('matieres', 'obtenus', 'employes')),
            'Session validée. Stocks mis à jour.'
        );
    }

    public function ajouterMatiere(Request $request, BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('saisirSession', $session->bonProduction);

        if ($session->statut === 'validee') {
            return $this->error('Session déjà validée.', 422);
        }

        $validated = $request->validate([
            'matiere_id'         => ['required', 'exists:matieres_premieres,id'],
            'quantite_utilisee'   => ['required', 'numeric', 'min:0.001'],
            'quantite_restituee'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $mp = BpMp::create([
            'bp_session_id'      => $session->id,
            'matiere_id'         => $validated['matiere_id'],
            'quantite_utilisee'   => $validated['quantite_utilisee'],
            'quantite_restituee'  => $validated['quantite_restituee'] ?? 0,
        ]);

        return $this->created($mp->load('matiere'));
    }

    public function ajouterObtenu(Request $request, BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('saisirSession', $session->bonProduction);

        if ($session->statut === 'validee') {
            return $this->error('Session déjà validée.', 422);
        }

        $validated = $request->validate([
            'classement_id'           => ['required', 'exists:classement_produits,id'],
            'quantite_produite'       => ['required', 'numeric', 'min:0.001'],
            'destination_location_id' => ['required', 'exists:locations,id'],
        ]);

        $obtenu = BpObtenue::create([
            'bp_session_id' => $session->id,
            ...$validated,
        ]);

        return $this->created($obtenu->load('classement.produit', 'destination'));
    }

    public function ajouterEmploye(Request $request, BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('saisirSession', $session->bonProduction);

        if ($session->statut === 'validee') {
            return $this->error('Session déjà validée.', 422);
        }

        $validated = $request->validate([
            'employe_id'    => ['required', 'exists:employes,id'],
            'heures_brutes' => ['required', 'numeric', 'min:0.1'],
        ]);

        $tauxHoraire = \App\Models\Employe::find($validated['employe_id'])?->tauxHoraireActuel() ?? 0;

        $bpEmploye = BpEmploye::create([
            'bp_session_id' => $session->id,
            'employe_id'    => $validated['employe_id'],
            'heures_brutes' => $validated['heures_brutes'],
            'taux_horaire'  => $tauxHoraire,
        ]);

        return $this->created($bpEmploye->load('employe.poste'));
    }

    public function ajouterEvenement(Request $request, BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('saisirSession', $session->bonProduction);

        if ($session->statut === 'validee') {
            return $this->error('Session déjà validée.', 422);
        }

        $validated = $request->validate([
            'type_evenement' => ['required', 'in:production,pause,panne,autre'],
            'heure_debut'    => ['required', 'date_format:H:i'],
            'heure_fin'      => ['nullable', 'date_format:H:i', 'after:heure_debut'],
            'description'    => ['nullable', 'string', 'max:500'],
        ]);

        $evenement = BpEvenement::create([
            'bp_session_id' => $session->id,
            ...$validated,
            'operateur_id'  => auth()->id(),
        ]);

        return $this->created($evenement);
    }
}