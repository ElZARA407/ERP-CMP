<?php

namespace App\Services;

use App\Enums\StatutFacture;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Utilisateur;

class ReportService
{
    public function __construct(
        private readonly ReportVisibilityService $visibility
    ) {}
    
    public function overview(?string $dateDebut = null, ?string $dateFin = null, array $mouvementFilters = [], ?Utilisateur $user = null): array
    {
        $range = $this->resolveRange($dateDebut, $dateFin);

        return [
            'periode' => [
                'date_debut' => $range['start']->toDateString(),
                'date_fin' => $range['end']->toDateString(),
            ],
            'commercial' => $this->commercial($range, $user),
            'stock' => $this->stock($range),
            'production' => $this->production($range),
            'recyclage' => $this->recyclage($range),
            'finance' => $this->finance($range),
            'generated_at' => now()->toDateTimeString(),
            'mouvements' => $this->mouvements($range, $mouvementFilters),
        ];
    }

    public function mouvements(array $range, array $filters = []): array
    {
        $entiteType = $filters['entite_type'] ?? null;
        $entiteId = $filters['entite_id'] ?? null;
        $motif = $filters['motif'] ?? null;

        $query = DB::table('mouvements_stock as ms')
            ->leftJoin('produits', function ($join) {
                $join->on('ms.entite_id', '=', 'produits.id')
                    ->where('ms.entite_type', '=', 'produit');
            })
            ->leftJoin('matieres_premieres', function ($join) {
                $join->on('ms.entite_id', '=', 'matieres_premieres.id')
                    ->where('ms.entite_type', '=', 'matiere');
            })
            ->leftJoin('classement_produits', 'ms.classement_id', '=', 'classement_produits.id')
            ->selectRaw("
                DATE(ms.date_mouvement) as date_mouvement,
                ms.location_id,
                ms.entite_type,
                ms.entite_id,
                ms.classement_id,
                COALESCE(produits.nomencla, matieres_premieres.reference) as reference,
                COALESCE(produits.designation, matieres_premieres.nom) as designation,
                classement_produits.libelle as classement,
                ms.motif,
                ms.reference_type,
                ms.reference_id,

                SUM(CASE WHEN ms.type = 'sortie' THEN ms.quantite ELSE 0 END) as sorties,
                SUM(CASE WHEN ms.type = 'entree' AND ms.reference_type = 'bp_session' THEN ms.quantite ELSE 0 END) as entree_fabrication,
                SUM(CASE WHEN ms.type = 'entree' AND ms.reference_type <> 'bp_session' THEN ms.quantite ELSE 0 END) as autres_entrees,
                SUM(CASE WHEN ms.type = 'retour' THEN ms.quantite ELSE 0 END) as retours
            ")
            ->whereBetween('ms.date_mouvement', [
                $range['start']->copy()->startOfDay(),
                $range['end']->copy()->endOfDay(),
            ]);

        if ($entiteType) {
            $query->where('ms.entite_type', $entiteType);
        }

        if ($entiteId) {
            $query->where('ms.entite_id', (int) $entiteId);
        }

        if ($motif) {
            $query->where('ms.motif', 'like', '%' . trim((string) $motif) . '%');
        }

        $rows = $query
            ->groupByRaw("
                DATE(ms.date_mouvement),
                ms.location_id,
                ms.entite_type,
                ms.entite_id,
                ms.classement_id,
                produits.nomencla,
                produits.designation,
                matieres_premieres.reference,
                matieres_premieres.nom,
                classement_produits.libelle,
                ms.motif,
                ms.reference_type,
                ms.reference_id
            ")
            ->orderByDesc('date_mouvement')
            ->limit(500)
            ->get();

        $lignes = $rows->map(function ($row) {
            $stockAJour = $this->stockAJourALaDate(
                (string) $row->date_mouvement,
                (int) $row->location_id,
                (string) $row->entite_type,
                (int) $row->entite_id,
                $row->classement_id !== null ? (int) $row->classement_id : null
            );

            return [
                'date_mouvement' => $row->date_mouvement,
                'reference' => $row->reference,
                'designation' => $row->designation,
                'classement' => $row->classement,
                'sorties' => round((float) $row->sorties, 3),
                'entree_fabrication' => round((float) $row->entree_fabrication, 3),
                'autres_entrees' => round((float) $row->autres_entrees, 3),
                'retours' => round((float) $row->retours, 3),
                'stock_a_jour' => round($stockAJour, 3),
                'motif' => $row->motif,
                'tiers' => $this->resolveMouvementTiers((string) $row->reference_type, (int) $row->reference_id),
            ];
        })->all();

        return [
            'motifs' => $this->mouvementMotifs(),
            'lignes' => $lignes,
        ];
    }

    private function mouvementMotifs(): array
    {
        return DB::table('mouvements_stock')
            ->whereNotNull('motif')
            ->where('motif', '<>', '')
            ->distinct()
            ->orderBy('motif')
            ->pluck('motif')
            ->values()
            ->all();
    }

    private function stockAJourALaDate(
        string $date,
        int $locationId,
        string $entiteType,
        int $entiteId,
        ?int $classementId
    ): float {
        return (float) DB::table('mouvements_stock')
            ->where('location_id', $locationId)
            ->where('entite_type', $entiteType)
            ->where('entite_id', $entiteId)
            ->where(function ($query) use ($classementId) {
                if ($classementId === null) {
                    $query->whereNull('classement_id');
                    return;
                }

                $query->where('classement_id', $classementId);
            })
            ->where('date_mouvement', '<=', Carbon::parse($date)->endOfDay())
            ->selectRaw("
                SUM(
                    CASE
                        WHEN reference_type = 'ajustement_inventaire' AND ecart IS NOT NULL THEN ecart
                        WHEN type IN ('entree', 'retour') THEN quantite
                        WHEN type = 'sortie' THEN -quantite
                        ELSE 0
                    END
                ) as stock_calcule
            ")
            ->value('stock_calcule') ?? 0;
    }

    private function resolveMouvementTiers(string $referenceType, int $referenceId): ?string
    {
        return match ($referenceType) {
            'vente_directe' => DB::table('ventes_directes')
                ->join('clients', 'ventes_directes.client_id', '=', 'clients.id')
                ->where('ventes_directes.id', $referenceId)
                ->value('clients.nom'),

            'livraison' => DB::table('livraisons')
                ->join('clients', 'livraisons.client_id', '=', 'clients.id')
                ->where('livraisons.id', $referenceId)
                ->value('clients.nom'),

            'journal_achat' => DB::table('journal_achats')
                ->join('fournisseurs', 'journal_achats.fournisseur_id', '=', 'fournisseurs.id')
                ->where('journal_achats.id', $referenceId)
                ->value('fournisseurs.nom'),

            'bon_sortie' => DB::table('bon_sorties')
                ->leftJoin('clients', 'bon_sorties.client_id', '=', 'clients.id')
                ->leftJoin('locations', 'bon_sorties.destination_location_id', '=', 'locations.id')
                ->where('bon_sorties.id', $referenceId)
                ->selectRaw("COALESCE(clients.nom, locations.nom, bon_sorties.motif_detail, 'Bon de sortie') as tiers")
                ->value('tiers'),

            'bp_session' => 'Production',

            'bt_session' => 'Recyclage',

            'ajustement_inventaire' => 'Inventaire',

            default => null,
        };
    }

    public function commercial(array $range, ?Utilisateur $user = null): array
    {
        $facturesBase = fn () => $this->visibility
            ->restrictFacturesFromCommercialScope(
                DB::table('factures'),
                $user
            )
            ->whereBetween('factures.date', [$range['start']->toDateString(), $range['end']->toDateString()])
            ->where('factures.statut', '<>', StatutFacture::ANNULEE->value);

        $commandesBase = fn () => $this->visibility
            ->restrictCommercialTable(DB::table('commandes'), 'commandes', $user)
            ->whereBetween('commandes.date', [$range['start']->toDateString(), $range['end']->toDateString()]);

        $ventesDirectesBase = fn () => $this->visibility
            ->restrictCommercialTable(DB::table('ventes_directes'), 'ventes_directes', $user)
            ->whereBetween('ventes_directes.date', [$range['start']->toDateString(), $range['end']->toDateString()]);

        $livraisonsBase = fn () => $this->visibility
            ->restrictLivraisonsFromCommercialScope(DB::table('livraisons'), $user)
            ->whereBetween('livraisons.date_livraison', [$range['start']->toDateString(), $range['end']->toDateString()]);

        return [
            'ventes_par_periode' => $facturesBase()
                ->selectRaw('DATE(factures.date) as date, SUM(factures.total) as total')
                ->groupByRaw('DATE(factures.date)')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'total' => round((float) $row->total, 2),
                ])
                ->all(),

            'ventes_par_produit' => $facturesBase()
                ->join('ligne_factures', 'factures.id', '=', 'ligne_factures.facture_id')
                ->join('produits', 'ligne_factures.produit_id', '=', 'produits.id')
                ->selectRaw('produits.id, produits.nomencla, produits.designation, SUM(ligne_factures.quantite) as quantite, SUM(ligne_factures.total_ligne) as total')
                ->groupBy('produits.id', 'produits.nomencla', 'produits.designation')
                ->orderByDesc('total')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->nomencla,
                    'libelle' => $row->designation,
                    'quantite' => round((float) $row->quantite, 3),
                    'total' => round((float) $row->total, 2),
                ])
                ->all(),

            'ventes_par_client' => $facturesBase()
                ->join('clients', 'factures.client_id', '=', 'clients.id')
                ->selectRaw('clients.id, clients.reference, clients.nom, SUM(factures.total) as total')
                ->groupBy('clients.id', 'clients.reference', 'clients.nom')
                ->orderByDesc('total')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->reference,
                    'libelle' => $row->nom,
                    'total' => round((float) $row->total, 2),
                ])
                ->all(),

            'commandes_detaillees' => $commandesBase()
                ->join('clients', 'commandes.client_id', '=', 'clients.id')
                ->leftJoin('ligne_commandes', 'commandes.id', '=', 'ligne_commandes.commande_id')
                ->leftJoin('lignes_livraison', 'ligne_commandes.id', '=', 'lignes_livraison.ligne_commande_id')
                ->selectRaw("
                    commandes.id,
                    commandes.numero,
                    commandes.date,
                    commandes.date_livraison_prevue,
                    commandes.statut,
                    clients.nom as client,
                    COALESCE(SUM(ligne_commandes.quantite), 0) as quantite_commandee,
                    COALESCE(SUM(lignes_livraison.quantite_livree), 0) as quantite_livree,
                    COALESCE(SUM(ligne_commandes.quantite_restante), 0) as quantite_restante,
                    COALESCE(SUM(ligne_commandes.quantite * ligne_commandes.prix_unitaire), 0) as total
                ")
                ->groupBy('commandes.id', 'commandes.numero', 'commandes.date', 'commandes.date_livraison_prevue', 'commandes.statut', 'clients.nom')
                ->orderByDesc('commandes.date')
                ->limit(100)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'numero' => $row->numero,
                    'date' => $row->date,
                    'date_livraison_prevue' => $row->date_livraison_prevue,
                    'statut' => $row->statut,
                    'client' => $row->client,
                    'quantite_commandee' => round((float) $row->quantite_commandee, 3),
                    'quantite_livree' => round((float) $row->quantite_livree, 3),
                    'quantite_restante' => round((float) $row->quantite_restante, 3),
                    'total' => round((float) $row->total, 2),
                ])
                ->all(),

            'ventes_directes_detaillees' => $ventesDirectesBase()
                ->join('clients', 'ventes_directes.client_id', '=', 'clients.id')
                ->leftJoin('lignes_vente_directe', 'ventes_directes.id', '=', 'lignes_vente_directe.vente_directe_id')
                ->leftJoin('lignes_livraison', 'lignes_vente_directe.id', '=', 'lignes_livraison.ligne_vente_directe_id')
                ->selectRaw("
                    ventes_directes.id,
                    ventes_directes.numero,
                    ventes_directes.date,
                    ventes_directes.statut,
                    clients.nom as client,
                    COALESCE(SUM(lignes_vente_directe.quantite), 0) as quantite_commandee,
                    COALESCE(SUM(lignes_livraison.quantite_livree), 0) as quantite_livree,
                    COALESCE(SUM(lignes_vente_directe.total_ligne), 0) as total
                ")
                ->groupBy('ventes_directes.id', 'ventes_directes.numero', 'ventes_directes.date', 'ventes_directes.statut', 'clients.nom')
                ->orderByDesc('ventes_directes.date')
                ->limit(100)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'numero' => $row->numero,
                    'date' => $row->date,
                    'statut' => $row->statut,
                    'client' => $row->client,
                    'quantite_commandee' => round((float) $row->quantite_commandee, 3),
                    'quantite_livree' => round((float) $row->quantite_livree, 3),
                    'quantite_restante' => round(max(0, (float) $row->quantite_commandee - (float) $row->quantite_livree), 3),
                    'total' => round((float) $row->total, 2),
                ])
                ->all(),

            'livraisons_detaillees' => $livraisonsBase()
                ->join('clients', 'livraisons.client_id', '=', 'clients.id')
                ->leftJoin('lignes_livraison', 'livraisons.id', '=', 'lignes_livraison.livraison_id')
                ->selectRaw("
                    livraisons.id,
                    livraisons.numero,
                    livraisons.source_type,
                    livraisons.source_id,
                    livraisons.date_livraison,
                    livraisons.statut,
                    livraisons.reference_bc,
                    livraisons.reference_facture,
                    clients.nom as client,
                    COUNT(lignes_livraison.id) as lignes_count,
                    COALESCE(SUM(lignes_livraison.quantite_livree), 0) as quantite_livree
                ")
                ->groupBy(
                    'livraisons.id',
                    'livraisons.numero',
                    'livraisons.source_type',
                    'livraisons.source_id',
                    'livraisons.date_livraison',
                    'livraisons.statut',
                    'livraisons.reference_bc',
                    'livraisons.reference_facture',
                    'clients.nom'
                )
                ->orderByDesc('livraisons.date_livraison')
                ->limit(100)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'numero' => $row->numero,
                    'source_type' => $row->source_type,
                    'source_id' => (int) $row->source_id,
                    'date_livraison' => $row->date_livraison,
                    'statut' => $row->statut,
                    'client' => $row->client,
                    'reference_bc' => $row->reference_bc,
                    'reference_facture' => $row->reference_facture,
                    'lignes_count' => (int) $row->lignes_count,
                    'quantite_livree' => round((float) $row->quantite_livree, 3),
                ])
                ->all(),

            'commandes_non_livrees' => $commandesBase()
                ->join('clients', 'commandes.client_id', '=', 'clients.id')
                ->leftJoin('ligne_commandes', 'commandes.id', '=', 'ligne_commandes.commande_id')
                ->selectRaw('commandes.id, commandes.numero, commandes.date, commandes.date_livraison_prevue, commandes.statut, clients.nom as client, SUM(ligne_commandes.quantite_restante) as quantite_restante')
                ->whereIn('commandes.statut', ['non_livree', 'partielle'])
                ->groupBy('commandes.id', 'commandes.numero', 'commandes.date', 'commandes.date_livraison_prevue', 'commandes.statut', 'clients.nom')
                ->orderBy('commandes.date_livraison_prevue')
                ->limit(30)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'numero' => $row->numero,
                    'date' => $row->date,
                    'date_livraison_prevue' => $row->date_livraison_prevue,
                    'statut' => $row->statut,
                    'client' => $row->client,
                    'quantite_restante' => round((float) $row->quantite_restante, 3),
                ])
                ->all(),
        ];
    }

    public function stock(array $range): array
    {
        return [
            'etat_stock' => [
                'references' => DB::table('stocks')->count(),
                'references_positives' => DB::table('stocks')->where('stock_total', '>', 0)->count(),
                'ruptures' => DB::table('stocks')->where('stock_total', '<=', 0)->count(),
                'valeur_matieres' => $this->valeurStockMatieres(),
            ],

            'mouvements' => DB::table('mouvements_stock')
                ->selectRaw('DATE(date_mouvement) as date, type, SUM(quantite) as quantite')
                ->whereBetween('date_mouvement', [$range['start']->startOfDay(), $range['end']->endOfDay()])
                ->groupByRaw('DATE(date_mouvement), type')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'type' => $row->type,
                    'quantite' => round((float) $row->quantite, 3),
                ])
                ->all(),

            'produits_sous_minimum' => DB::table('stocks')
                ->join('produits', function ($join) {
                    $join->on('stocks.entite_id', '=', 'produits.id')
                        ->where('stocks.entite_type', '=', 'produit');
                })
                ->leftJoin('classement_produits', 'stocks.classement_id', '=', 'classement_produits.id')
                ->selectRaw('stocks.id, produits.nomencla, produits.designation, classement_produits.libelle as classement, stocks.stock_total, produits.seuil')
                ->whereNotNull('produits.seuil')
                ->whereColumn('stocks.stock_total', '<=', 'produits.seuil')
                ->orderBy('stocks.stock_total')
                ->limit(30)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->nomencla,
                    'libelle' => $row->designation,
                    'classement' => $row->classement,
                    'stock_total' => round((float) $row->stock_total, 3),
                    'seuil' => round((float) $row->seuil, 3),
                ])
                ->all(),

            'matieres_sous_minimum' => DB::table('stocks')
                ->join('matieres_premieres', function ($join) {
                    $join->on('stocks.entite_id', '=', 'matieres_premieres.id')
                        ->where('stocks.entite_type', '=', 'matiere');
                })
                ->selectRaw('stocks.id, matieres_premieres.reference, matieres_premieres.nom, stocks.stock_total, matieres_premieres.seuil')
                ->whereNotNull('matieres_premieres.seuil')
                ->whereColumn('stocks.stock_total', '<=', 'matieres_premieres.seuil')
                ->orderBy('stocks.stock_total')
                ->limit(30)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->reference,
                    'libelle' => $row->nom,
                    'stock_total' => round((float) $row->stock_total, 3),
                    'seuil' => round((float) $row->seuil, 3),
                ])
                ->all(),
        ];
    }

    public function production(array $range): array
    {
        return [
            'objectif_vs_realise' => DB::table('bon_productions')
                ->leftJoin('bp_sessions', function ($join) {
                    $join->on('bon_productions.id', '=', 'bp_sessions.bon_production_id')
                        ->where('bp_sessions.statut', '=', 'validee');
                })
                ->leftJoin('bp_obtenues', 'bp_sessions.id', '=', 'bp_obtenues.bp_session_id')
                ->join('produits', 'bon_productions.produit_id', '=', 'produits.id')
                ->selectRaw('produits.id, produits.nomencla, produits.designation, SUM(DISTINCT bon_productions.quantite_cible) as objectif, SUM(bp_obtenues.quantite_produite) as realise')
                ->whereBetween('bon_productions.date', [$range['start']->toDateString(), $range['end']->toDateString()])
                ->where('bon_productions.statut', '<>', 'annule')
                ->groupBy('produits.id', 'produits.nomencla', 'produits.designation')
                ->orderByDesc('realise')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->nomencla,
                    'libelle' => $row->designation,
                    'objectif' => round((float) $row->objectif, 3),
                    'realise' => round((float) $row->realise, 3),
                    'taux' => (float) $row->objectif > 0
                        ? round(((float) $row->realise / (float) $row->objectif) * 100, 2)
                        : 0,
                ])
                ->all(),

            'production_par_machine' => DB::table('bp_sessions')
                ->join('machines', 'bp_sessions.machine_id', '=', 'machines.id')
                ->join('bp_obtenues', 'bp_sessions.id', '=', 'bp_obtenues.bp_session_id')
                ->selectRaw('machines.id, machines.nom, SUM(bp_obtenues.quantite_produite) as quantite')
                ->where('bp_sessions.statut', 'validee')
                ->whereBetween('bp_sessions.date_session', [$range['start']->toDateString(), $range['end']->toDateString()])
                ->groupBy('machines.id', 'machines.nom')
                ->orderByDesc('quantite')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'libelle' => $row->nom,
                    'quantite' => round((float) $row->quantite, 3),
                ])
                ->all(),

            'consommation_matiere' => DB::table('bp_mps')
                ->join('bp_sessions', 'bp_mps.bp_session_id', '=', 'bp_sessions.id')
                ->join('matieres_premieres', 'bp_mps.matiere_id', '=', 'matieres_premieres.id')
                ->selectRaw('matieres_premieres.id, matieres_premieres.reference, matieres_premieres.nom, SUM(bp_mps.quantite_utilisee - bp_mps.quantite_restituee) as quantite, SUM(bp_mps.cout_matiere) as cout')
                ->where('bp_sessions.statut', 'validee')
                ->whereBetween('bp_sessions.date_session', [$range['start']->toDateString(), $range['end']->toDateString()])
                ->groupBy('matieres_premieres.id', 'matieres_premieres.reference', 'matieres_premieres.nom')
                ->orderByDesc('quantite')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->reference,
                    'libelle' => $row->nom,
                    'quantite' => round((float) $row->quantite, 3),
                    'cout' => round((float) $row->cout, 2),
                ])
                ->all(),

            'cout_production' => [
                'cout_total' => round((float) DB::table('bp_session_calculs')
                    ->join('bp_sessions', 'bp_session_calculs.bp_session_id', '=', 'bp_sessions.id')
                    ->where('bp_sessions.statut', 'validee')
                    ->whereBetween('bp_sessions.date_session', [$range['start']->toDateString(), $range['end']->toDateString()])
                    ->sum('bp_session_calculs.cout_global'), 2),
            ],
        ];
    }

    public function recyclage(array $range): array
    {
        return [
            'quantite_transformee' => DB::table('bt_mps')
                ->join('bt_sessions', 'bt_mps.bt_session_id', '=', 'bt_sessions.id')
                ->selectRaw('bt_mps.type, SUM(bt_mps.quantite) as quantite')
                ->where('bt_sessions.statut', 'validee')
                ->whereBetween('bt_sessions.date_session', [$range['start']->toDateString(), $range['end']->toDateString()])
                ->groupBy('bt_mps.type')
                ->get()
                ->map(fn ($row) => [
                    'type' => $row->type,
                    'quantite' => round((float) $row->quantite, 3),
                ])
                ->all(),

            'evolution_mensuelle' => DB::table('bt_mps')
                ->join('bt_sessions', 'bt_mps.bt_session_id', '=', 'bt_sessions.id')
                ->selectRaw("DATE_FORMAT(bt_sessions.date_session, '%Y-%m') as mois, bt_mps.type, SUM(bt_mps.quantite) as quantite")
                ->where('bt_sessions.statut', 'validee')
                ->whereBetween('bt_sessions.date_session', [$range['start']->copy()->subMonths(11)->toDateString(), $range['end']->toDateString()])
                ->groupByRaw("DATE_FORMAT(bt_sessions.date_session, '%Y-%m'), bt_mps.type")
                ->orderBy('mois')
                ->get()
                ->map(fn ($row) => [
                    'mois' => $row->mois,
                    'type' => $row->type,
                    'quantite' => round((float) $row->quantite, 3),
                ])
                ->all(),
        ];
    }

    public function finance(array $range): array
    {
        return [
            'chiffre_affaires' => round((float) DB::table('factures')
                ->whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()])
                ->where('statut', '<>', StatutFacture::ANNULEE->value)
                ->sum('total'), 2),

            'factures_emises' => DB::table('factures')
                ->whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()])
                ->where('statut', '<>', StatutFacture::ANNULEE->value)
                ->count(),

            'factures_en_attente' => DB::table('factures')
                ->whereIn('statut', [
                    StatutFacture::EMISE->value,
                    StatutFacture::PARTIELLEMENT_PAYEE->value,
                ])
                ->count(),

            'factures_en_retard' => DB::table('factures')
                ->whereIn('statut', [
                    StatutFacture::EMISE->value,
                    StatutFacture::PARTIELLEMENT_PAYEE->value,
                ])
                ->whereDate('echeance_paiement', '<', today())
                ->count(),

            'clients_debiteurs' => DB::table('factures')
                ->join('clients', 'factures.client_id', '=', 'clients.id')
                ->selectRaw('clients.id, clients.reference, clients.nom, SUM(factures.total - factures.montant_paye) as reste')
                ->whereIn('factures.statut', [
                    StatutFacture::EMISE->value,
                    StatutFacture::PARTIELLEMENT_PAYEE->value,
                ])
                ->groupBy('clients.id', 'clients.reference', 'clients.nom')
                ->havingRaw('reste > 0')
                ->orderByDesc('reste')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'reference' => $row->reference,
                    'libelle' => $row->nom,
                    'reste' => round((float) $row->reste, 2),
                ])
                ->all(),
        ];
    }

    private function resolveRange(?string $dateDebut, ?string $dateFin): array
    {
        $end = $dateFin ? Carbon::parse($dateFin) : today();
        $start = $dateDebut ? Carbon::parse($dateDebut) : $end->copy()->subDays(29);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
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
}