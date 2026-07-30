<?php

namespace App\Services;

use App\Enums\StatutFacture;
use App\Enums\StatutProduction;
use App\Enums\StatutRecyclage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly ReportVisibilityService $visibility
    ) {}

    public function overview(): array
    {
        $user = auth()->user();
        $role = $user?->role?->nom ?? 'guest';
        $cacheKey = 'dashboard.overview.v4.' . $role . '.' . ($user?->id ?? 0);

        return Cache::remember($cacheKey, now()->addSeconds(60), function () {
            return [
                'kpi' => $this->kpi(),
                'charts' => [
                    'ventes_30_jours' => $this->ventes30Jours(),
                    'production_objectif_realise' => $this->productionObjectifRealise(),
                    'stock_entrees_sorties' => $this->stockEntreesSorties(),
                    'top_produits' => $this->topProduits(),
                    'top_clients' => $this->topClients(),
                ],
                'alertes' => $this->alertes(),
                'generated_at' => now()->toDateTimeString(),
            ];
        });
    }

    public function legacy(): array
    {
        $overview = $this->overview();

        return [
            'production' => [
                'bp_actifs' => $overview['kpi']['bons_production_en_cours'],
                'bp_clotures_mois' => $this->bpCloturesMois(),
                'cout_production_mois' => $this->coutProductionMois(),
            ],
            'stock' => [
                'total_references' => $overview['kpi']['references_stock'],
                'references_rupture' => $overview['kpi']['produits_sous_minimum'] + $overview['kpi']['matieres_sous_minimum'],
                'valeur_stock_mp' => $this->valeurStockMatieres(),
            ],
            'commercial' => [
                'commandes_en_cours' => $overview['kpi']['commandes_en_attente'],
                'commandes_en_retard' => $this->commandesEnRetard(),
                'ca_mois' => $this->caMois(),
            ],
            'finance' => [
                'factures_impayees' => $overview['kpi']['factures_en_attente'],
                'montant_impaye' => $this->montantImpaye(),
                'factures_en_retard' => $this->facturesEnRetard(),
                'montant_retard' => $this->montantRetard(),
            ],
        ];
    }

    public function productionKpi(): array
    {
        return $this->legacy()['production'];
    }

    public function stockKpi(): array
    {
        return $this->legacy()['stock'];
    }

    public function commercialKpi(): array
    {
        return $this->legacy()['commercial'];
    }

    public function financeKpi(): array
    {
        return $this->legacy()['finance'];
    }

    public function pilotage(): array
    {
        return [
            'alertes' => $this->alertes(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    private function kpi(): array
    {
        $user = auth()->user();

        return [
            'commandes_en_attente' => $this->visibility
                ->restrictCommercialTable(DB::table('commandes'), 'commandes', $user)
                ->whereIn('commandes.statut', ['non_livree', 'partielle'])
                ->count(),

            'bons_production_en_cours' => DB::table('bon_productions')
                ->whereIn('statut', [
                    StatutProduction::OUVERT->value,
                    StatutProduction::EN_COURS->value,
                ])
                ->count(),

            'bons_transformation_en_cours' => DB::table('bon_transformations')
                ->whereIn('statut', [
                    StatutRecyclage::OUVERT->value,
                    StatutRecyclage::EN_COURS->value,
                ])
                ->count(),

            'livraisons_du_jour' => $this->visibility
                ->restrictLivraisonsFromCommercialScope(DB::table('livraisons'), $user)
                ->whereDate('livraisons.date_livraison', today())
                ->count(),

            'factures_en_attente' => $this->visibility
                ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
                ->whereIn('factures.statut', [
                    StatutFacture::EN_ATTENTE->value,
                    StatutFacture::EMISE->value,
                    StatutFacture::PARTIELLEMENT_PAYEE->value,
                ])
                ->count(),

            'valeur_totale_stock' => round($this->valeurStockMatieres() + $this->valeurStockProduits(), 2),

            'produits_sous_minimum' => $this->produitsSousMinimumCount(),

            'matieres_sous_minimum' => $this->matieresSousMinimumCount(),

            'references_stock' => DB::table('stocks')
                ->where('stock_total', '>', 0)
                ->count(),
        ];
    }

    private function ventes30Jours(): array
    {
        $user = auth()->user();
        $start = today()->subDays(29);
        $end = today();

        $rows = $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->selectRaw('DATE(factures.date) as jour, SUM(factures.total) as total')
            ->whereBetween('factures.date', [$start->toDateString(), $end->toDateString()])
            ->where('factures.statut', '<>', StatutFacture::ANNULEE->value)
            ->groupByRaw('DATE(factures.date)')
            ->orderBy('jour')
            ->get()
            ->keyBy('jour');

        return collect(range(0, 29))->map(function ($offset) use ($start, $rows) {
            $day = $start->copy()->addDays($offset)->toDateString();

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('d/m'),
                'total' => round((float) ($rows[$day]->total ?? 0), 2),
            ];
        })->values()->all();
    }

    private function productionObjectifRealise(): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $objectif = DB::table('bon_productions')
            ->whereBetween('date', [$start, $end])
            ->where('statut', '<>', StatutProduction::ANNULE->value)
            ->sum('quantite_cible');

        $realise = DB::table('bp_obtenues')
            ->join('bp_sessions', 'bp_obtenues.bp_session_id', '=', 'bp_sessions.id')
            ->where('bp_sessions.statut', 'validee')
            ->whereBetween('bp_sessions.date_session', [$start, $end])
            ->sum('bp_obtenues.quantite_produite');

        return [
            'objectif' => round((float) $objectif, 3),
            'realise' => round((float) $realise, 3),
            'taux' => (float) $objectif > 0 ? round(((float) $realise / (float) $objectif) * 100, 2) : 0,
        ];
    }

    private function stockEntreesSorties(): array
    {
        $start = today()->subDays(29);
        $end = today();

        $rows = DB::table('mouvements_stock')
            ->selectRaw("
                DATE(date_mouvement) as jour,
                SUM(CASE WHEN type IN ('entree', 'retour') THEN quantite ELSE 0 END) as entrees,
                SUM(CASE WHEN type = 'sortie' THEN quantite ELSE 0 END) as sorties
            ")
            ->whereBetween('date_mouvement', [$start->startOfDay(), $end->endOfDay()])
            ->groupByRaw('DATE(date_mouvement)')
            ->orderBy('jour')
            ->get()
            ->keyBy('jour');

        return collect(range(0, 29))->map(function ($offset) use ($start, $rows) {
            $day = $start->copy()->addDays($offset)->toDateString();

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('d/m'),
                'entrees' => round((float) ($rows[$day]->entrees ?? 0), 3),
                'sorties' => round((float) ($rows[$day]->sorties ?? 0), 3),
            ];
        })->values()->all();
    }

    private function topProduits(): array
    {
        $user = auth()->user();

        return $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->join('ligne_factures', 'factures.id', '=', 'ligne_factures.facture_id')
            ->join('produits', 'ligne_factures.produit_id', '=', 'produits.id')
            ->selectRaw('produits.id, produits.nomencla, produits.designation, SUM(ligne_factures.quantite) as quantite, SUM(ligne_factures.total_ligne) as total')
            ->where('factures.statut', '<>', StatutFacture::ANNULEE->value)
            ->groupBy('produits.id', 'produits.nomencla', 'produits.designation')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => $row->designation,
                'description' => $row->nomencla,
                'quantite' => round((float) $row->quantite, 3),
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function topClients(): array
    {
        $user = auth()->user();

        return $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->join('clients', 'factures.client_id', '=', 'clients.id')
            ->selectRaw('clients.id, clients.nom, clients.reference, SUM(factures.total) as total')
            ->where('factures.statut', '<>', StatutFacture::ANNULEE->value)
            ->groupBy('clients.id', 'clients.nom', 'clients.reference')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => $row->nom,
                'description' => $row->reference,
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function alertes(): array
    {
        $user = auth()->user();
        $alertes = [];

        foreach ($this->produitsSousMinimumRows(5) as $row) {
            $alertes[] = [
                'id' => 'stock-produit-' . $row->stock_id,
                'priorite' => 'haute',
                'type' => 'stock_faible',
                'titre' => 'Produit fini sous minimum',
                'message' => "{$row->designation} : stock {$row->stock_total}, seuil {$row->seuil}",
                'action_label' => 'Voir stock',
                'action_url' => '/stocks',
            ];
        }

        foreach ($this->matieresSousMinimumRows(5) as $row) {
            $alertes[] = [
                'id' => 'stock-matiere-' . $row->stock_id,
                'priorite' => 'haute',
                'type' => 'stock_faible',
                'titre' => 'Matière première sous minimum',
                'message' => "{$row->nom} : stock {$row->stock_total}, seuil {$row->seuil}",
                'action_label' => 'Voir stock',
                'action_url' => '/stocks',
            ];
        }

        $commandes = $this->visibility
            ->restrictCommercialTable(DB::table('commandes'), 'commandes', $user)
            ->join('clients', 'commandes.client_id', '=', 'clients.id')
            ->select('commandes.id', 'commandes.numero', 'commandes.date_livraison_prevue', 'clients.nom as client')
            ->whereIn('commandes.statut', ['non_livree', 'partielle'])
            ->whereDate('commandes.date_livraison_prevue', '<=', today()->addDays(2))
            ->orderBy('commandes.date_livraison_prevue')
            ->limit(5)
            ->get();

        foreach ($commandes as $commande) {
            $alertes[] = [
                'id' => 'commande-' . $commande->id,
                'priorite' => Carbon::parse($commande->date_livraison_prevue)->isPast() ? 'haute' : 'moyenne',
                'type' => 'commande_urgente',
                'titre' => 'Commande à livrer',
                'message' => "{$commande->numero} - {$commande->client} prévue le " . Carbon::parse($commande->date_livraison_prevue)->format('d/m/Y'),
                'action_label' => 'Ouvrir commande',
                'action_url' => '/commandes/' . $commande->id,
            ];
        }

        $factures = $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->join('clients', 'factures.client_id', '=', 'clients.id')
            ->select('factures.id', 'factures.numero', 'factures.echeance_paiement', 'factures.total', 'factures.montant_paye', 'clients.nom as client')
            ->whereIn('factures.statut', [
                StatutFacture::EMISE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])
            ->whereDate('factures.echeance_paiement', '<', today())
            ->orderBy('factures.echeance_paiement')
            ->limit(5)
            ->get();

        foreach ($factures as $facture) {
            $reste = max(0, (float) $facture->total - (float) $facture->montant_paye);

            $alertes[] = [
                'id' => 'facture-' . $facture->id,
                'priorite' => 'haute',
                'type' => 'facture_retard',
                'titre' => 'Facture en retard',
                'message' => "{$facture->numero} - {$facture->client}, reste à payer : " . number_format($reste, 0, ',', ' ') . ' Ar',
                'action_label' => 'Ouvrir facture',
                'action_url' => '/factures/' . $facture->id,
            ];
        }

        $bpBloques = DB::table('bon_productions')
            ->select('id', 'numero', 'date')
            ->whereIn('statut', [
                StatutProduction::OUVERT->value,
                StatutProduction::EN_COURS->value,
            ])
            ->whereDate('date', '<=', today()->subDays(3))
            ->orderBy('date')
            ->limit(5)
            ->get();

        foreach ($bpBloques as $bp) {
            $alertes[] = [
                'id' => 'bp-' . $bp->id,
                'priorite' => 'moyenne',
                'type' => 'bp_bloque',
                'titre' => 'BP ouvert depuis plusieurs jours',
                'message' => "{$bp->numero} ouvert le " . Carbon::parse($bp->date)->format('d/m/Y'),
                'action_label' => 'Ouvrir BP',
                'action_url' => '/production/' . $bp->id,
            ];
        }

        $priorityOrder = ['haute' => 1, 'moyenne' => 2, 'basse' => 3];

        return collect($alertes)
            ->sortBy(fn ($alerte) => $priorityOrder[$alerte['priorite']] ?? 9)
            ->take(12)
            ->values()
            ->all();
    }

    private function valeurStockMatieres(): float
    {
        return (float) DB::table('stocks')
            ->join('matieres_premieres', function ($join) {
                $join->on('stocks.entite_id', '=', 'matieres_premieres.id')
                    ->where('stocks.entite_type', '=', 'matiere');
            })
            ->selectRaw('SUM(stocks.stock_total * matieres_premieres.prix_moyen) as total')
            ->value('total') ?? 0;
    }

    private function valeurStockProduits(): float
    {
        return (float) DB::table('stocks')
            ->where('stocks.entite_type', 'produit')
            ->selectRaw("
                SUM(
                    stocks.stock_total * COALESCE(
                        (
                            SELECT bsc.cout_unitaire
                            FROM bp_obtenues bo
                            INNER JOIN bp_sessions bs ON bs.id = bo.bp_session_id
                            INNER JOIN bp_session_calculs bsc ON bsc.bp_session_id = bs.id
                            WHERE bo.produit_id = stocks.entite_id
                            AND bo.classement_id = stocks.classement_id
                            AND bs.statut = 'validee'
                            AND bsc.cout_unitaire > 0
                            ORDER BY bs.date_session DESC, bs.id DESC
                            LIMIT 1
                        ),
                        0
                    )
                ) as total
            ")
            ->value('total') ?? 0;
    }

    private function produitsSousMinimumCount(): int
    {
        return $this->produitsSousMinimumBase()->count();
    }

    private function matieresSousMinimumCount(): int
    {
        return $this->matieresSousMinimumBase()->count();
    }

    private function produitsSousMinimumRows(int $limit)
    {
        return $this->produitsSousMinimumBase()
            ->select('stocks.id as stock_id', 'produits.designation', 'produits.nomencla', 'stocks.stock_total', 'produits.seuil')
            ->orderBy('stocks.stock_total')
            ->limit($limit)
            ->get();
    }

    private function matieresSousMinimumRows(int $limit)
    {
        return $this->matieresSousMinimumBase()
            ->select('stocks.id as stock_id', 'matieres_premieres.nom', 'matieres_premieres.reference', 'stocks.stock_total', 'matieres_premieres.seuil')
            ->orderBy('stocks.stock_total')
            ->limit($limit)
            ->get();
    }

    private function produitsSousMinimumBase()
    {
        return DB::table('stocks')
            ->join('produits', function ($join) {
                $join->on('stocks.entite_id', '=', 'produits.id')
                    ->where('stocks.entite_type', '=', 'produit');
            })
            ->whereNotNull('produits.seuil')
            ->whereColumn('stocks.stock_total', '<=', 'produits.seuil');
    }

    private function matieresSousMinimumBase()
    {
        return DB::table('stocks')
            ->join('matieres_premieres', function ($join) {
                $join->on('stocks.entite_id', '=', 'matieres_premieres.id')
                    ->where('stocks.entite_type', '=', 'matiere');
            })
            ->whereNotNull('matieres_premieres.seuil')
            ->whereColumn('stocks.stock_total', '<=', 'matieres_premieres.seuil');
    }

    private function bpCloturesMois(): int
    {
        return DB::table('bon_productions')
            ->where('statut', StatutProduction::CLOTURE->value)
            ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    private function coutProductionMois(): float
    {
        return (float) DB::table('bon_productions')
            ->where('statut', StatutProduction::CLOTURE->value)
            ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('cout_total');
    }

    private function commandesEnRetard(): int
    {
        $user = auth()->user();

        return $this->visibility
            ->restrictCommercialTable(DB::table('commandes'), 'commandes', $user)
            ->whereIn('commandes.statut', ['non_livree', 'partielle'])
            ->whereDate('commandes.date_livraison_prevue', '<', today())
            ->count();
    }

    private function caMois(): float
    {
        $user = auth()->user();

        return (float) $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->where('factures.statut', StatutFacture::PAYEE->value)
            ->whereBetween('factures.date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('factures.total');
    }

    private function montantImpaye(): float
    {
        $user = auth()->user();

        return (float) $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->whereIn('factures.statut', [
                StatutFacture::EMISE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])
            ->selectRaw('SUM(factures.total - factures.montant_paye) as reste')
            ->value('reste') ?? 0;
    }

    private function facturesEnRetard(): int
    {
        $user = auth()->user();

        return $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->whereIn('factures.statut', [
                StatutFacture::EMISE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])
            ->whereDate('factures.echeance_paiement', '<', today())
            ->count();
    }

    private function montantRetard(): float
    {
        $user = auth()->user();

        return (float) $this->visibility
            ->restrictFacturesFromCommercialScope(DB::table('factures'), $user)
            ->whereIn('factures.statut', [
                StatutFacture::EMISE->value,
                StatutFacture::PARTIELLEMENT_PAYEE->value,
            ])
            ->whereDate('factures.echeance_paiement', '<', today())
            ->selectRaw('SUM(factures.total - factures.montant_paye) as reste')
            ->value('reste') ?? 0;
    }
}