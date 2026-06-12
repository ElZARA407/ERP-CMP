<?php
// app/Http/Controllers/Api/Kpi/DashboardController.php

namespace App\Http\Controllers\Api\Kpi;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\BonProduction;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\Stock;
use App\Enums\StatutFacture;
use App\Enums\StatutProduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    // ── GET /dashboard ────────────────────────────────────
    public function index(): JsonResponse
    {
        return $this->success([
            'production' => $this->kpiProduction(),
            'stock'      => $this->kpiStock(),
            'commercial' => $this->kpiCommercial(),
            'finance'    => $this->kpiFinance(),
        ]);
    }

    // ── GET /dashboard/production ─────────────────────────
    public function production(): JsonResponse
    {
        return $this->success($this->kpiProduction());
    }

    // ── GET /dashboard/stock ──────────────────────────────
    public function stock(): JsonResponse
    {
        return $this->success($this->kpiStock());
    }

    // ── GET /dashboard/commercial ─────────────────────────
    public function commercial(): JsonResponse
    {
        return $this->success($this->kpiCommercial());
    }

    // ── GET /dashboard/finance ────────────────────────────
    public function finance(): JsonResponse
    {
        return $this->success($this->kpiFinance());
    }

    // ── KPI privés ────────────────────────────────────────
    private function kpiProduction(): array
    {
        $moisCourant = now()->format('Y-m');

        return [
            'bp_actifs'          => BonProduction::whereIn('statut', [
                StatutProduction::OUVERT->value,
                StatutProduction::EN_COURS->value,
            ])->count(),
            'bp_clotures_mois'   => BonProduction::where('statut', StatutProduction::CLOTURE->value)
                ->whereRaw("DATE_FORMAT(updated_at, '%Y-%m') = ?", [$moisCourant])
                ->count(),
            'cout_production_mois' => BonProduction::where('statut', StatutProduction::CLOTURE->value)
                ->whereRaw("DATE_FORMAT(updated_at, '%Y-%m') = ?", [$moisCourant])
                ->sum('cout_total'),
        ];
    }

    private function kpiStock(): array
    {
        return [
            'total_references'   => Stock::where('stock_total', '>', 0)->count(),
            'references_rupture' => Stock::where('stock_total', '<=', 0)->count(),
            'valeur_stock_mp'    => DB::table('stocks')
                ->join('matieres_premieres', function ($j) {
                    $j->on('stocks.entite_id', '=', 'matieres_premieres.id')
                      ->where('stocks.entite_type', '=', 'matiere');
                })
                ->selectRaw('SUM(stocks.stock_total * matieres_premieres.prix_moyen) as total')
                ->value('total') ?? 0,
        ];
    }

    private function kpiCommercial(): array
    {
        $moisCourant = now()->format('Y-m');

        return [
            'commandes_en_cours'   => Commande::whereIn('statut', ['non_livree', 'partielle'])->count(),
            'commandes_en_retard'  => Commande::nonLivrees()
                ->where('date_livraison_prevue', '<', now())
                ->count(),
            'ca_mois'              => Facture::where('statut', StatutFacture::PAYEE->value)
                ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$moisCourant])
                ->sum('total'),
        ];
    }

    private function kpiFinance(): array
    {
        return [
            'factures_impayees'   => Facture::whereIn('statut', [
                StatutFacture::EMISE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])->count(),
            'montant_impaye'      => Facture::whereIn('statut', [
                StatutFacture::EMISE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])->sum('total'),
            'factures_en_retard'  => Facture::enRetard()->count(),
            'montant_retard'      => Facture::enRetard()->sum('total'),
        ];
    }
}