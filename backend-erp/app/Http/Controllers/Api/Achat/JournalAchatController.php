<?php
// app/Http/Controllers/Api/Achat/JournalAchatController.php

namespace App\Http\Controllers\Api\Achat;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Achat\StoreJournalAchatRequest;
use App\Http\Resources\JournalAchatResource;
use App\Models\JournalAchat;
use App\Models\LigneAchat;
use App\Services\AchatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalAchatController extends BaseApiController
{
    public function __construct(
        private readonly AchatService $achatService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = JournalAchat::with('fournisseur', 'location', 'createur');

        if ($request->filled('fournisseur_id')) {
            $query->where('fournisseur_id', $request->fournisseur_id);
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

        $brs = $query->orderByDesc('date')
                     ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            JournalAchatResource::collection($brs)->response()->getData(true)
        );
    }

    public function store(StoreJournalAchatRequest $request): JsonResponse
    {
        $br = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $br = JournalAchat::create([
                'numero'         => JournalAchat::generateReference('BR'),
                'fournisseur_id' => $data['fournisseur_id'],
                'date'           => $data['date'],
                'location_id'    => $data['location_id'],
                'vehicule'       => $data['vehicule'] ?? null,
                'observations'   => $data['observations'] ?? null,
                'statut'         => 'brouillon',
                'created_by'     => auth()->id(),
            ]);

            foreach ($data['lignes'] as $ligne) {
                $totalLigne = round(
                    $ligne['quantite'] * $ligne['prix_unitaire'],
                    2
                );
                LigneAchat::create([
                    'journal_achat_id' => $br->id,
                    'matiere_id'       => $ligne['matiere_id'],
                    'quantite'         => $ligne['quantite'],
                    'prix_unitaire'    => $ligne['prix_unitaire'],
                    'total_ligne'      => $totalLigne,
                ]);
            }

            $this->achatService->calculerTotal($br->fresh('lignes'));

            return $br->load('fournisseur', 'location', 'lignes.matiere');
        });

        return $this->created(new JournalAchatResource($br));
    }

    public function show(JournalAchat $bonsReception): JsonResponse
    {
        $bonsReception->load('fournisseur', 'location', 'lignes.matiere', 'createur');

        return $this->success(new JournalAchatResource($bonsReception));
    }

    public function update(Request $request, JournalAchat $bonsReception): JsonResponse
    {
        if ($bonsReception->statut === 'valide') {
            return $this->error('Un BR validé ne peut pas être modifié.', 422);
        }

        $bonsReception->update($request->only([
            'vehicule', 'observations',
        ]));

        return $this->success(
            new JournalAchatResource($bonsReception->fresh()),
            'BR mis à jour.'
        );
    }

    public function destroy(JournalAchat $bonsReception): JsonResponse
    {
        if ($bonsReception->statut === 'valide') {
            return $this->error('Un BR validé ne peut pas être supprimé.', 422);
        }

        $bonsReception->delete();

        return $this->success(null, 'BR supprimé.');
    }

    // ── POST /bons-reception/{br}/valider ─────────────────
    public function valider(JournalAchat $bonsReception): JsonResponse
    {
        try {
            $this->achatService->valider($bonsReception, auth()->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new JournalAchatResource($bonsReception->fresh()),
            'Bon de réception validé. Stocks mis à jour.'
        );
    }
}