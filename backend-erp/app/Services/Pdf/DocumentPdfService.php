<?php

namespace App\Services\Pdf;

use App\Models\BonSortie;
use App\Models\Facture;
use App\Models\JournalAchat;
use App\Models\Livraison;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class DocumentPdfService
{
    public function downloadLivraison(Livraison $livraison)
    {
        $livraison->loadMissing(
            'client',
            'createur',
            'lignes.produit',
            'lignes.classement',
            'facture',
            'factures'
        );
        $clientEstDivers = (bool) ($livraison->client?->est_divers ?? false);
        $clientNom = $clientEstDivers ? 'CLIENT DIVERS' : ($livraison->client?->nom ?? '—');
        $clientAdresse = $clientEstDivers ? '' : ($livraison->client?->adresse ?? '');
        $data = [
            'company' => $this->companyBlock(),
            'document' => [
                'title' => 'BON DE LIVRAISON',
                'reference_label' => 'REFERENCE',
                'reference_number' => $livraison->numero,
                'meta_rows' => [
                    ['label' => 'Date', 'value' => $this->formatDate($livraison->date_livraison)],
                    ['label' => 'Client', 'value' => $clientNom],
                    ['label' => 'Adresse', 'value' => $clientAdresse],
                    ['label' => 'Ref BC', 'value' => $livraison->reference_bc ?? '—'],
                    ['label' => 'Ref facture', 'value' => $livraison->reference_facture ?? '—'],
                ],
                'line_headers' => ['N°', 'Reference', 'Designation', 'Colisage', 'Quantite(s)', 'V'],
                'lines' => $this->mapLivraisonLines($livraison),
                'footer_note' => "1 exemplaire blanc pour le client\n1 exemplaire bleu pour la comptabilite\n1 exemplaire vert pour le carnet BL usine",
                'signature_labels' => ['CMP', 'TRANSPORTEUR', 'CLIENT'],
                'copies' => 2,
            ],
        ];

        return $this->renderPdf('pdf.documents.stock-document', $data, $livraison->numero . '.pdf');
    }

    public function downloadBonSortie(BonSortie $bonSortie)
    {
        $bonSortie->loadMissing(
            'location',
            'client',
            'createur',
            'lignes.produit',
            'lignes.classement'
        );

        $data = [
            'company' => $this->companyBlock(),
            'document' => [
                'title' => 'BON DE SORTIE',
                'reference_label' => 'REFERENCE',
                'reference_number' => $bonSortie->numero,
                'meta_rows' => [
                    ['label' => 'Date', 'value' => $this->formatDate($bonSortie->date)],
                    ['label' => 'Location', 'value' => $bonSortie->location?->nom ?? '—'],
                    ['label' => 'Client', 'value' => $bonSortie->client?->nom ?? '—'],
                    ['label' => 'Motif', 'value' => $this->labelForMotif($bonSortie->motif)],
                ],
                'line_headers' => ['N°', 'Reference', 'Designation', 'Colisage', 'Quantite(s)', 'V'],
                'lines' => $this->mapBonSortieLines($bonSortie),
                'footer_note' => "Document interne de sortie stock",
                'signature_labels' => ['CMP', 'TRANSPORTEUR', 'CLIENT'],
                'copies' => 2,
            ],
        ];

        return $this->renderPdf('pdf.documents.stock-document', $data, $bonSortie->numero . '.pdf');
    }

    public function downloadJournalAchat(JournalAchat $journalAchat)
    {
        $journalAchat->loadMissing(
            'fournisseur',
            'location',
            'createur',
            'lignes.matiere'
        );
        $fournisseurEstDivers = (bool) ($journalAchat->fournisseur?->est_divers ?? false);
        $fournisseurNom = $fournisseurEstDivers ? 'FOURNISSEUR DIVERS' : ($journalAchat->fournisseur?->nom ?? '—');
        $fournisseurAdresse = $fournisseurEstDivers ? '' : ($journalAchat->fournisseur?->adresse ?? '');
        $data = [
            'company' => $this->companyBlock(),
            'document' => [
                'title' => 'BON DE RECEPTION',
                'reference_label' => 'REFERENCE',
                'reference_number' => $journalAchat->numero,
                'meta_rows' => [
                    ['label' => 'Date', 'value' => $this->formatDate($journalAchat->date)],
                    ['label' => 'Fournisseur', 'value' => $fournisseurNom],
                    ['label' => 'Adresse', 'value' => $fournisseurAdresse],
                    ['label' => 'Location', 'value' => $journalAchat->location?->nom ?? '—'],
                    ['label' => 'Vehicule', 'value' => $journalAchat->vehicule ?? '—'],
                ],
                'line_headers' => ['N°', 'Reference', 'Designation', 'Unite', 'Quantite', 'V'],
                'lines' => $this->mapJournalAchatLines($journalAchat),
                'footer_note' => "1 exemplaire blanc pour le client\n1 exemplaire bleu pour la comptabilite\n1 exemplaire vert pour le carnet BL usine",
                'signature_labels' => ['CMP', 'TRANSPORTEUR', 'CLIENT'],
                'copies' => 2,
            ],
        ];

        return $this->renderPdf('pdf.documents.stock-document', $data, $journalAchat->numero . '.pdf');
    }

    public function downloadFacture(Facture $facture)
    {
        $facture->loadMissing(
            'client',
            'livraison',
            'livraisons',
            'lignes.produit',
            'lignes.classement',
            'createur'
        );
        $clientEstDivers = (bool) ($facture->client?->est_divers ?? false);
        $totalHt = (float) $facture->total;
        $tva = round($totalHt * 0.20, 2);
        $netAPayer = round($totalHt + $tva, 2);

        $data = [
            'company' => $this->companyBlock(),
            'facture' => [
                'numero' => $facture->numero,
                'date' => $this->formatDate($facture->date),
                'echeance_paiement' => $this->formatDate($facture->echeance_paiement),
                'date_paiement' => $this->formatDate($facture->date_paiement),
                'mode_paiement' => $facture->mode_paiement?->label() ?? '—',
                'statut' => $facture->statut?->label() ?? '—',
                'client_nom' => $clientEstDivers ? '' : ($facture->client?->nom ?? '—'),
                'client_adresse' => $clientEstDivers ? '' : ($facture->client?->adresse ?? '—'),
                'client_interlocuteur' => $clientEstDivers ? '' : ($facture->client?->contact ?? $facture->client?->telephone ?? '—'),
                'client_nif' => $clientEstDivers ? '' : ($facture->client?->NIF ?? '—'),
                'client_stat' => $clientEstDivers ? 'CLIENT DIVERS' : ($facture->client?->STAT ?? '—'),
                'total_ht' => $this->formatMoney($totalHt),
                'tva' => $this->formatMoney($tva),
                'net_a_payer' => $this->formatMoney($netAPayer),
                'montant_en_lettres' => Str::upper($this->amountToWords($netAPayer) . ' Ariary'),
                'bl_refs' => $facture->livraisons?->map(fn ($livraison) => $livraison->numero)->filter()->values()->all() ?? [],
                'notes' => $facture->notes,
            ],
            'lines' => $this->mapFactureLines($facture),
            'livraisons' => $this->mapFactureLivraisons($facture),
            'signature_labels' => ['CMP', 'TRANSPORTEUR', 'CLIENT'],
        ];

        return $this->renderPdf('pdf.documents.facture', $data, $facture->numero . '.pdf');
    }
    private function renderPdf(string $view, array $data, string $filename)
{
    Log::info('PDF export start', [
        'view' => $view,
        'filename' => $filename,
        'view_exists' => view()->exists($view),
    ]);

    try {
        if (! view()->exists($view)) {
            return response()->json([
                'success' => false,
                'message' => "Vue PDF introuvable: {$view}",
                'view' => $view,
                'filename' => $filename,
            ], 500);
        }

        $paper = config('cmp.pdf.paper', 'a4');
        $orientation = config('cmp.pdf.orientation', 'landscape');
        $safeFilename = $this->sanitizeFilename($filename);

        $html = view($view, $data)->render();

        Log::info('PDF html rendered', [
            'view' => $view,
            'filename' => $safeFilename,
            'html_length' => strlen($html),
        ]);

        $content = Pdf::loadHTML($html)
            ->setPaper($paper, $orientation)
            ->setWarnings(false)
            ->output();

        Log::info('PDF binary rendered', [
            'view' => $view,
            'filename' => $safeFilename,
            'bytes' => strlen($content),
        ]);

        if ($content === '' || strlen($content) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Le PDF genere est vide.',
                'filename' => $safeFilename,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'PDF genere avec succes.',
            'data' => [
                'filename' => $safeFilename,
                'mime_type' => 'application/pdf',
                'content_base64' => base64_encode($content),
            ],
        ]);
    } catch (\Throwable $exception) {
        report($exception);

        Log::error('PDF export failed', [
            'view' => $view,
            'filename' => $filename,
            'message' => $exception->getMessage(),
            'class' => $exception::class,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Generation PDF impossible.',
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
            'view' => $view,
            'filename' => $filename,
        ], 500);
    }
}
   
    private function companyBlock(): array
    {
        $company = config('cmp.company', []);
        $logoPath = $company['logo_path'] ?? null;
        $logoFullPath = $logoPath ? public_path($logoPath) : null;

        $identification = trim(implode(' - ', array_filter([
            filled($company['nif'] ?? null) ? 'NIF : ' . $company['nif'] : null,
            filled($company['stat'] ?? null) ? 'STATISTIQUE : ' . $company['stat'] : null,
            filled($company['rcs'] ?? null) ? 'RCS : ' . $company['rcs'] : null,
        ])));

        return [
            'name' => $company['name'] ?? 'COMPAGNIE MALAGASY DE PLASTIQUE',
            'logo_src' => $logoFullPath && file_exists($logoFullPath) ? $logoFullPath : null,
            'identification' => $identification,
            'address' => $company['address'] ?? '',
            'city' => $company['city'] ?? '',
            'phone' => $company['phone'] ?? '',
            'email' => $company['email'] ?? '',
        ];
    }
    private function mapLivraisonLines(Livraison $livraison): array
{
    return $livraison->lignes->values()->map(function ($ligne, $index) {
        $reference = $ligne->produit?->nomencla ?? $ligne->produit?->reference ?? '—';
        $designation = $ligne->produit?->designation ?? $ligne->produit?->nom ?? '—';
        $colisage = $ligne->produit?->colisage !== null
            ? $this->formatQty((float) $ligne->produit->colisage)
            : '';

        return [
            'no' => $index + 1,
            'reference' => $reference,
            'designation' => $designation,
            'colisage_type' => $ligne->produit?->format ?? $ligne->produit?->unite ?? '',
            'colisage_qte' => $colisage,
            'quantite_colis' => '',
            'quantite' => $this->formatQty((float) $ligne->quantite_livree),
            'quantite_extra' => '',
            'validation' => '',
        ];
    })->all();
}

private function mapBonSortieLines(BonSortie $bonSortie): array
{
    return $bonSortie->lignes->values()->map(function ($ligne, $index) {
        $reference = $ligne->produit?->nomencla ?? $ligne->produit?->reference ?? '—';
        $designation = $ligne->produit?->designation ?? $ligne->produit?->nom ?? '—';
        $colisage = $ligne->produit?->colisage !== null
            ? $this->formatQty((float) $ligne->produit->colisage)
            : '';

        return [
            'no' => $index + 1,
            'reference' => $reference,
            'designation' => $designation,
            'colisage_type' => $ligne->produit?->format ?? $ligne->produit?->unite ?? '',
            'colisage_qte' => $colisage,
            'quantite_colis' => '',
            'quantite' => $this->formatQty((float) $ligne->quantite),
            'quantite_extra' => '',
            'validation' => '',
        ];
    })->all();
}

private function mapJournalAchatLines(JournalAchat $journalAchat): array
{
    return $journalAchat->lignes->values()->map(function ($ligne, $index) {
        return [
            'no' => $index + 1,
            'reference' => $ligne->matiere?->reference ?? '—',
            'designation' => $ligne->matiere?->nom ?? '—',
            'colisage_type' => $ligne->matiere?->unite ?? '',
            'colisage_qte' => $this->formatQty((float) $ligne->quantite),
            'quantite_colis' => '',
            'quantite' => $this->formatQty((float) $ligne->quantite),
            'quantite_extra' => '',
            'validation' => '',
        ];
    })->all();
}

    private function mapFactureLines(Facture $facture): array
    {
        return $facture->lignes->values()->map(function ($ligne, $index) {
            $designation = $ligne->produit?->designation ?? $ligne->produit?->nomencla ?? '—';
            $reference = $ligne->produit?->nomencla ?? '—';

            return [
                'no' => $index + 1,
                'reference' => $reference,
                'designation' => $designation,
                'quantite' => $this->formatQty((float) $ligne->quantite),
                'prix_unitaire' => $this->formatMoney((float) $ligne->prix_unitaire),
                'montant' => $this->formatMoney((float) $ligne->total_ligne),
            ];
        })->all();
    }

    private function mapFactureLivraisons(Facture $facture): array
    {
        return ($facture->livraisons ?? collect())->values()->map(function ($livraison) {
            return [
                'numero' => $livraison->numero,
                'date' => $this->formatDate($livraison->date_livraison),
                'statut' => $livraison->statut,
                'total' => $this->formatMoney((float) ($livraison->pivot->total_livraison ?? 0)),
                'lignes_count' => (int) ($livraison->pivot->lignes_count ?? 0),
            ];
        })->all();
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_string($value) && $value !== '') {
            try {
                return (new \DateTimeImmutable($value))->format('d/m/Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        return '—';
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 0, ',', ' ') . ' Ar';
    }

    private function formatQty(float $value): string
    {
        $formatted = number_format($value, 3, ',', ' ');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }

    private function labelForMotif(?string $motif): string
    {
        return match ($motif) {
            'usage_interne' => 'Usage interne',
            'perte' => 'Perte',
            'echantillon' => 'Echantillon',
            'don' => 'Don',
            'autre' => 'Autre',
            default => $motif ?? '—',
        };
    }

    private function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'document.pdf';
    }

    private function amountToWords(float $amount): string
    {
        $number = (int) round($amount, 0);

        if ($number === 0) {
            return 'zero';
        }

        return trim($this->numberToFrenchWords($number));
    }

    private function numberToFrenchWords(int $number): string
    {
        $units = [
            0 => 'zero',
            1 => 'un',
            2 => 'deux',
            3 => 'trois',
            4 => 'quatre',
            5 => 'cinq',
            6 => 'six',
            7 => 'sept',
            8 => 'huit',
            9 => 'neuf',
            10 => 'dix',
            11 => 'onze',
            12 => 'douze',
            13 => 'treize',
            14 => 'quatorze',
            15 => 'quinze',
            16 => 'seize',
            17 => 'dix-sept',
            18 => 'dix-huit',
            19 => 'dix-neuf',
        ];

        $tens = [
            20 => 'vingt',
            30 => 'trente',
            40 => 'quarante',
            50 => 'cinquante',
            60 => 'soixante',
            70 => 'soixante-dix',
            80 => 'quatre-vingt',
            90 => 'quatre-vingt-dix',
        ];

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            if ($number < 70) {
                $ten = intdiv($number, 10) * 10;
                $unit = $number % 10;

                if ($unit === 0) {
                    return $tens[$ten];
                }

                if ($unit === 1) {
                    return $tens[$ten] . ' et un';
                }

                return $tens[$ten] . '-' . $units[$unit];
            }

            if ($number < 80) {
                $rest = $number - 60;
                return 'soixante-' . $this->numberToFrenchWords($rest);
            }

            $rest = $number - 80;
            if ($rest === 0) {
                return 'quatre-vingts';
            }

            return 'quatre-vingt-' . $this->numberToFrenchWords($rest);
        }

        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $rest = $number % 100;

            $prefix = $hundreds === 1 ? 'cent' : $units[$hundreds] . ' cent';
            if ($rest === 0) {
                return $hundreds > 1 ? $prefix . 's' : $prefix;
            }

            return $prefix . ' ' . $this->numberToFrenchWords($rest);
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $rest = $number % 1000;

            $prefix = $thousands === 1
                ? 'mille'
                : $this->numberToFrenchWords($thousands) . ' mille';

            if ($rest === 0) {
                return $prefix;
            }

            return $prefix . ' ' . $this->numberToFrenchWords($rest);
        }

        if ($number < 1000000000) {
            $millions = intdiv($number, 1000000);
            $rest = $number % 1000000;

            $prefix = $millions === 1
                ? 'un million'
                : $this->numberToFrenchWords($millions) . ' millions';

            if ($rest === 0) {
                return $prefix;
            }

            return $prefix . ' ' . $this->numberToFrenchWords($rest);
        }

        $milliards = intdiv($number, 1000000000);
        $rest = $number % 1000000000;

        $prefix = $milliards === 1
            ? 'un milliard'
            : $this->numberToFrenchWords($milliards) . ' milliards';

        if ($rest === 0) {
            return $prefix;
        }

        return $prefix . ' ' . $this->numberToFrenchWords($rest);
    }
}