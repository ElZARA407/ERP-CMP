<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Commercial\StoreCommandeRequest;
use App\Http\Resources\CommandeResource;
use App\Models\Commande;
use App\Models\LigneCommande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with('client', 'location', 'createur');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->boolean('en_retard')) {
            $query->nonLivrees()
                ->where('date_livraison_prevue', '<', now());
        }

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }

        $commandes = $query
            ->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            CommandeResource::collection($commandes)->response()->getData(true)
        );
    }

    public function store(StoreCommandeRequest $request): JsonResponse
    {
        $commande = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $commande = Commande::create([
                'numero' => Commande::generateReference('CMD'),
                'client_id' => $data['client_id'],
                'date' => $data['date'],
                'date_livraison_prevue' => $data['date_livraison_prevue'] ?? null,
                'location_id' => $data['location_id'],
                'echeance' => $data['echeance'],
                'statut' => 'non_livree',
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['lignes'] as $ligne) {
                LigneCommande::create([
                    'commande_id' => $commande->id,
                    'produit_id' => $ligne['produit_id'],
                    'classement_id' => $ligne['classement_id'],
                    'quantite' => $ligne['quantite'],
                    'quantite_restante' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'etat' => 'disponible',
                ]);
            }

            return $commande->load('client', 'location', 'lignes.produit', 'lignes.classement');
        });

        return $this->created(new CommandeResource($commande));
    }

    public function show(Commande $commande): JsonResponse
    {
        $commande->load('client', 'location', 'lignes.produit', 'lignes.classement', 'createur');

        return $this->success(new CommandeResource($commande));
    }

    public function update(Request $request, Commande $commande): JsonResponse
    {
        $this->authorize('update', $commande);

        $commande->update($request->only([
            'date_livraison_prevue',
            'echeance',
            'location_id',
        ]));

        return $this->success(
            new CommandeResource($commande->fresh('client', 'location', 'lignes.produit', 'lignes.classement')),
            'Commande mise a jour.'
        );
    }

    public function destroy(Commande $commande): JsonResponse
    {
        $this->authorize('delete', $commande);

        return $this->forbidden('Les commandes ne peuvent pas etre supprimees.');
    }

    public function duplicate(Request $request, Commande $commande): JsonResponse
    {
        $nouvelle = DB::transaction(function () use ($commande, $request) {
            $commande->load('lignes');

            $nouvelle = Commande::create([
                'numero' => Commande::generateReference('CMD'),
                'client_id' => $commande->client_id,
                'date' => now(),
                'date_livraison_prevue' => null,
                'location_id' => $commande->location_id,
                'echeance' => $commande->echeance,
                'statut' => 'non_livree',
                'created_by' => $request->user()->id,
            ]);

            foreach ($commande->lignes as $ligne) {
                LigneCommande::create([
                    'commande_id' => $nouvelle->id,
                    'produit_id' => $ligne->produit_id,
                    'classement_id' => $ligne->classement_id,
                    'quantite' => $ligne->quantite,
                    'quantite_restante' => $ligne->quantite,
                    'prix_unitaire' => $ligne->prix_unitaire,
                    'etat' => 'disponible',
                ]);
            }

            return $nouvelle->load('client', 'location', 'lignes.produit', 'lignes.classement');
        });

        return $this->created(new CommandeResource($nouvelle));
    }
}