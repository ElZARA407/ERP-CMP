<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ContratResource;
use App\Models\Contrat;
use App\Models\LigneContrat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContratController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Contrat::with('client', 'lignes.produit', 'lignes.classement');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('mois', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) =>
                        $client->where('nom', 'like', "%{$search}%")
                            ->orWhere('reference', 'like', "%{$search}%")
                    )
                    ->orWhereHas('lignes.produit', fn ($produit) =>
                        $produit->where('designation', 'like', "%{$search}%")
                            ->orWhere('nomencla', 'like', "%{$search}%")
                    );
            });
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('mois')) {
            $query->where('mois', $request->mois);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('frequence')) {
            $query->whereHas('lignes', fn ($ligne) =>
                $ligne->where('frequence', $request->frequence)
            );
        }

        if ($request->filled('date_debut')) {
            $query->whereHas('lignes', fn ($ligne) =>
                $ligne->whereDate('date_debut', '>=', $request->date_debut)
            );
        }

        if ($request->filled('date_fin')) {
            $query->whereHas('lignes', fn ($ligne) =>
                $ligne->where(function ($q) use ($request) {
                    $q->whereNull('date_fin')
                        ->orWhereDate('date_fin', '<=', $request->date_fin);
                })
            );
        }

        $contrats = $query
            ->orderByDesc('mois')
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', config('api.per_page', 10)));

        return $this->success(
            ContratResource::collection($contrats)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $contrat = DB::transaction(function () use ($validated) {
            $lignes = $validated['lignes'];
            unset($validated['lignes']);

            $contrat = Contrat::create([
                'numero' => Contrat::generateReference('CTR'),
                'client_id' => $validated['client_id'],
                'mois' => $validated['mois'],
                'actif' => true,
            ]);

            foreach ($lignes as $ligne) {
                LigneContrat::create([
                    'contrat_id' => $contrat->id,
                    'produit_id' => $ligne['produit_id'],
                    'classement_id' => $ligne['classement_id'],
                    'quantite_contractuelle' => $ligne['quantite_contractuelle'],
                    'quantite_livree_ytd' => 0,
                    'frequence' => $ligne['frequence'],
                    'frequence_jours' => $ligne['frequence_jours'] ?? null,
                    'date_debut' => $ligne['date_debut'] ?? null,
                    'date_fin' => $ligne['date_fin'] ?? null,
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'statut' => 'disponible',
                ]);
            }

            return $contrat->load('client', 'lignes.produit', 'lignes.classement');
        });

        return $this->created(new ContratResource($contrat));
    }

    public function show(Contrat $contrat): JsonResponse
    {
        $contrat->load('client', 'lignes.produit', 'lignes.classement');

        return $this->success(new ContratResource($contrat));
    }

    public function update(Request $request, Contrat $contrat): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        DB::transaction(function () use ($contrat, $validated) {
            if (array_key_exists('client_id', $validated)) {
                $contrat->client_id = $validated['client_id'];
            }

            if (array_key_exists('mois', $validated)) {
                $contrat->mois = $validated['mois'];
            }

            if (array_key_exists('actif', $validated)) {
                $contrat->actif = $validated['actif'];
            }

            $contrat->save();

            if (isset($validated['lignes']) && is_array($validated['lignes'])) {
                $contrat->lignes()->delete();

                foreach ($validated['lignes'] as $ligne) {
                    LigneContrat::create([
                        'contrat_id' => $contrat->id,
                        'produit_id' => $ligne['produit_id'],
                        'classement_id' => $ligne['classement_id'],
                        'quantite_contractuelle' => $ligne['quantite_contractuelle'],
                        'quantite_livree_ytd' => 0,
                        'frequence' => $ligne['frequence'],
                        'frequence_jours' => $ligne['frequence_jours'] ?? null,
                        'date_debut' => $ligne['date_debut'] ?? null,
                        'date_fin' => $ligne['date_fin'] ?? null,
                        'prix_unitaire' => $ligne['prix_unitaire'],
                        'statut' => 'disponible',
                    ]);
                }
            }
        });

        return $this->success(
            new ContratResource($contrat->fresh('client', 'lignes.produit', 'lignes.classement')),
            'Contrat mis a jour.'
        );
    }

    public function destroy(Contrat $contrat): JsonResponse
    {
        $contrat->update(['actif' => false]);

        return $this->success(null, 'Contrat desactive.');
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'client_id' => [$required, 'exists:clients,id'],
            'mois' => [$required, 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'actif' => ['sometimes', 'boolean'],

            'lignes' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required_with:lignes', 'exists:produits,id'],
            'lignes.*.classement_id' => ['required_with:lignes', 'exists:classement_produits,id'],
            'lignes.*.quantite_contractuelle' => ['required_with:lignes', 'numeric', 'min:0.001'],
            'lignes.*.frequence' => ['required_with:lignes', Rule::in(LigneContrat::FREQUENCES)],
            'lignes.*.frequence_jours' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn () => collect($request->input('lignes', []))->contains(
                    fn ($ligne) => ($ligne['frequence'] ?? null) === 'tous_x_jours'
                )),
            ],
            'lignes.*.date_debut' => ['nullable', 'date'],
            'lignes.*.date_fin' => ['nullable', 'date', 'after_or_equal:lignes.*.date_debut'],
            'lignes.*.prix_unitaire' => ['required_with:lignes', 'numeric', 'min:0'],
        ]);
    }
}