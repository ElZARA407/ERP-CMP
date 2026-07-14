<?php
// app/Http/Controllers/Api/Production/BpSessionController.php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Production\StoreBpSessionRequest;
use App\Http\Resources\BpSessionResource;
use App\Models\BonProduction;
use App\Models\BpEmploye;
use App\Models\BpEvenement;
use App\Models\BpMp;
use App\Models\BpObtenue;
use App\Models\BpSession;
use App\Models\Employe;
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
            ->with(
                'machine',
                'matieres.matiere',
                'obtenus.produit',
                'obtenus.classement',
                'obtenus.destination',
                'employes.employe',
                'evenements.operateur',
                'calcul'
            )
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

        $validated = $request->validated();

        $session = DB::transaction(function () use ($bonsProduction, $validated) {
            $session = BpSession::create([
                'bon_production_id' => $bonsProduction->id,
                'session_numero' => $bonsProduction->prochainNumeroSession(),
                'date_session' => $validated['date_session'],
                'machine_id' => $validated['machine_id'],
                'cout_electricite' => $validated['cout_electricite'] ?? 0,
                'cout_total' => 0,
                'statut' => 'ouverte',
                'saisi_by' => auth()->id(),
            ]);

            foreach ($validated['matieres'] ?? [] as $row) {
                BpMp::create([
                    'bp_session_id' => $session->id,
                    'matiere_id' => $row['matiere_id'],
                    'quantite_utilisee' => $row['quantite_utilisee'],
                    'quantite_restituee' => $row['quantite_restituee'] ?? 0,
                    'cout_matiere' => 0,
                ]);
            }

            foreach ($validated['obtenus'] ?? [] as $row) {
                BpObtenue::create([
                    'bp_session_id' => $session->id,
                    'produit_id' => $row['produit_id'],
                    'classement_id' => $row['classement_id'],
                    'quantite_produite' => $row['quantite_produite'],
                    'destination_location_id' => $row['destination_location_id'],
                ]);
            }

            foreach ($validated['employes'] ?? [] as $row) {
                $tauxHoraire = Employe::with('poste')->find($row['employe_id'])?->tauxHoraireActuel() ?? 0;
                $heuresBrutes = (float) ($row['heures_brutes'] ?? 0);

                BpEmploye::create([
                    'bp_session_id' => $session->id,
                    'employe_id' => $row['employe_id'],
                    'heures_brutes' => $heuresBrutes,
                    'heures_effectives' => $heuresBrutes,
                    'taux_horaire' => $tauxHoraire,
                    'cout' => round($heuresBrutes * (float) $tauxHoraire, 2),
                ]);
            }

            foreach ($validated['evenements'] ?? [] as $row) {
                BpEvenement::create([
                    'bp_session_id' => $session->id,
                    'type_evenement' => $row['type_evenement'],
                    'heure_debut' => $row['heure_debut'],
                    'heure_fin' => $row['heure_fin'] ?? null,
                    'description' => $row['description'] ?? null,
                    'operateur_id' => auth()->id(),
                ]);
            }

            return $session;
        });

        $session->load(
            'machine',
            'matieres.matiere',
            'obtenus.produit',
            'obtenus.classement',
            'obtenus.destination',
            'employes.employe.poste',
            'evenements.operateur',
            'calcul'
        );

        return $this->created(
            new BpSessionResource($session)
        );
    }

    public function show(BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('view', $session->bonProduction);

        $session->load(
            'machine',
            'matieres.matiere',
            'obtenus.produit',
            'obtenus.classement',
            'obtenus.destination',
            'employes.employe.poste',
            'evenements.operateur',
            'bonProduction.machine'
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
            'machine_id' => ['sometimes', 'exists:machines,id'],
            'cout_electricite' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $session->update($validated);

        return $this->success(
            new BpSessionResource($session->fresh()->load('machine')),
            'Session mise à jour.'
        );
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
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            new BpSessionResource(
                $session->fresh()->load(
                    'machine',
                    'matieres.matiere',
                    'obtenus.produit',
                    'obtenus.classement',
                    'obtenus.destination',
                    'employes.employe.poste',
                    'calcul'
                )
            ),
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
            'matiere_id' => ['required', 'exists:matieres_premieres,id'],
            'quantite_utilisee' => ['required', 'numeric', 'min:0.001'],
            'quantite_restituee' => ['nullable', 'numeric', 'min:0', 'lte:quantite_utilisee'],
        ]);

        $mp = BpMp::create([
            'bp_session_id' => $session->id,
            'matiere_id' => $validated['matiere_id'],
            'quantite_utilisee' => $validated['quantite_utilisee'],
            'quantite_restituee' => $validated['quantite_restituee'] ?? 0,
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
            'produit_id' => ['required', 'exists:produits,id'],
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite_produite' => ['required', 'numeric', 'min:0.001'],
            'destination_location_id' => ['required', 'exists:locations,id'],
        ]);

        $obtenu = BpObtenue::create([
            'bp_session_id' => $session->id,
            ...$validated,
        ]);

        return $this->created($obtenu->load('produit', 'classement', 'destination'));
    }

    public function ajouterEmploye(Request $request, BpSession $session): JsonResponse
    {
        $session->loadMissing('bonProduction');
        $this->authorize('saisirSession', $session->bonProduction);

        if ($session->statut === 'validee') {
            return $this->error('Session déjà validée.', 422);
        }

        $validated = $request->validate([
            'employe_id' => ['required', 'exists:employes,id'],
            'heures_brutes' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tauxHoraire = Employe::with('poste')->find($validated['employe_id'])?->tauxHoraireActuel() ?? 0;
        $heuresBrutes = (float) ($validated['heures_brutes'] ?? 0);

        $bpEmploye = BpEmploye::create([
            'bp_session_id' => $session->id,
            'employe_id' => $validated['employe_id'],
            'heures_brutes' => $heuresBrutes,
            'heures_effectives' => $heuresBrutes,
            'taux_horaire' => $tauxHoraire,
            'cout' => round($heuresBrutes * (float) $tauxHoraire, 2),
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
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $evenement = BpEvenement::create([
            'bp_session_id' => $session->id,
            ...$validated,
            'operateur_id' => auth()->id(),
        ]);

        return $this->created($evenement->load('operateur'));
    }
}
