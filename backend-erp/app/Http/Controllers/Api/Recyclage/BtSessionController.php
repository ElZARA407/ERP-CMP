<?php

namespace App\Http\Controllers\Api\Recyclage;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\BtSessionResource;
use App\Models\BonTransformation;
use App\Models\BtEmploye;
use App\Models\BtEvenement;
use App\Models\BtMp;
use App\Models\BtSession;
use App\Models\Employe;
use App\Models\MatierePremiere;
use App\Services\RecyclageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BtSessionController extends BaseApiController
{
    public function __construct(
        private readonly RecyclageService $recyclageService
    ) {}

    public function index(BonTransformation $bonsTransformation): JsonResponse
    {
        $sessions = $bonsTransformation->sessions()
            ->with(
                'machine',
                'matieres.matiere',
                'employes.employe.poste',
                'evenements.operateur',
                'calcul'
            )
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
            'date_session' => ['required', 'date'],
            'machine_id' => ['required', 'exists:machines,id'],

            'sorties' => ['required', 'array', 'min:1'],
            'sorties.*.quantite_utilisee' => ['required', 'numeric', 'min:0.001'],
            'sorties.*.quantite_restituee' => ['nullable', 'numeric', 'min:0'],

            'entrees' => ['required', 'array', 'min:1'],
            'entrees.*.matiere_id' => ['required', 'exists:matieres_premieres,id'],
            'entrees.*.quantite' => ['required', 'numeric', 'min:0.001'],

            'employes' => ['nullable', 'array'],
            'employes.*.employe_id' => ['required', 'exists:employes,id'],
            'employes.*.heures_brutes' => ['nullable', 'numeric', 'min:0'],

            'evenements' => ['nullable', 'array'],
            'evenements.*.type_evenement' => ['required', Rule::in(['broyage', 'pause', 'panne', 'autre'])],
            'evenements.*.heure_debut' => ['required', 'date_format:H:i'],
            'evenements.*.heure_fin' => ['nullable', 'date_format:H:i'],
            'evenements.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        $bonsTransformation->loadMissing('matiereBrute');

        if ($bonsTransformation->matiereBrute?->type !== 'brute') {
            return $this->error('La matière du BT doit être une matière brute.', 422);
        }

        foreach ($validated['sorties'] as $sortie) {
            if ((float) ($sortie['quantite_restituee'] ?? 0) > (float) $sortie['quantite_utilisee']) {
                return $this->error('La quantité restituée ne peut pas dépasser la quantité utilisée.', 422);
            }
        }

        foreach ($validated['entrees'] as $entree) {
            $matiere = MatierePremiere::find($entree['matiere_id']);

            if (!$matiere || $matiere->type !== 'broyee') {
                return $this->error('Les matières obtenues doivent être de type broyée.', 422);
            }
        }

        $session = DB::transaction(function () use ($bonsTransformation, $validated) {
            $session = BtSession::create([
                'bon_transformation_id' => $bonsTransformation->id,
                'session_numero' => $bonsTransformation->prochainNumeroSession(),
                'date_session' => $validated['date_session'],
                'machine_id' => $validated['machine_id'],
                'machine_broyage' => null,
                'ecarts' => 0,
                'statut' => 'ouverte',
                'saisi_by' => auth()->id(),
            ]);

            foreach ($validated['sorties'] as $row) {
                BtMp::create([
                    'bt_session_id' => $session->id,
                    'matiere_id' => $bonsTransformation->matiere_brute_id,
                    'type' => 'sortie',
                    'quantite' => $row['quantite_utilisee'],
                    'quantite_restituee' => $row['quantite_restituee'] ?? 0,
                ]);
            }

            foreach ($validated['entrees'] as $row) {
                BtMp::create([
                    'bt_session_id' => $session->id,
                    'matiere_id' => $row['matiere_id'],
                    'type' => 'entree',
                    'quantite' => $row['quantite'],
                    'quantite_restituee' => 0,
                ]);
            }

            foreach ($validated['employes'] ?? [] as $row) {
                $employe = Employe::with('poste')->find($row['employe_id']);
                $tauxHoraire = $employe?->tauxHoraireActuel() ?? 0;
                $heuresBrutes = (float) ($row['heures_brutes'] ?? 0);

                BtEmploye::create([
                    'bt_session_id' => $session->id,
                    'employe_id' => $row['employe_id'],
                    'heures_brutes' => $heuresBrutes,
                    'heures_effectives' => $heuresBrutes,
                    'taux_horaire' => $tauxHoraire,
                    'cout' => round($heuresBrutes * (float) $tauxHoraire, 2),
                ]);
            }

            foreach ($validated['evenements'] ?? [] as $row) {
                BtEvenement::create([
                    'bt_session_id' => $session->id,
                    'type_evenement' => $row['type_evenement'],
                    'heure_debut' => $row['heure_debut'],
                    'heure_fin' => $row['heure_fin'] ?? null,
                    'description' => $row['description'] ?? null,
                    'operateur_id' => auth()->id(),
                ]);
            }

            return $session;
        });

        return $this->created(
            new BtSessionResource(
                $session->load(
                    'machine',
                    'matieres.matiere',
                    'employes.employe.poste',
                    'evenements.operateur',
                    'calcul'
                )
            )
        );
    }

    public function show(BtSession $session): JsonResponse
    {
        $session->load(
            'machine',
            'matieres.matiere',
            'employes.employe.poste',
            'evenements.operateur',
            'bonTransformation.matiereBrute',
            'calcul'
        );

        return $this->success(new BtSessionResource($session));
    }

    public function update(Request $request, BtSession $session): JsonResponse
    {
        if ((string) $session->statut === 'validee') {
            return $this->error('Une session validée ne peut pas être modifiée.', 422);
        }

        $validated = $request->validate([
            'machine_id' => ['sometimes', 'exists:machines,id'],
        ]);

        $session->update($validated);

        return $this->success(
            new BtSessionResource($session->fresh(['machine'])),
            'Session mise à jour.'
        );
    }

    public function destroy(BtSession $session): JsonResponse
    {
        if ((string) $session->statut === 'validee') {
            return $this->error('Une session validée ne peut pas être supprimée.', 422);
        }

        $session->delete();

        return $this->success(null, 'Session supprimée.');
    }

    public function valider(BtSession $session): JsonResponse
    {
        try {
            $this->recyclageService->validerSession($session, auth()->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new BtSessionResource(
                $session->fresh()->load(
                    'machine',
                    'matieres.matiere',
                    'employes.employe.poste',
                    'evenements.operateur',
                    'calcul'
                )
            ),
            'Session validée. Stocks mis à jour.'
        );
    }
}