<?php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\BonSortieResource;
use App\Models\BonSortie;
use App\Models\LigneSortie;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BonSortieController extends BaseApiController
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = BonSortie::with(
            'location',
            'destinationLocation',
            'client',
            'createur',
            'valideur',
            'lignes.produit',
            'lignes.classement'
        );

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('motif', 'like', "%{$search}%")
                    ->orWhere('motif_detail', 'like', "%{$search}%")
                    ->orWhere('observations', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) =>
                        $client->where('nom', 'like', "%{$search}%")
                            ->orWhere('reference', 'like', "%{$search}%")
                    )
                    ->orWhereHas('location', fn ($location) =>
                        $location->where('nom', 'like', "%{$search}%")
                    )
                    ->orWhereHas('destinationLocation', fn ($location) =>
                        $location->where('nom', 'like', "%{$search}%")
                    )
                    ->orWhereHas('createur', fn ($user) =>
                        $user->where('nom', 'like', "%{$search}%")
                    )
                    ->orWhereHas('lignes.produit', fn ($produit) =>
                        $produit->where('designation', 'like', "%{$search}%")
                            ->orWhere('nomencla', 'like', "%{$search}%")
                    );
            });
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('destination_location_id')) {
            $query->where('destination_location_id', $request->destination_location_id);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('motif')) {
            $query->where('motif', $request->motif);
        }

        if ($request->filled('produit_id')) {
            $query->whereHas('lignes', fn ($ligne) =>
                $ligne->where('produit_id', $request->produit_id)
            );
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date', '<=', $request->date_fin);
        }

        $bons = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', config('api.per_page', 10)));

        return $this->success(
            BonSortieResource::collection($bons)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $bon = DB::transaction(function () use ($validated) {
            $lignes = $validated['lignes'];
            unset($validated['lignes']);

            $validated = $this->normalizeContextFields($validated);

            $bon = BonSortie::create([
                'numero' => BonSortie::generateReference('BS'),
                ...$validated,
                'statut' => 'brouillon',
                'created_by' => auth()->id(),
            ]);

            foreach ($lignes as $ligne) {
                LigneSortie::create([
                    'bon_sortie_id' => $bon->id,
                    'produit_id' => $ligne['produit_id'],
                    'classement_id' => $ligne['classement_id'],
                    'quantite' => $ligne['quantite'],
                ]);
            }

            return $bon->load(
                'location',
                'destinationLocation',
                'client',
                'createur',
                'valideur',
                'lignes.produit',
                'lignes.classement'
            );
        });

        return $this->created(new BonSortieResource($bon));
    }

    public function show(BonSortie $bonsSortie): JsonResponse
    {
        $bonsSortie->load(
            'location',
            'destinationLocation',
            'client',
            'createur',
            'valideur',
            'lignes.produit',
            'lignes.classement'
        );

        return $this->success(new BonSortieResource($bonsSortie));
    }

    public function update(Request $request, BonSortie $bonsSortie): JsonResponse
    {
        if ($bonsSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus etre modifie.', 422);
        }

        $validated = $this->validatePayload($request, true);

        DB::transaction(function () use ($bonsSortie, $validated) {
            $lignes = $validated['lignes'] ?? null;
            unset($validated['lignes']);

            $validated = $this->normalizeContextFields($validated);

            $bonsSortie->update($validated);

            if (is_array($lignes)) {
                $bonsSortie->lignes()->delete();

                foreach ($lignes as $ligne) {
                    LigneSortie::create([
                        'bon_sortie_id' => $bonsSortie->id,
                        'produit_id' => $ligne['produit_id'],
                        'classement_id' => $ligne['classement_id'],
                        'quantite' => $ligne['quantite'],
                    ]);
                }
            }
        });

        return $this->success(
            new BonSortieResource($bonsSortie->fresh(
                'location',
                'destinationLocation',
                'client',
                'createur',
                'valideur',
                'lignes.produit',
                'lignes.classement'
            )),
            'Bon de sortie mis a jour.'
        );
    }

    public function destroy(BonSortie $bonsSortie): JsonResponse
    {
        if ($bonsSortie->statut !== 'brouillon') {
            return $this->error('Seul un BS en brouillon peut etre supprime.', 422);
        }

        $bonsSortie->delete();

        return $this->success(null, 'Bon de sortie supprime.');
    }

    public function valider(BonSortie $bonsSortie): JsonResponse
    {
        if (!$bonsSortie->estValidable()) {
            return $this->error('Ce bon de sortie ne peut pas etre valide.', 422);
        }

        DB::transaction(function () use ($bonsSortie) {
            $bonsSortie->loadMissing('lignes.produit', 'lignes.classement');

            foreach ($bonsSortie->lignes as $ligne) {
                if (!$ligne->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de sortie.');
                }

                $this->stockService->sortie(
                    locationId: $bonsSortie->location_id,
                    entiteType: 'produit',
                    entiteId: $ligne->produit_id,
                    quantite: (float) $ligne->quantite,
                    referenceType: 'bon_sortie',
                    referenceId: $bonsSortie->id,
                    operateur: auth()->user(),
                    classementId: $ligne->classement_id,
                    motif: $this->buildMouvementMotif($bonsSortie)
                );
            }

            $bonsSortie->update([
                'statut' => 'valide',
                'valide_by' => auth()->id(),
            ]);
        });

        return $this->success(
            new BonSortieResource($bonsSortie->fresh(
                'location',
                'destinationLocation',
                'client',
                'createur',
                'valideur',
                'lignes.produit',
                'lignes.classement'
            )),
            'Bon de sortie valide. Stocks decrementes.'
        );
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'location_id' => [$required, 'exists:locations,id'],
            'date' => [$required, 'date'],
            'motif' => [
                $required,
                Rule::in(BonSortie::MOTIFS),
            ],
            'destination_location_id' => [
                'nullable',
                'exists:locations,id',
                Rule::requiredIf(fn () => $request->input('motif') === BonSortie::MOTIF_TRANSFERT),
            ],
            'client_id' => [
                'nullable',
                'exists:clients,id',
                Rule::requiredIf(fn () => $request->input('motif') === BonSortie::MOTIF_ECHANTILLON),
            ],
            'motif_detail' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => in_array($request->input('motif'), [
                    BonSortie::MOTIF_PERTE,
                    BonSortie::MOTIF_CASSE,
                    BonSortie::MOTIF_DESTRUCTION,
                    BonSortie::MOTIF_AUTRE,
                ], true)),
            ],
            'observations' => ['nullable', 'string'],
            'lignes' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required_with:lignes', 'exists:produits,id'],
            'lignes.*.classement_id' => ['required_with:lignes', 'exists:classement_produits,id'],
            'lignes.*.quantite' => ['required_with:lignes', 'numeric', 'min:0.001'],
        ]);
    }

    private function normalizeContextFields(array $validated): array
    {
        $motif = $validated['motif'] ?? null;

        if ($motif !== BonSortie::MOTIF_TRANSFERT) {
            $validated['destination_location_id'] = null;
        }

        if ($motif !== BonSortie::MOTIF_ECHANTILLON) {
            $validated['client_id'] = null;
        }

        return $validated;
    }

    private function buildMouvementMotif(BonSortie $bon): string
    {
        $label = method_exists($bon, 'motifLibelle') ? $bon->motifLibelle() : $bon->motif;
        $detail = trim((string) $bon->motif_detail);

        return $detail !== ''
            ? "Bon de sortie - {$label} : {$detail}"
            : "Bon de sortie - {$label}";
    }
}