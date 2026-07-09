<?php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\LivraisonResource;
use App\Models\Livraison;
use App\Models\Utilisateur;
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
            $request->boolean('est_facturee')
                ? $query->whereHas('facture')
                : $query->whereDoesntHave('facture');
        }

        $livraisons = $query
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            LivraisonResource::collection($livraisons)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => ['required', 'in:commande,vente_directe'],
            'source_id' => ['required', 'integer'],
            'client_id' => ['required', 'exists:clients,id'],
            'reference_bc' => ['nullable', 'string', 'max:30'],
            'chauffeur' => ['nullable', 'string', 'max:100'],
            'vehicule' => ['nullable', 'string', 'max:30'],
            'observations' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.ligne_commande_id' => ['nullable', 'exists:ligne_commandes,id'],
            'lignes.*.ligne_vente_directe_id' => ['nullable', 'exists:lignes_vente_directe,id'],
            'lignes.*.produit_id' => ['required', 'exists:produits,id'],
            'lignes.*.classement_id' => ['required', 'exists:classement_produits,id'],
            'lignes.*.quantite_livree' => ['required', 'numeric', 'min:0.001'],
        ]);

        $livraison = DB::transaction(function () use ($validated) {
            $livraison = Livraison::create([
                'numero' => Livraison::generateReference('BL'),
                'source_type' => $validated['source_type'],
                'source_id' => $validated['source_id'],
                'client_id' => $validated['client_id'],
                'reference_bc' => $validated['reference_bc'] ?? null,
                'chauffeur' => $validated['chauffeur'] ?? null,
                'vehicule' => $validated['vehicule'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'statut' => 'prepare',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['lignes'] as $ligne) {
                $livraison->lignes()->create([
                    'ligne_commande_id' => $ligne['ligne_commande_id'] ?? null,
                    'ligne_vente_directe_id' => $ligne['ligne_vente_directe_id'] ?? null,
                    'produit_id' => $ligne['produit_id'],
                    'classement_id' => $ligne['classement_id'],
                    'quantite_livree' => $ligne['quantite_livree'],
                ]);
            }

            return $livraison->load('client', 'lignes.produit', 'lignes.classement');
        });

        return $this->created(new LivraisonResource($livraison));
    }

    public function show(Livraison $livraison): JsonResponse
    {
        $livraison->load('client', 'lignes.produit', 'lignes.classement', 'createur', 'facture');

        return $this->success(new LivraisonResource($livraison));
    }

    public function update(Request $request, Livraison $livraison): JsonResponse
    {
        if ($livraison->statut === 'livre') {
            return $this->error('Une livraison confirmee ne peut pas etre modifiee.', 422);
        }

        $livraison->update($request->only([
            'chauffeur',
            'vehicule',
            'observations',
            'date_livraison',
        ]));

        return $this->success(
            new LivraisonResource($livraison->fresh('client', 'lignes.produit', 'lignes.classement')),
            'Livraison mise a jour.'
        );
    }

    public function destroy(Livraison $livraison): JsonResponse
    {
        return $this->forbidden('Les livraisons ne peuvent pas etre supprimees.');
    }

    public function confirmer(Request $request, Livraison $livraison): JsonResponse
    {
        $operateur = $request->user();

        if (! $operateur instanceof Utilisateur) {
            return $this->error('Utilisateur authentifie invalide.', 422);
        }

        try {
            $this->livraisonService->confirmerLivraison($livraison, $operateur);
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            new LivraisonResource($livraison->fresh()->load('client', 'lignes.produit', 'lignes.classement', 'facture')),
            'Livraison confirmee. Stocks decrementes.'
        );
    }

    public function annuler(Request $request, Livraison $livraison): JsonResponse
    {
        $operateur = $request->user();

        if (! $operateur instanceof Utilisateur) {
            return $this->error('Utilisateur authentifie invalide.', 422);
        }

        try {
            $this->livraisonService->annulerLivraison($livraison, $operateur);
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            new LivraisonResource($livraison->fresh()->load('client', 'lignes.produit', 'lignes.classement', 'facture')),
            'Livraison annulee. Stocks recredites.'
        );
    }
}