<?php
// app/Http/Controllers/Api/Logistique/LivraisonController.php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\LivraisonResource;
use App\Models\LigneLivraison;
use App\Models\Livraison;
use App\Services\LivraisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivraisonController extends BaseApiController
{
    public function __construct(
        private readonly LivraisonService $livraisonService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Livraison::with('client', 'createur');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        if ($request->has('est_facturee')) {
            if ($request->boolean('est_facturee')) {
                $query->whereHas('facture');
            } else {
                $query->whereDoesntHave('facture');
            }
        }

        $livraisons = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            LivraisonResource::collection($livraisons)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type'            => ['required', 'in:commande,vente_directe'],
            'source_id'              => ['required', 'integer'],
            'client_id'              => ['required', 'exists:clients,id'],
            'reference_bc'           => ['nullable', 'string', 'max:30'],
            'chauffeur'              => ['nullable', 'string', 'max:100'],
            'vehicule'               => ['nullable', 'string', 'max:30'],
            'observations'           => ['nullable', 'string'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.ligne_commande_id'      => ['nullable', 'exists:lignes_commande,id'],
            'lignes.*.ligne_vente_directe_id' => ['nullable', 'exists:lignes_vente_directe,id'],
            'lignes.*.classement_id'          => ['required', 'exists:classement_produits,id'],
            'lignes.*.quantite_livree'         => ['required', 'numeric', 'min:0.001'],
        ]);

        $livraison = DB::transaction(function () use ($validated) {
            $livraison = Livraison::create([
                'numero'       => Livraison::generateReference('BL'),
                'source_type'  => $validated['source_type'],
                'source_id'    => $validated['source_id'],
                'client_id'    => $validated['client_id'],
                'reference_bc' => $validated['reference_bc'] ?? null,
                'chauffeur'    => $validated['chauffeur'] ?? null,
                'vehicule'     => $validated['vehicule'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'statut'       => 'prepare',
                'created_by'   => auth()->id(),
            ]);

            foreach ($validated['lignes'] as $ligne) {
                LigneLivraison::create([
                    'livraison_id'             => $livraison->id,
                    'ligne_commande_id'        => $ligne['ligne_commande_id'] ?? null,
                    'ligne_vente_directe_id'   => $ligne['ligne_vente_directe_id'] ?? null,
                    'classement_id'            => $ligne['classement_id'],
                    'quantite_livree'          => $ligne['quantite_livree'],
                ]);
            }

            return $livraison->load('client', 'lignes.classement.produit');
        });

        return $this->created(new LivraisonResource($livraison));
    }

    public function show(Livraison $livraison): JsonResponse
    {
        $livraison->load('client', 'lignes.classement.produit', 'createur', 'facture');

        return $this->success(new LivraisonResource($livraison));
    }

    public function update(Request $request, Livraison $livraison): JsonResponse
    {
        if ($livraison->statut === 'livre') {
            return $this->error('Une livraison confirmée ne peut pas être modifiée.', 422);
        }

        $livraison->update($request->only([
            'chauffeur', 'vehicule', 'observations', 'date_livraison',
        ]));

        return $this->success(new LivraisonResource($livraison->fresh()), 'Livraison mise à jour.');
    }

    public function destroy(Livraison $livraison): JsonResponse
    {
        return $this->forbidden('Les livraisons ne peuvent pas être supprimées.');
    }

    // ── POST /livraisons/{id}/confirmer ───────────────────
    public function confirmer(Livraison $livraison): JsonResponse
    {
        try {
            $this->livraisonService->confirmerLivraison($livraison, auth()->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new LivraisonResource($livraison->fresh()->load('client', 'lignes')),
            'Livraison confirmée. Stocks décrementés.'
        );
    }
}