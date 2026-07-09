<?php

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ProduitResource;
use App\Imports\ProduitImport;
use App\Models\CategorieProduit;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Imports\ProduitSheetImport;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProduitController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Produit::with('categorie', 'stocks.classement');

        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('designation', 'like', "%{$request->search}%")
                    ->orWhere('nomencla', 'like', "%{$request->search}%");
            });
        }

        $produits = $query
            ->orderBy('designation')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            ProduitResource::collection($produits)->response()->getData(true)
        );
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

                DB::transaction(function () use ($file, $requestedSheets) {
                    Excel::import(new ProduitImport($requestedSheets), $file);
                });
            } else {
                if ($requestedSheets !== []) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La liste des feuilles nommées est réservée aux fichiers Excel (.xls, .xlsx).',
                        'data' => null,
                        'errors' => null,
                        'status' => 422,
                    ], 422);
                }

                DB::transaction(function () use ($file) {
                    Excel::import(new ProduitSheetImport(), $file);
                });
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier produit contient des lignes invalides.',
                'data' => null,
                'errors' => null,
                'status' => 422,
            ], 422);
        } catch (\RuntimeException $e) {
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
                'message' => 'Import produit impossible.',
                'data' => null,
                'errors' => null,
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produits importés avec succès.',
            'data' => null,
            'errors' => null,
            'status' => 200,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => ['required', 'string', 'max:150'],
            'categorie_id' => ['required', 'exists:categorie_produits,id'],
            'contenance' => ['nullable', 'string', 'max:20'],
            'format' => ['nullable', 'string', 'max:20'],
            'unite' => ['required', 'string', 'max:10'],
            'colisage' => ['required', 'numeric', 'min:1'],
            'poids' => ['required', 'string', 'max:10'],
            'seuil' => ['required', 'numeric', 'min:0'],
            'actif' => ['boolean'],
        ]);

        $produit = DB::transaction(function () use ($validated) {
            $categorie = CategorieProduit::query()
                ->whereKey($validated['categorie_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $nomencla = $this->generateNomencla($categorie);

            return Produit::create([
                'nomencla' => $nomencla,
                'designation' => $validated['designation'],
                'categorie_id' => $validated['categorie_id'],
                'contenance' => $validated['contenance'] ?? null,
                'format' => $validated['format'] ?? null,
                'unite' => $validated['unite'],
                'colisage' => $validated['colisage'],
                'poids' => $validated['poids'],
                'seuil' => (float) $validated['seuil'],
                'actif' => $validated['actif'] ?? true,
            ])->load('categorie');
        });

        return $this->created(
            new ProduitResource($produit),
            'Produit créé.'
        );
    }

    public function show(Produit $produit): JsonResponse
    {
        $produit->load('categorie', 'stocks.classement');

        return $this->success(new ProduitResource($produit));
    }

    public function update(Request $request, Produit $produit): JsonResponse
    {
        $validated = $request->validate([
            'designation' => ['sometimes', 'string', 'max:150'],
            'contenance' => ['nullable', 'string', 'max:20'],
            'format' => ['nullable', 'string', 'max:20'],
            'colisage' => ['sometimes', 'numeric', 'min:1'],
            'poids' => ['sometimes', 'string', 'max:10'],
            'seuil' => ['sometimes', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $produit->update($validated);

        return $this->success(
            new ProduitResource($produit->fresh('categorie', 'stocks.classement')),
            'Produit mis à jour.'
        );
    }

    public function destroy(Produit $produit): JsonResponse
    {
        $produit->delete();

        return $this->success(null, 'Produit archivé.');
    }

    private function generateNomencla(CategorieProduit $categorie): string
    {
        $prefix = 'PF-' . $categorie->nom . '-';

        $existing = Produit::query()
            ->where('categorie_id', $categorie->id)
            ->where('nomencla', 'like', $prefix . '%')
            ->pluck('nomencla');

        $maxSequence = 0;

        foreach ($existing as $nomencla) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $nomencla, $matches)) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }

        return $prefix . str_pad((string) ($maxSequence + 1), 3, '0', STR_PAD_LEFT);
    }
}