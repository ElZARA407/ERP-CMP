<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FournisseurResource;
use App\Models\Fournisseur;
use App\Models\JournalAchat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FournisseurController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Fournisseur::query();

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

        $fournisseurs = $query
            ->orderBy('nom')
            ->paginate((int) $request->query('per_page', 10));

        return $this->success(
            FournisseurResource::collection($fournisseurs)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:50', 'unique:fournisseurs,reference'],
            'NIF' => ['nullable', 'string', 'max:80'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150'],
            'interlocutaire' => ['nullable', 'string', 'max:150'],
            'code_compta' => ['nullable', 'string', 'max:80'],
            'facturation' => ['nullable', 'string', 'max:150'],
            'est_divers' => ['nullable', 'boolean'],
            'actif' => ['nullable', 'boolean'],
        ]);

        $validated['reference'] = $validated['reference'] ?? Fournisseur::generateReference('FO');
        $validated['adresse'] = $validated['adresse'] ?? '';
        $validated['contact'] = $validated['contact'] ?? '';
        $validated['est_divers'] = (bool) ($validated['est_divers'] ?? false);
        $validated['actif'] = (bool) ($validated['actif'] ?? true);

        $fournisseur = Fournisseur::create($validated);

        return $this->created(new FournisseurResource($fournisseur));
    }

    public function show(Fournisseur $fournisseur): JsonResponse
    {
        return $this->success(new FournisseurResource($fournisseur));
    }

    public function update(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:150'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:fournisseurs,reference,' . $fournisseur->id],
            'NIF' => ['sometimes', 'nullable', 'string', 'max:80'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'interlocutaire' => ['sometimes', 'nullable', 'string', 'max:150'],
            'code_compta' => ['sometimes', 'nullable', 'string', 'max:80'],
            'facturation' => ['sometimes', 'nullable', 'string', 'max:150'],
            'est_divers' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $fournisseur->update($validated);

        return $this->success(new FournisseurResource($fournisseur->fresh()));
    }

    public function destroy(Fournisseur $fournisseur): JsonResponse
    {
        $fournisseur->update(['actif' => false]);

        return $this->success(new FournisseurResource($fournisseur->fresh()), 'Fournisseur archivé.');
    }

    public function historique(Fournisseur $fournisseur, Request $request): JsonResponse
    {
        $annee = (int) $request->query('annee', now()->year);

        $achats = JournalAchat::query()
            ->with(['location', 'lignes.matiere'])
            ->where('fournisseur_id', $fournisseur->id)
            ->whereYear('date', $annee)
            ->latest('date')
            ->limit(30)
            ->get();

        $totalAchats = round((float) $achats->sum('total'), 2);
        $totalValide = round((float) $achats->where('statut', 'valide')->sum('total'), 2);
        $totalBrouillon = round((float) $achats->where('statut', 'brouillon')->sum('total'), 2);

        return $this->success([
            'annee' => $annee,
            'total_achats' => $totalAchats,
            'total_valide' => $totalValide,
            'total_brouillon' => $totalBrouillon,
            'nb_achats' => $achats->count(),
            'nb_valides' => $achats->where('statut', 'valide')->count(),
            'nb_brouillons' => $achats->where('statut', 'brouillon')->count(),
            'achats' => $achats->map(fn (JournalAchat $achat) => [
                'id' => $achat->id,
                'numero' => $achat->numero,
                'date' => optional($achat->date)->toDateString(),
                'vehicule' => $achat->vehicule,
                'statut' => $achat->statut,
                'total' => (float) $achat->total,
                'location' => $achat->location?->nom,
                'lignes_count' => $achat->lignes->count(),
                'lignes' => $achat->lignes->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'matiere' => $ligne->matiere ? [
                        'id' => $ligne->matiere->id,
                        'reference' => $ligne->matiere->reference,
                        'nom' => $ligne->matiere->nom,
                    ] : null,
                    'quantite' => (float) $ligne->quantite,
                    'prix_unitaire' => (float) $ligne->prix_unitaire,
                    'total_ligne' => (float) $ligne->total_ligne,
                ])->values(),
            ])->values(),
        ]);
    }
}