<?php

namespace App\Http\Controllers\Api\Reports;

use App\Exports\ReportsOverviewExport;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends BaseApiController
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'mouvement_entite_type' => ['nullable', 'in:produit,matiere'],
            'mouvement_entite_id' => ['nullable', 'integer', 'min:1'],
            'mouvement_motif' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->success(
            $this->reportService->overview(
                $validated['date_debut'] ?? null,
                $validated['date_fin'] ?? null,
                [
                    'entite_type' => $validated['mouvement_entite_type'] ?? null,
                    'entite_id' => $validated['mouvement_entite_id'] ?? null,
                    'motif' => $validated['mouvement_motif'] ?? null,
                ]
            )
        );
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'section' => ['required', 'in:commercial,stock,production,recyclage,finance,mouvements'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'mouvement_entite_type' => ['nullable', 'in:produit,matiere'],
            'mouvement_entite_id' => ['nullable', 'integer', 'min:1'],
            'mouvement_motif' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = $this->reportService->overview(
            $validated['date_debut'] ?? null,
            $validated['date_fin'] ?? null,
            [
                'entite_type' => $validated['mouvement_entite_type'] ?? null,
                'entite_id' => $validated['mouvement_entite_id'] ?? null,
                'motif' => $validated['mouvement_motif'] ?? null,
            ]
        );

        $section = $validated['section'];
        $fileName = 'rapport-' . $section . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(
            new ReportsOverviewExport($payload, $section),
            $fileName
        );
    }
}