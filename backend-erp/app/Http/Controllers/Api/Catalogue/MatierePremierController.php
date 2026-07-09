<?php

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\MatierePremiereResource;
use App\Imports\MatierePremiereImport;
use App\Models\MatierePremiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use App\Imports\MatierePremiereSheetImport;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MatierePremierController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = MatierePremiere::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                    ->orWhere('reference', 'like', "%{$request->search}%");
            });
        }

        $matieres = $query
            ->orderBy('nom')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            MatierePremiereResource::collection($matieres)->response()->getData(true)
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
                    Excel::import(new MatierePremiereImport($requestedSheets), $file);
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
                    Excel::import(new MatierePremiereSheetImport(), $file);
                });
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier matière première contient des lignes invalides.',
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
                'message' => 'Import matière première impossible.',
                'data' => null,
                'errors' => null,
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Matières premières importées avec succès.',
            'data' => null,
            'errors' => null,
            'status' => 200,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:30', 'unique:matieres_premieres,reference'],
            'nom' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:preformes,broyee,brute,vierge,colorant,autre'],
            'description' => ['nullable', 'string'],
            'unite' => ['required', 'string', 'max:10'],
            'prix_moyen' => ['nullable', 'numeric', 'min:0'],
            'seuil' => ['nullable', 'numeric', 'min:0'],
            'actif' => ['boolean'],
        ]);

        $matiere = MatierePremiere::create($validated);

        return $this->created(new MatierePremiereResource($matiere));
    }

    public function show(MatierePremiere $matieresPremiere): JsonResponse
    {
        return $this->success(new MatierePremiereResource($matieresPremiere));
    }

    public function update(Request $request, MatierePremiere $matieresPremiere): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'unite' => ['sometimes', 'string', 'max:10'],
            'seuil' => ['sometimes', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $matieresPremiere->update($validated);

        return $this->success(
            new MatierePremiereResource($matieresPremiere->fresh()),
            'Matière première mise à jour.'
        );
    }

    public function destroy(MatierePremiere $matieresPremiere): JsonResponse
    {
        $matieresPremiere->delete();

        return $this->success(null, 'Matière première archivée.');
    }
}