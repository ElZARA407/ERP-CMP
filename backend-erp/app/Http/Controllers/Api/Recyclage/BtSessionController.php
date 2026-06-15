<?php
// app/Http/Controllers/Api/Recyclage/BtSessionController.php

namespace App\Http\Controllers\Api\Recyclage;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\BtSessionResource;
use App\Models\BonTransformation;
use App\Models\BtSession;
use App\Models\BtMp;
use App\Models\BtEmploye;
use App\Models\BtEvenement;
use App\Services\RecyclageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BtSessionController extends BaseApiController
{
    public function __construct(
        private readonly RecyclageService $recyclageService
    ) {}

    public function index(BonTransformation $bonsTransformation): JsonResponse
    {
        $sessions = $bonsTransformation->sessions()
            ->with('matieres.matiere', 'employes.employe')
            ->orderBy('session_numero')
            ->get();

        return $this->success(BtSessionResource::collection($sessions));
    }

    public function store(Request $request, BonTransformation $bonsTransformation): JsonResponse
    {
        if (!$bonsTransformation->statut->estActif()) {
            return $this->error('Ce BT ne peut plus recevoir de sessions.', 422);
        }

        $validated = $request->validate([
            'date_session'    => ['required', 'date'],
            'machine_broyage' => ['required', 'string', 'max:100'],
        ]);

        $dernierNumero = $bonsTransformation->sessions()->max('session_numero') ?? 0;

        $session = BtSession::create([
            'bon_transformation_id' => $bonsTransformation->id,
            'session_numero'        => $dernierNumero + 1,
            ...$validated,
            'statut'                => 'ouverte',
            'ecarts'                => 0,
            'saisi_by'              => auth()->id(),
        ]);

        return $this->created(new BtSessionResource($session));
    }

    public function show(BtSession $btSession): JsonResponse
    {
        $btSession->load(
            'matieres.matiere',
            'employes.employe.poste',
            'evenements.operateur',
            'bonTransformation'
        );

        return $this->success(new BtSessionResource($btSession));
    }

    public function update(Request $request, BtSession $btSession): JsonResponse
    {
        if ($btSession->statut === 'validee') {
            return $this->error('Une session validée ne peut pas être modifiée.', 422);
        }

        $btSession->update($request->only(['machine_broyage']));

        return $this->success(new BtSessionResource($btSession->fresh()), 'Session mise à jour.');
    }

    public function destroy(BtSession $btSession): JsonResponse
    {
        if ($btSession->statut === 'validee') {
            return $this->error('Une session validée ne peut pas être supprimée.', 422);
        }

        $btSession->delete();

        return $this->success(null, 'Session supprimée.');
    }

    public function valider(BtSession $btSession): JsonResponse
    {
        try {
            $this->recyclageService->validerSession($btSession, auth()->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new BtSessionResource($btSession->fresh()->load('matieres', 'employes')),
            'Session validée. Stocks mis à jour.'
        );
    }

    public function ajouterMatiere(Request $request, BtSession $btSession): JsonResponse
    {
        if ($btSession->statut === 'validee') {
            return $this->error('Session déjà validée.', 422);
        }

        $validated = $request->validate([
            'matiere_id'         => ['required', 'exists:matieres_premieres,id'],
            'type'               => ['required', 'in:entree,sortie'],
            'quantite'           => ['required', 'numeric', 'min:0.001'],
            'quantite_restituee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $mp = BtMp::create([
            'bt_session_id' => $btSession->id,
            ...$validated,
            'quantite_restituee' => $validated['quantite_restituee'] ?? 0,
        ]);

        return $this->created($mp->load('matiere'));
    }

    public function ajouterEmploye(Request $request, BtSession $btSession): JsonResponse
    {
        if ($btSession->statut === 'validee') {
            return $this->error('Session déjà validée.', 422);
        }

        $validated = $request->validate([
            'employe_id'    => ['required', 'exists:employes,id'],
            'heures_brutes' => ['required', 'numeric', 'min:0.1'],
        ]);

        $tauxHoraire = \App\Models\Employe::find($validated['employe_id'])
            ->tauxHoraireActuel();

        $btEmploye = BtEmploye::create([
            'bt_session_id' => $btSession->id,
            'employe_id'    => $validated['employe_id'],
            'heures_brutes' => $validated['heures_brutes'],
            'taux_horaire'  => $tauxHoraire,
        ]);

        return $this->created($btEmploye->load('employe.poste'));
    }

    public function ajouterEvenement(Request $request, BtSession $btSession): JsonResponse
    {
        $validated = $request->validate([
            'type_evenement' => ['required', 'in:broyage,pause,panne,autre'],
            'heure_debut'    => ['required', 'date_format:H:i'],
            'heure_fin'      => ['nullable', 'date_format:H:i', 'after:heure_debut'],
            'description'    => ['nullable', 'string', 'max:500'],
        ]);

        $evenement = BtEvenement::create([
            'bt_session_id' => $btSession->id,
            ...$validated,
            'operateur_id'  => auth()->id(),
        ]);

        return $this->created($evenement);
    }
}