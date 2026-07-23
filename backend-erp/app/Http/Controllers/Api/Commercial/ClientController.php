<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\Livraison;
use App\Models\VenteDirecte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('adresse', 'like', "%{$search}%");
            });
        }

        if ($request->has('actif')) {
            $query->where('actif', filter_var($request->query('actif'), FILTER_VALIDATE_BOOLEAN));
        }

        $clients = $query
            ->orderBy('nom')
            ->paginate((int) $request->query('per_page', 10));

        return $this->success(
            ClientResource::collection($clients)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:50', 'unique:clients,reference'],
            'NIF' => ['nullable', 'string', 'max:80'],
            'STAT' => ['nullable', 'string', 'max:80'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150'],
            'interlocutaire' => ['nullable', 'string', 'max:150'],
            'code_compta' => ['nullable', 'string', 'max:80'],
            'facturation' => ['nullable', 'string', 'max:150'],
            'est_divers' => ['nullable', 'boolean'],
            'actif' => ['nullable', 'boolean'],
        ]);

        $validated['reference'] = $validated['reference'] ?? Client::generateReference('CL');
        $validated['adresse'] = $validated['adresse'] ?? '';
        $validated['contact'] = $validated['contact'] ?? '';
        $validated['est_divers'] = (bool) ($validated['est_divers'] ?? false);
        $validated['actif'] = (bool) ($validated['actif'] ?? true);

        $client = Client::create($validated);

        return $this->created(new ClientResource($client));
    }

    public function show(Client $client): JsonResponse
    {
        return $this->success(new ClientResource($client));
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:150'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:clients,reference,' . $client->id],
            'NIF' => ['sometimes', 'nullable', 'string', 'max:80'],
            'STAT' => ['sometimes', 'nullable', 'string', 'max:80'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'interlocutaire' => ['sometimes', 'nullable', 'string', 'max:150'],
            'code_compta' => ['sometimes', 'nullable', 'string', 'max:80'],
            'facturation' => ['sometimes', 'nullable', 'string', 'max:150'],
            'est_divers' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $client->update($validated);

        return $this->success(new ClientResource($client->fresh()));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->update(['actif' => false]);

        return $this->success(new ClientResource($client->fresh()), 'Client archivé.');
    }

    public function encours(Client $client): JsonResponse
    {
        $commandes = Commande::query()
            ->where('client_id', $client->id)
            ->whereIn('statut', ['non_livree', 'partielle'])
            ->get();

        return $this->success([
            'nb_commandes' => $commandes->count(),
            'montant_total' => round((float) $commandes->sum('total'), 2),
        ]);
    }

    public function historique(Client $client, Request $request): JsonResponse
    {
        $annee = (int) $request->query('annee', now()->year);

        $commandes = Commande::query()
            ->with(['location'])
            ->where('client_id', $client->id)
            ->whereYear('date', $annee)
            ->latest('date')
            ->limit(20)
            ->get();

        $ventesDirectes = VenteDirecte::query()
            ->with(['location'])
            ->where('client_id', $client->id)
            ->whereYear('date', $annee)
            ->latest('date')
            ->limit(20)
            ->get();

        $livraisons = Livraison::query()
            ->with(['client'])
            ->where('client_id', $client->id)
            ->whereYear('date_livraison', $annee)
            ->latest('date_livraison')
            ->limit(20)
            ->get();

        $factures = Facture::query()
            ->where('client_id', $client->id)
            ->whereYear('date', $annee)
            ->latest('date')
            ->limit(20)
            ->get();

        $totalCommandes = round((float) $commandes->sum('total'), 2);
        $totalVentesDirectes = round((float) $ventesDirectes->sum('total'), 2);
        $totalFactures = round((float) $factures->sum('montant_total'), 2);
        $totalPaye = round((float) $factures->sum('montant_paye'), 2);

        return $this->success([
            'annee' => $annee,
            'ca_annuel' => round($totalCommandes + $totalVentesDirectes, 2),
            'total_commandes' => $totalCommandes,
            'total_ventes_directes' => $totalVentesDirectes,
            'total_facture' => $totalFactures,
            'total_paye' => $totalPaye,
            'reste_a_payer' => round(max(0, $totalFactures - $totalPaye), 2),
            'nb_commandes' => $commandes->count(),
            'nb_ventes_directes' => $ventesDirectes->count(),
            'nb_livraisons' => $livraisons->count(),
            'nb_factures' => $factures->count(),
            'commandes' => $commandes->map(fn (Commande $commande) => [
                'id' => $commande->id,
                'numero' => $commande->numero,
                'date' => optional($commande->date)->toDateString(),
                'statut' => $commande->statut,
                'total' => (float) $commande->total,
                'location' => $commande->location?->nom,
            ])->values(),
            'ventes_directes' => $ventesDirectes->map(fn (VenteDirecte $vente) => [
                'id' => $vente->id,
                'numero' => $vente->numero,
                'date' => optional($vente->date)->toDateString(),
                'statut' => $vente->statut,
                'total' => (float) $vente->total,
                'location' => $vente->location?->nom,
            ])->values(),
            'livraisons' => $livraisons->map(fn (Livraison $livraison) => [
                'id' => $livraison->id,
                'numero' => $livraison->numero,
                'date_livraison' => optional($livraison->date_livraison)->toDateString(),
                'statut' => $livraison->statut,
                'source_type' => $livraison->source_type,
                'source_id' => $livraison->source_id,
                'est_facturee' => (bool) $livraison->est_facturee,
            ])->values(),
            'factures' => $factures->map(fn (Facture $facture) => [
                'id' => $facture->id,
                'numero' => $facture->numero,
                'date_facture' => optional($facture->date_facture)->toDateString(),
                'statut' => $facture->statut,
                'montant_total' => (float) $facture->montant_total,
                'montant_paye' => (float) $facture->montant_paye,
                'reste_a_payer' => (float) max(0, $facture->montant_total - $facture->montant_paye),
            ])->values(),
        ]);
    }
}