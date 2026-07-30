<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Organisation\LocationController;
use App\Http\Controllers\Api\Organisation\RoleController;
use App\Http\Controllers\Api\Organisation\UtilisateurController;
use App\Http\Controllers\Api\Rh\PosteController;
use App\Http\Controllers\Api\Rh\EmployeController;
use App\Http\Controllers\Api\Catalogue\CategorieProduitController;
use App\Http\Controllers\Api\Catalogue\MatierePremierController;
use App\Http\Controllers\Api\Catalogue\ProduitController;
use App\Http\Controllers\Api\Catalogue\ClassementProduitController;
use App\Http\Controllers\Api\Stock\StockController;
use App\Http\Controllers\Api\Stock\MouvementStockController;
use App\Http\Controllers\Api\Commercial\ClientController;
use App\Http\Controllers\Api\Commercial\FournisseurController;
use App\Http\Controllers\Api\Commercial\ContratController;
use App\Http\Controllers\Api\Commercial\CommandeController;
use App\Http\Controllers\Api\Commercial\VenteDirecteController;
use App\Http\Controllers\Api\Achat\DemandeAchatController;
use App\Http\Controllers\Api\Achat\JournalAchatController;
use App\Http\Controllers\Api\Production\BonProductionController;
use App\Http\Controllers\Api\Production\BpSessionController;
use App\Http\Controllers\Api\Production\ProductionCostController;
use App\Http\Controllers\Api\Production\MachineController;
use App\Http\Controllers\Api\Recyclage\BonTransformationController;
use App\Http\Controllers\Api\Recyclage\BtSessionController;
use App\Http\Controllers\Api\Logistique\LivraisonController;
use App\Http\Controllers\Api\Logistique\BonSortieController;
use App\Http\Controllers\Api\Finance\FactureController;
use App\Http\Controllers\Api\Kpi\DashboardController;
use App\Http\Controllers\Api\Documents\PdfExportController;
use App\Http\Controllers\Api\Reports\ReportController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth');

        Route::post('logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum');

        Route::get('me', [AuthController::class, 'me'])
            ->middleware('auth:sanctum');

        Route::post('refresh', [AuthController::class, 'refresh'])
            ->middleware('auth:sanctum');

        Route::put('password', [AuthController::class, 'changePassword'])
            ->middleware('auth:sanctum');
    });

    Route::middleware(['auth:sanctum', 'actif'])->group(function () {
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('production', [DashboardController::class, 'production']);
            Route::get('stock', [DashboardController::class, 'stock']);
            Route::get('commercial', [DashboardController::class, 'commercial']);
            Route::get('finance', [DashboardController::class, 'finance']);
            Route::get('pilotage', [DashboardController::class, 'pilotage']);
        });

        Route::prefix('rapports')
            ->middleware('role:admin,commercial,finance,logistique,responsable_achat,responsable_prod,operateur_saisie')
            ->group(function () {
                Route::get('/', [ReportController::class, 'overview']);
                Route::get('export', [ReportController::class, 'export']);
            });

        /*
        |--------------------------------------------------------------------------
        | Lecture commune - référentiels et listes utiles aux formulaires
        |--------------------------------------------------------------------------
        */
        Route::prefix('organisation')->group(function () {
            Route::get('locations', [LocationController::class, 'index']);
            Route::get('locations/{location}', [LocationController::class, 'show']);
        });

        Route::prefix('rh')->group(function () {
            Route::apiResource('postes', PosteController::class)->only(['index', 'show']);
            Route::apiResource('employes', EmployeController::class)->only(['index', 'show']);
        });

        Route::prefix('catalogue')->group(function () {
            Route::apiResource('categories', CategorieProduitController::class)->only(['index', 'show']);
            Route::apiResource('matieres-premieres', MatierePremierController::class)->only(['index', 'show']);
            Route::apiResource('produits', ProduitController::class)->only(['index', 'show']);

            Route::get('classements', [ClassementProduitController::class, 'index']);
            Route::apiResource('produits.classements', ClassementProduitController::class)
                ->only(['index', 'show'])
                ->shallow();
        });

        Route::prefix('stocks')->group(function () {
            Route::get('/', [StockController::class, 'index']);
            Route::get('ruptures', [StockController::class, 'ruptures']);
            Route::get('par-location/{id}', [StockController::class, 'parLocation']);
            Route::get('par-produit/{id}', [StockController::class, 'parProduit']);
            Route::get('par-matiere/{id}', [StockController::class, 'parMatiere']);
            Route::get('alertes', [StockController::class, 'alertes']);
            Route::get('mouvements', [MouvementStockController::class, 'index']);
            Route::get('mouvements/{id}', [MouvementStockController::class, 'show']);
        });

        Route::prefix('commercial')->group(function () {
            Route::apiResource('clients', ClientController::class)->only(['index', 'show']);
            Route::get('clients/{client}/encours', [ClientController::class, 'encours']);
            Route::get('clients/{client}/historique', [ClientController::class, 'historique']);

            Route::apiResource('fournisseurs', FournisseurController::class)->only(['index', 'show']);
            Route::get('fournisseurs/{fournisseur}/historique', [FournisseurController::class, 'historique']);

            Route::apiResource('contrats', ContratController::class)->only(['index', 'show']);
            Route::apiResource('contrats.lignes', \App\Http\Controllers\Api\Commercial\LigneContratController::class)
                ->only(['index', 'show'])
                ->shallow();

            Route::apiResource('commandes', CommandeController::class)->only(['index', 'show']);
            Route::apiResource('commandes.lignes', \App\Http\Controllers\Api\Commercial\LigneCommandeController::class)
                ->only(['index', 'show'])
                ->shallow();

            Route::apiResource('ventes-directes', VenteDirecteController::class)->only(['index', 'show']);
            Route::apiResource('ventes-directes.lignes', \App\Http\Controllers\Api\Commercial\LigneVenteDirecteController::class)
                ->only(['index', 'show'])
                ->shallow();
        });

        Route::prefix('achats')->group(function () {
            Route::apiResource('demandes', DemandeAchatController::class)->only(['index', 'show']);

            Route::get('bons-reception/{br}/pdf', [PdfExportController::class, 'journalAchat']);

            Route::apiResource('bons-reception', JournalAchatController::class)
                ->only(['index', 'show'])
                ->parameters(['bons-reception' => 'bonsReception']);

            Route::apiResource('bons-reception.lignes', \App\Http\Controllers\Api\Achat\LigneAchatController::class)
                ->only(['index', 'show'])
                ->shallow();
        });

        Route::prefix('production')->group(function () {
            Route::apiResource('machines', MachineController::class)->only(['index', 'show']);
            Route::apiResource('bons-production', BonProductionController::class)->only(['index', 'show']);

            Route::get('couts/produits/{produit}', [ProductionCostController::class, 'parProduit']);
            Route::get('couts/bons-production/{bonsProduction}', [ProductionCostController::class, 'parBonProduction']);

            Route::apiResource('bons-production.sessions', BpSessionController::class)
                ->only(['index', 'show'])
                ->shallow();
        });

        Route::prefix('recyclage')->group(function () {
            Route::apiResource('bons-transformation', BonTransformationController::class)->only(['index', 'show']);

            Route::apiResource('bons-transformation.sessions', BtSessionController::class)
                ->only(['index', 'show'])
                ->shallow();
        });

        Route::prefix('logistique')->group(function () {
            Route::apiResource('livraisons', LivraisonController::class)->only(['index', 'show']);
            Route::get('livraisons/{livraison}/pdf', [PdfExportController::class, 'livraison']);

            Route::apiResource('livraisons.lignes', \App\Http\Controllers\Api\Logistique\LigneLivraisonController::class)
                ->only(['index', 'show'])
                ->shallow();

            Route::apiResource('bons-sortie', BonSortieController::class)
                ->only(['index', 'show'])
                ->parameters(['bons-sortie' => 'bonsSortie']);

            Route::get('bons-sortie/{bon}/pdf', [PdfExportController::class, 'bonSortie']);

            Route::apiResource('bons-sortie.lignes', \App\Http\Controllers\Api\Logistique\LigneSortieController::class)
                ->only(['index', 'show'])
                ->shallow();
        });

        Route::prefix('finance')->group(function () {
            Route::apiResource('factures', FactureController::class)->only(['index', 'show']);
            Route::get('factures/{facture}/pdf', [PdfExportController::class, 'facture']);
            Route::get('factures/retards', [FactureController::class, 'enRetard']);
        });

        /*
        |--------------------------------------------------------------------------
        | Écriture / actions - protégées par rôle
        |--------------------------------------------------------------------------
        */
        Route::prefix('organisation')
            ->middleware('role:admin')
            ->group(function () {
                Route::apiResource('roles', RoleController::class);
                Route::apiResource('utilisateurs', UtilisateurController::class);

                Route::patch(
                    'utilisateurs/{utilisateur}/toggle-actif',
                    [UtilisateurController::class, 'toggleActif']
                );

                Route::post('locations', [LocationController::class, 'store']);
                Route::put('locations/{location}', [LocationController::class, 'update']);
                Route::patch('locations/{location}', [LocationController::class, 'update']);
                Route::delete('locations/{location}', [LocationController::class, 'destroy']);
            });

        Route::prefix('rh')
            ->middleware('role:admin')
            ->group(function () {
                Route::apiResource('postes', PosteController::class)->except(['index', 'show']);
                Route::apiResource('employes', EmployeController::class)->except(['index', 'show']);
            });

        Route::prefix('catalogue')
            ->middleware('role:admin,responsable_prod,responsable_achat,operateur_saisie')
            ->group(function () {
                Route::post('produits/import', [ProduitController::class, 'import']);
                Route::post('matieres-premieres/import', [MatierePremierController::class, 'import']);

                Route::apiResource('categories', CategorieProduitController::class)->except(['index', 'show']);
                Route::apiResource('matieres-premieres', MatierePremierController::class)->except(['index', 'show']);
                Route::apiResource('produits', ProduitController::class)->except(['index', 'show']);

                Route::apiResource('produits.classements', ClassementProduitController::class)
                    ->except(['index', 'show'])
                    ->shallow();
            });

        Route::prefix('stocks')
            ->middleware('role:admin,logistique,responsable_achat,operateur_saisie')
            ->group(function () {
                Route::post('import', [StockController::class, 'import']);
                Route::post('/', [StockController::class, 'store']);
                Route::post('ajustements', [StockController::class, 'ajusterInventaire']);
            });

        Route::prefix('commercial')->group(function () {
            Route::middleware('role:admin,commercial,finance')->group(function () {
                Route::apiResource('clients', ClientController::class)->except(['index', 'show']);
            });

            Route::middleware('role:admin,commercial,responsable_achat')->group(function () {
                Route::apiResource('fournisseurs', FournisseurController::class)->except(['index', 'show']);
            });

            Route::middleware('role:admin,commercial')->group(function () {
                Route::apiResource('contrats', ContratController::class)->except(['index', 'show']);

                Route::apiResource('contrats.lignes', \App\Http\Controllers\Api\Commercial\LigneContratController::class)
                    ->except(['index', 'show'])
                    ->shallow();

                Route::apiResource('ventes-directes', VenteDirecteController::class)->except(['index', 'show']);
                Route::post('ventes-directes/{vente}/annuler', [VenteDirecteController::class, 'annuler']);

                Route::apiResource('ventes-directes.lignes', \App\Http\Controllers\Api\Commercial\LigneVenteDirecteController::class)
                    ->except(['index', 'show'])
                    ->shallow();

                Route::apiResource('commandes', CommandeController::class)->except(['index', 'show']);
                Route::post('commandes/{commande}/duplicate', [CommandeController::class, 'duplicate']);

                Route::apiResource('commandes.lignes', \App\Http\Controllers\Api\Commercial\LigneCommandeController::class)
                    ->except(['index', 'show'])
                    ->shallow();
            });

            Route::middleware('role:admin,logistique')->group(function () {
                Route::post('ventes-directes/{vente}/valider', [VenteDirecteController::class, 'valider']);
            });
        });

        Route::prefix('achats')
            ->middleware('role:admin,responsable_achat')
            ->group(function () {
                Route::apiResource('demandes', DemandeAchatController::class)->except(['index', 'show']);
                Route::post('demandes/{demande}/soumettre', [DemandeAchatController::class, 'soumettre']);
                Route::post('demandes/{demande}/approuver', [DemandeAchatController::class, 'approuver']);
                Route::post('demandes/{demande}/rejeter', [DemandeAchatController::class, 'rejeter']);

                Route::apiResource('bons-reception', JournalAchatController::class)
                    ->except(['index', 'show'])
                    ->parameters(['bons-reception' => 'bonsReception']);

                Route::post('bons-reception/{bonsReception}/valider', [JournalAchatController::class, 'valider']);

                Route::apiResource('bons-reception.lignes', \App\Http\Controllers\Api\Achat\LigneAchatController::class)
                    ->except(['index', 'show'])
                    ->shallow();
            });

        Route::prefix('production')
            ->middleware('role:admin,responsable_prod,operateur_saisie')
            ->group(function () {
                Route::apiResource('machines', MachineController::class)->except(['index', 'show']);
                Route::apiResource('bons-production', BonProductionController::class)->except(['index', 'show']);

                Route::post('bons-production/{bonsProduction}/cloture', [BonProductionController::class, 'cloture']);
                Route::post('bons-production/{bonsProduction}/annuler', [BonProductionController::class, 'annuler']);

                Route::apiResource('bons-production.sessions', BpSessionController::class)
                    ->except(['index', 'show'])
                    ->shallow();

                Route::post('sessions/{session}/matieres', [BpSessionController::class, 'ajouterMatiere']);
                Route::post('sessions/{session}/obtenus', [BpSessionController::class, 'ajouterObtenu']);
                Route::post('sessions/{session}/employes', [BpSessionController::class, 'ajouterEmploye']);
                Route::post('sessions/{session}/evenements', [BpSessionController::class, 'ajouterEvenement']);
            });

        Route::prefix('production')
            ->middleware('role:admin,responsable_prod,logistique')
            ->group(function () {
                Route::post('sessions/{session}/valider', [BpSessionController::class, 'valider']);
            });

        Route::prefix('recyclage')
            ->middleware('role:admin,responsable_prod,operateur_saisie')
            ->group(function () {
                Route::apiResource('bons-transformation', BonTransformationController::class)->except(['index', 'show']);
                Route::post('bons-transformation/{bt}/cloture', [BonTransformationController::class, 'cloture']);

                Route::apiResource('bons-transformation.sessions', BtSessionController::class)
                    ->except(['index', 'show'])
                    ->shallow();

                Route::post('bt-sessions/{session}/matieres', [BtSessionController::class, 'ajouterMatiere']);
                Route::post('bt-sessions/{session}/employes', [BtSessionController::class, 'ajouterEmploye']);
                Route::post('bt-sessions/{session}/evenements', [BtSessionController::class, 'ajouterEvenement']);
            });

        Route::prefix('recyclage')
            ->middleware('role:admin,responsable_prod,logistique')
            ->group(function () {
                Route::post('bt-sessions/{session}/valider', [BtSessionController::class, 'valider']);
            });

        Route::prefix('logistique')
            ->middleware('role:admin,logistique,commercial')
            ->group(function () {
                Route::apiResource('livraisons', LivraisonController::class)->except(['index', 'show']);
                Route::post('livraisons/{livraison}/confirmer', [LivraisonController::class, 'confirmer']);
                Route::post('livraisons/{livraison}/annuler', [LivraisonController::class, 'annuler']);

                Route::apiResource('livraisons.lignes', \App\Http\Controllers\Api\Logistique\LigneLivraisonController::class)
                    ->except(['index', 'show'])
                    ->shallow();

                Route::apiResource('bons-sortie', BonSortieController::class)
                    ->except(['index', 'show'])
                    ->parameters(['bons-sortie' => 'bonsSortie']);

                Route::post('bons-sortie/{bonsSortie}/valider', [BonSortieController::class, 'valider']);

                Route::apiResource('bons-sortie.lignes', \App\Http\Controllers\Api\Logistique\LigneSortieController::class)
                    ->except(['index', 'show'])
                    ->shallow();
            });

        Route::prefix('finance')
            ->middleware('role:admin,finance')
            ->group(function () {
                Route::post('factures/preview', [FactureController::class, 'preview']);
                Route::apiResource('factures', FactureController::class)->except(['index', 'show']);
                Route::post('factures/{facture}/payer', [FactureController::class, 'payer']);
                Route::post('factures/{facture}/annuler', [FactureController::class, 'annuler']);
            });
    });
});