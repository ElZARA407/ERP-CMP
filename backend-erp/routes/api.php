<?php
// routes/api.php

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
use App\Http\Controllers\Api\Recyclage\BonTransformationController;
use App\Http\Controllers\Api\Recyclage\BtSessionController;
use App\Http\Controllers\Api\Logistique\LivraisonController;
use App\Http\Controllers\Api\Logistique\BonSortieController;
use App\Http\Controllers\Api\Finance\FactureController;
use App\Http\Controllers\Api\Kpi\DashboardController;

/*
|--------------------------------------------------------------------------
| Routes publiques - Authentification
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // Auth
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

    /*
    |--------------------------------------------------------------------------
    | Routes protégées - auth:sanctum + utilisateur actif
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'actif'])->group(function () {

        // ── Dashboard ─────────────────────────────────────
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('production', [DashboardController::class, 'production']);
            Route::get('stock', [DashboardController::class, 'stock']);
            Route::get('commercial', [DashboardController::class, 'commercial']);
            Route::get('finance', [DashboardController::class, 'finance']);
        });

        // ── Organisation ──────────────────────────────────
        Route::prefix('organisation')
            ->middleware('role:admin')
            ->group(function () {
                Route::apiResource('locations', LocationController::class);
                Route::apiResource('roles', RoleController::class);
                Route::apiResource('utilisateurs', UtilisateurController::class);
                Route::patch(
                    'utilisateurs/{utilisateur}/toggle-actif',
                    [UtilisateurController::class, 'toggleActif']
                );
            });

        // ── RH ────────────────────────────────────────────
        Route::prefix('rh')
            ->middleware('role:admin')
            ->group(function () {
                Route::apiResource('postes', PosteController::class);
                Route::apiResource('employes', EmployeController::class);
            });

        // ── Catalogue ─────────────────────────────────────
        Route::prefix('catalogue')
            ->middleware('role:admin,responsable_prod,operateur_saisie,commercial,responsable_achat')
            ->group(function () {
                Route::apiResource('categories', CategorieProduitController::class);
                Route::apiResource('matieres-premieres', MatierePremierController::class);
                Route::apiResource('produits', ProduitController::class);
                Route::apiResource('produits.classements', ClassementProduitController::class)
                    ->shallow();
            });

        // ── Stock ─────────────────────────────────────────
        Route::prefix('stocks')
            ->middleware('role:admin,responsable_prod,operateur_saisie,commercial,logistique,responsable_achat')
            ->group(function () {
                Route::get('/', [StockController::class, 'index']);
                Route::get('/ruptures', [StockController::class, 'ruptures']);
                Route::get('/par-location/{id}', [StockController::class, 'parLocation']);
                Route::get('/par-produit/{id}', [StockController::class, 'parProduit']);
                Route::get('/par-matiere/{id}', [StockController::class, 'parMatiere']);
                Route::get('mouvements', [MouvementStockController::class, 'index']);
                Route::get('mouvements/{id}', [MouvementStockController::class, 'show']);
            });

        // ── Commercial ────────────────────────────────────
        Route::prefix('commercial')->group(function () {

            Route::middleware('role:admin,commercial,finance')->group(function () {
                Route::apiResource('clients', ClientController::class);
                Route::get('clients/{client}/encours', [ClientController::class, 'encours']);
                Route::get('clients/{client}/historique', [ClientController::class, 'historique']);
            });

            Route::middleware('role:admin,commercial,responsable_achat')->group(function () {
                Route::apiResource('fournisseurs', FournisseurController::class);
            });

            Route::middleware('role:admin,commercial')->group(function () {
                Route::apiResource('contrats', ContratController::class);
                Route::apiResource('contrats.lignes', \App\Http\Controllers\Api\Commercial\LigneContratController::class)
                    ->shallow();

                Route::apiResource('ventes-directes', VenteDirecteController::class);
                Route::post('ventes-directes/{vente}/valider', [VenteDirecteController::class, 'valider']);
                Route::apiResource('ventes-directes.lignes', \App\Http\Controllers\Api\Commercial\LigneVenteDirecteController::class)
                    ->shallow();
            });

            Route::middleware('role:admin,commercial,finance,logistique,responsable_achat')->group(function () {
                Route::apiResource('commandes', CommandeController::class);
                Route::post('commandes/{commande}/duplicate', [CommandeController::class, 'duplicate']);
                Route::apiResource('commandes.lignes', \App\Http\Controllers\Api\Commercial\LigneCommandeController::class)
                    ->shallow();
            });
        });

        // ── Achats ────────────────────────────────────────
        Route::prefix('achats')
            ->middleware('role:admin,responsable_achat')
            ->group(function () {
                Route::apiResource('demandes', DemandeAchatController::class);
                Route::post('demandes/{demande}/soumettre', [DemandeAchatController::class, 'soumettre']);
                Route::post('demandes/{demande}/approuver', [DemandeAchatController::class, 'approuver']);
                Route::post('demandes/{demande}/rejeter', [DemandeAchatController::class, 'rejeter']);

                Route::apiResource('bons-reception', JournalAchatController::class);
                Route::post('bons-reception/{br}/valider', [JournalAchatController::class, 'valider']);
                Route::apiResource('bons-reception.lignes', \App\Http\Controllers\Api\Achat\LigneAchatController::class)
                    ->shallow();
            });

        // ── Production ────────────────────────────────────
        Route::prefix('production')
            ->middleware('role:admin,responsable_prod,operateur_saisie')
            ->group(function () {
                Route::apiResource('bons-production', BonProductionController::class);
                Route::post('bons-production/{bp}/cloture', [BonProductionController::class, 'cloture']);
                Route::post('bons-production/{bp}/annuler', [BonProductionController::class, 'annuler']);

                Route::apiResource('bons-production.sessions', BpSessionController::class)
                    ->shallow();
                Route::post('sessions/{session}/valider', [BpSessionController::class, 'valider']);
                Route::post('sessions/{session}/matieres', [BpSessionController::class, 'ajouterMatiere']);
                Route::post('sessions/{session}/obtenus', [BpSessionController::class, 'ajouterObtenu']);
                Route::post('sessions/{session}/employes', [BpSessionController::class, 'ajouterEmploye']);
                Route::post('sessions/{session}/evenements', [BpSessionController::class, 'ajouterEvenement']);
            });

        // ── Recyclage ─────────────────────────────────────
        Route::prefix('recyclage')
            ->middleware('role:admin,responsable_prod,operateur_saisie')
            ->group(function () {
                Route::apiResource('bons-transformation', BonTransformationController::class);
                Route::post('bons-transformation/{bt}/cloture', [BonTransformationController::class, 'cloture']);

                Route::apiResource('bons-transformation.sessions', BtSessionController::class)
                    ->shallow();
                Route::post('bt-sessions/{session}/valider', [BtSessionController::class, 'valider']);
                Route::post('bt-sessions/{session}/matieres', [BtSessionController::class, 'ajouterMatiere']);
                Route::post('bt-sessions/{session}/employes', [BtSessionController::class, 'ajouterEmploye']);
                Route::post('bt-sessions/{session}/evenements', [BtSessionController::class, 'ajouterEvenement']);
            });

        // ── Logistique ────────────────────────────────────
        Route::prefix('logistique')
            ->middleware('role:admin,logistique')
            ->group(function () {
                Route::apiResource('livraisons', LivraisonController::class);
                Route::post('livraisons/{livraison}/confirmer', [LivraisonController::class, 'confirmer']);
                Route::apiResource('livraisons.lignes', \App\Http\Controllers\Api\Logistique\LigneLivraisonController::class)
                    ->shallow();

                Route::apiResource('bons-sortie', BonSortieController::class);
                Route::post('bons-sortie/{bon}/valider', [BonSortieController::class, 'valider']);
                Route::apiResource('bons-sortie.lignes', \App\Http\Controllers\Api\Logistique\LigneSortieController::class)
                    ->shallow();
            });

        // ── Finance ───────────────────────────────────────
        Route::prefix('finance')
            ->middleware('role:admin,finance')
            ->group(function () {
                Route::apiResource('factures', FactureController::class);
                Route::post('factures/{facture}/payer', [FactureController::class, 'payer']);
                Route::post('factures/{facture}/annuler', [FactureController::class, 'annuler']);
                Route::get('factures/retards', [FactureController::class, 'enRetard']);
            });
    });
});