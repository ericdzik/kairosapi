<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ProduitController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\TontineController;
use App\Http\Controllers\API\CotisationController;
use App\Http\Controllers\API\PointageController;
use App\Http\Controllers\API\LivraisonController;
use App\Http\Controllers\API\VenteController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ExportController;
use App\Http\Controllers\API\AuditLogController;

// AUTH (public)
Route::prefix('auth')->group(function () {
    Route::post('login',   [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// ROUTES PROTÉGÉES
Route::middleware('auth:sanctum')->group(function () {

    Route::post('auth/logout',          [AuthController::class, 'logout']);
    Route::post('auth/change-password', [AuthController::class, 'changePassword']);

    // UTILISATEURS
    Route::middleware('role:directeur,secretaire')->group(function () {
        Route::post('users', [UserController::class, 'store']);
    });
    Route::middleware('role:directeur')->group(function () {
        Route::get('users',              [UserController::class, 'index']);
        Route::get('users/{user}',       [UserController::class, 'show']);
        Route::put('users/{user}',       [UserController::class, 'update']);
        Route::delete('users/{user}',    [UserController::class, 'destroy']);
        Route::patch('users/{user}/toggle', [UserController::class, 'toggle']);
    });
    Route::middleware('role:directeur,secretaire')->group(function () {
        Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    });

    // PRODUITS — directeur et secrétaire uniquement
    Route::get('produits',                    [ProduitController::class, 'index']);
    Route::get('produits/{produit}',          [ProduitController::class, 'show']);
    Route::middleware('role:directeur,secretaire')->group(function () {
        Route::post('produits',               [ProduitController::class, 'store']);
        Route::put('produits/{produit}',      [ProduitController::class, 'update']);
        Route::delete('produits/{produit}',   [ProduitController::class, 'destroy']);
        Route::patch('produits/{produit}/toggle', [ProduitController::class, 'toggle']);
    });

    // CLIENTS (infos de base uniquement)
    Route::apiResource('clients', ClientController::class);

    // TONTINES
    Route::get('tontines',                          [TontineController::class, 'index']);
    Route::post('tontines',                         [TontineController::class, 'store']);
    Route::get('tontines/{tontine}',                [TontineController::class, 'show']);
    Route::patch('tontines/{tontine}/suspendre',    [TontineController::class, 'suspendre']);
    Route::get('clients/{client}/tontines',         [TontineController::class, 'parClient']);

    // COTISATIONS
    Route::get('cotisations',                               [CotisationController::class, 'index']);
    Route::post('cotisations',                              [CotisationController::class, 'store']);
    Route::get('cotisations/jour',                          [CotisationController::class, 'jour']);
    Route::patch('cotisations/valider-lot',                 [CotisationController::class, 'validerLot']);
    Route::get('tontines/{tontine}/cotisations',            [CotisationController::class, 'parTontine']);
    Route::patch('cotisations/{cotisation}/valider',        [CotisationController::class, 'valider']);
    Route::patch('cotisations/{cotisation}/rejeter',        [CotisationController::class, 'rejeter']);
    Route::post('cotisations/{cotisation}/annuler',         [CotisationController::class, 'annuler']);

    // POINTAGE
    Route::get('tontines/{tontine}/pointage',    [PointageController::class, 'grille']);
    Route::get('tontines/{tontine}/progression', [PointageController::class, 'progression']);

    // LIVRAISONS
    Route::get('livraisons/pret',              [LivraisonController::class, 'pret']);
    Route::post('livraisons',                  [LivraisonController::class, 'store']);
    Route::get('livraisons/{livraison}/bon',   [LivraisonController::class, 'bon']);

    // VENTES DIRECTES
    Route::get('ventes',                    [VenteController::class, 'index']);
    Route::post('ventes',                   [VenteController::class, 'store']);
    Route::get('ventes/jour',               [VenteController::class, 'jour']);
    Route::get('ventes/{vente}',            [VenteController::class, 'show']);
    Route::patch('ventes/{vente}/valider',  [VenteController::class, 'valider']);
    Route::patch('ventes/{vente}/annuler',  [VenteController::class, 'annuler']);

    // DASHBOARDS
    Route::get('dashboard/directeur',   [DashboardController::class, 'directeur']);
    Route::get('dashboard/comptabilite',[DashboardController::class, 'comptabilite']);
    Route::get('dashboard/commercial',  [DashboardController::class, 'commercial']);
    Route::get('dashboard/controleur',  [DashboardController::class, 'controleur']);
    Route::get('dashboard/stats',       [DashboardController::class, 'stats']);
    Route::get('commerciaux/stats',     [DashboardController::class, 'commerciauxStats']);

    // NOTIFICATIONS
    Route::get('notifications',                          [NotificationController::class, 'index']);
    Route::patch('notifications/lire-tout',              [NotificationController::class, 'lireTout']);
    Route::patch('notifications/{notification}/lire',    [NotificationController::class, 'lire']);

    // EXPORTS
    Route::get('exports/rapport-journalier',    [ExportController::class, 'rapportJournalier']);
    Route::get('exports/mises',                 [ExportController::class, 'mises']);
    Route::get('exports/pointage/{tontine}',    [ExportController::class, 'pointage']);

    // AUDIT LOGS
    Route::middleware('role:directeur,controleur')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });
});
