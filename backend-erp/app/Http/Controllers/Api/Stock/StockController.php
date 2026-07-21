<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\MouvementStockResource;
use App\Http\Resources\StockResource;
use App\Imports\StockImport;
use App\Models\ClassementProduit;
use App\Models\MatierePremiere;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Utilisateur;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StockController extends BaseApiController
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Stock::with('location', 'classement', 'entite')
            ->where('stock_total', '>', 0);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('entite_type')) {
            $query->where('entite_type', $request->entite_type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $like = '%' . $search . '%';

            $produitIds = Produit::query()
                ->where(function ($q) use ($like) {
                    $q->where('designation', 'like', $like)
                        ->orWhere('nomencla', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $matiereIds = MatierePremiere::query()
                ->where(function ($q) use ($like) {
                    $q->where('nom', 'like', $like)
                        ->orWhere('reference', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $classementIds = ClassementProduit::query()
                ->where(function ($q) use ($like) {
                    $q->where('libelle', 'like', $like)
                        ->orWhere('qualite', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $query->where(function ($searchQuery) use ($like, $produitIds, $matiereIds, $classementIds) {
                $searchQuery->whereHas('location', function ($locationQuery) use ($like) {
                    $locationQuery->where('nom', 'like', $like);
                });

                if (!empty($classementIds)) {
                    $searchQuery->orWhereIn('classement_id', $classementIds);
                }

                if (!empty($produitIds)) {
                    $searchQuery->orWhere(function ($produitQuery) use ($produitIds) {
                        $produitQuery->where('entite_type', 'produit')
                            ->whereIn('entite_id', $produitIds);
                    });
                }

                if (!empty($matiereIds)) {
                    $searchQuery->orWhere(function ($matiereQuery) use ($matiereIds) {
                        $matiereQuery->where('entite_type', 'matiere')
                            ->whereIn('entite_id', $matiereIds);
                    });
                }
            });
        }

        $stocks = $query
            ->orderByDesc('updated_at')
            ->paginate((int) $request->get('per_page', 10))
            ->appends($request->query());

        return $this->success(
            StockResource::collection($stocks)->response()->getData(true)
        );
    }

    public function alertes(Request $request): JsonResponse
    {
        $query = Stock::with('location', 'classement', 'entite')
            ->where('stock_total', '>', 0);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('entite_type')) {
            $query->where('entite_type', $request->entite_type);
        }

        $stocks = $query->get()->filter(function (Stock $stock) {
            $seuil = $stock->entite?->seuil;

            return $seuil !== null
                && (float) $stock->stock_total > 0
                && (float) $stock->stock_total <= (float) $seuil;
        })->values();

        return $this->success(StockResource::collection($stocks));
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'entite_type' => ['required', 'in:matiere,produit'],
            'entite_id' => ['required', 'integer', 'min:1'],
            'classement_id' => ['nullable', 'integer', 'exists:classement_produits,id'],
            'stock_total' => ['required', 'numeric', 'min:0'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['entite_type'] === 'produit' && empty($validated['classement_id'])) {
            return $this->error('Le classement est requis pour un stock de produit.', 422);
        }

        if ($validated['entite_type'] === 'matiere' && !empty($validated['classement_id'])) {
            return $this->error('Le classement doit rester vide pour une matière première.', 422);
        }

        if ($validated['entite_type'] === 'produit' && !Produit::whereKey($validated['entite_id'])->exists()) {
            return $this->error('Produit introuvable.', 422);
        }

        if ($validated['entite_type'] === 'matiere' && !MatierePremiere::whereKey($validated['entite_id'])->exists()) {
            return $this->error('Matière première introuvable.', 422);
        }

        try {
            /** @var Utilisateur $operateur */
            $operateur = $request->user();

            $mouvement = $this->stockService->ajusterInventaire(
                locationId: (int) $validated['location_id'],
                entiteType: $validated['entite_type'],
                entiteId: (int) $validated['entite_id'],
                stockPhysique: (float) $validated['stock_total'],
                motif: trim((string) ($validated['motif'] ?? '')) ?: 'inventaire',
                operateur: $operateur,
                classementId: !empty($validated['classement_id']) ? (int) $validated['classement_id'] : null
            );

            return $this->created(
                new MouvementStockResource($mouvement->load('location', 'utilisateur', 'classement', 'entite')),
                'Stock initial déclaré avec mouvement inventaire.'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);

            return $this->error('Création du stock initial impossible.', 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'sheet_names' => ['sometimes', 'array'],
            'sheet_names.*' => ['string', 'max:150'],
        ]);

        $file = $validated['file'];
        $requestedSheets = collect($validated['sheet_names'] ?? [])
            ->map(static fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        try {
            $extension = strtolower((string) $file->getClientOriginalExtension());

            if (in_array($extension, ['xls', 'xlsx'], true)) {
                $workbookSheets = IOFactory::load($file->getRealPath())->getSheetNames();

                if ($requestedSheets !== []) {
                    $missingSheets = array_values(array_diff($requestedSheets, $workbookSheets));

                    if ($missingSheets !== []) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Une ou plusieurs feuilles demandées sont introuvables dans le fichier.',
                            'data' => null,
                            'errors' => [
                                'missing_sheets' => $missingSheets,
                            ],
                            'status' => 422,
                        ], 422);
                    }
                }
            } elseif ($requestedSheets !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'La liste des feuilles nommées est réservée aux fichiers Excel (.xls, .xlsx).',
                    'data' => null,
                    'errors' => null,
                    'status' => 422,
                ], 422);
            }

            /** @var Utilisateur $operateur */
            $operateur = $request->user();
            $import = new StockImport($requestedSheets);
            $importedCount = 0;
            $unchangedCount = 0;

            DB::transaction(function () use ($file, $import, $operateur, &$importedCount, &$unchangedCount) {
                Excel::import($import, $file);

                foreach ($import->rows() as $row) {
                    try {
                        $this->stockService->ajusterInventaire(
                            locationId: (int) $row['location_id'],
                            entiteType: (string) $row['entite_type'],
                            entiteId: (int) $row['entite_id'],
                            stockPhysique: (float) $row['stock_total'],
                            motif: 'inventaire',
                            operateur: $operateur,
                            classementId: $row['classement_id'] !== null ? (int) $row['classement_id'] : null
                        );

                        $importedCount++;
                    } catch (\DomainException $e) {
                        if (str_contains(strtolower($e->getMessage()), 'aucun ecart')) {
                            $unchangedCount++;
                            continue;
                        }

                        throw $e;
                    }
                }
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier stock contient des lignes invalides.',
                'data' => null,
                'errors' => null,
                'status' => 422,
            ], 422);
        } catch (\RuntimeException|\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => null,
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Import stock impossible.',
                'data' => null,
                'errors' => null,
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock importé avec succès. Mouvements inventaire générés.',
            'data' => [
                'imported_count' => $importedCount,
                'unchanged_count' => $unchangedCount,
            ],
            'errors' => null,
            'status' => 200,
        ], 200);
    }

    public function ajusterInventaire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'entite_type' => ['required', 'in:matiere,produit'],
            'entite_id' => ['required', 'integer', 'min:1'],
            'classement_id' => ['nullable', 'integer', 'exists:classement_produits,id'],
            'stock_physique' => ['required', 'numeric', 'min:0'],
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($validated['entite_type'] === 'produit' && empty($validated['classement_id'])) {
            return $this->error('Le classement est requis pour un ajustement de produit.', 422);
        }

        if ($validated['entite_type'] === 'produit' && !Produit::whereKey($validated['entite_id'])->exists()) {
            return $this->error('Produit introuvable.', 422);
        }

        if ($validated['entite_type'] === 'matiere' && !MatierePremiere::whereKey($validated['entite_id'])->exists()) {
            return $this->error('Matière première introuvable.', 422);
        }

        try {
            /** @var Utilisateur $operateur */
            $operateur = $request->user();

            $mouvement = $this->stockService->ajusterInventaire(
                locationId: (int) $validated['location_id'],
                entiteType: $validated['entite_type'],
                entiteId: (int) $validated['entite_id'],
                stockPhysique: (float) $validated['stock_physique'],
                motif: $validated['motif'],
                operateur: $operateur,
                classementId: $validated['classement_id'] ? (int) $validated['classement_id'] : null
            );

            return $this->success(
                new MouvementStockResource($mouvement->load('location', 'utilisateur', 'classement', 'entite')),
                'Ajustement inventaire enregistré.'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);
            return $this->error('Ajustement inventaire impossible.', 500);
        }
    }

    public function ruptures(Request $request): JsonResponse
    {
        $query = Stock::with('location', 'classement', 'entite')
            ->where('stock_total', '<=', 0);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('entite_type')) {
            $query->where('entite_type', $request->entite_type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $like = '%' . $search . '%';

            $produitIds = Produit::query()
                ->where(function ($q) use ($like) {
                    $q->where('designation', 'like', $like)
                        ->orWhere('nomencla', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $matiereIds = MatierePremiere::query()
                ->where(function ($q) use ($like) {
                    $q->where('nom', 'like', $like)
                        ->orWhere('reference', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $classementIds = ClassementProduit::query()
                ->where(function ($q) use ($like) {
                    $q->where('libelle', 'like', $like)
                        ->orWhere('qualite', 'like', $like);
                })
                ->pluck('id')
                ->all();

            $query->where(function ($searchQuery) use ($like, $produitIds, $matiereIds, $classementIds) {
                $searchQuery->whereHas('location', function ($locationQuery) use ($like) {
                    $locationQuery->where('nom', 'like', $like);
                });

                if (!empty($classementIds)) {
                    $searchQuery->orWhereIn('classement_id', $classementIds);
                }

                if (!empty($produitIds)) {
                    $searchQuery->orWhere(function ($produitQuery) use ($produitIds) {
                        $produitQuery->where('entite_type', 'produit')
                            ->whereIn('entite_id', $produitIds);
                    });
                }

                if (!empty($matiereIds)) {
                    $searchQuery->orWhere(function ($matiereQuery) use ($matiereIds) {
                        $matiereQuery->where('entite_type', 'matiere')
                            ->whereIn('entite_id', $matiereIds);
                    });
                }
            });
        }

        $stocks = $query
            ->orderByDesc('updated_at')
            ->paginate((int) $request->get('per_page', 10))
            ->appends($request->query());

        return $this->success(
            StockResource::collection($stocks)->response()->getData(true)
        );
    }

    public function parLocation(int $id): JsonResponse
    {
        $stocks = Stock::with('location', 'classement', 'entite')
            ->where('location_id', $id)
            ->where('stock_total', '>', 0)
            ->get();

        return $this->success(StockResource::collection($stocks));
    }

    public function parProduit(int $id): JsonResponse
    {
        $stocks = Stock::with('location', 'classement', 'entite')
            ->where('entite_type', 'produit')
            ->where('entite_id', $id)
            ->get();

        return $this->success(StockResource::collection($stocks));
    }

    public function parMatiere(int $id): JsonResponse
    {
        $stocks = Stock::with('location', 'classement', 'entite')
            ->where('entite_type', 'matiere')
            ->where('entite_id', $id)
            ->get();

        return $this->success(StockResource::collection($stocks));
    }
}