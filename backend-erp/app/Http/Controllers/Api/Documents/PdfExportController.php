<?php

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Models\BonSortie;
use App\Models\Facture;
use App\Models\JournalAchat;
use App\Models\Livraison;
use App\Services\Pdf\DocumentPdfService;

class PdfExportController extends Controller
{
    public function __construct(
        private readonly DocumentPdfService $pdfExportService
    ) {
    }

    public function livraison(Livraison $livraison)
    {
        return $this->pdfExportService->downloadLivraison($livraison);
    }

    public function bonSortie(BonSortie $bon)
    {
        return $this->pdfExportService->downloadBonSortie($bon);
    }

    public function journalAchat(JournalAchat $br)
    {
        return $this->pdfExportService->downloadJournalAchat($br);
    }

    public function facture(Facture $facture)
    {
        return $this->pdfExportService->downloadFacture($facture);
    }
}