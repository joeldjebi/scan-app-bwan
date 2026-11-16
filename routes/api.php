<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\Partner\EventController as PartnerEventController;
use App\Http\Controllers\Api\Partner\ScanController as PartnerScanController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Agents et chefs : l'événement est déduit du pass scanné.
        Route::post('verify', [ScanController::class, 'verify']);
        Route::post('scans', [ScanController::class, 'store']);
        Route::post('scans/batch', [ScanController::class, 'batch']);
        Route::get('scans/history', [ScanController::class, 'history']);
        Route::get('sync', SyncController::class);

        // Chefs (et admins) : supervision d'un événement.
        Route::get('events', [EventController::class, 'index']);
        Route::prefix('events/{event}')->middleware('can:supervise,event')->group(function () {
            Route::get('stats', [EventController::class, 'stats']);
            Route::get('scans', [EventController::class, 'scans']);
        });
    });
});

// API partenaire : entités extérieures authentifiées par clé API (voir docs/openapi-partner.yaml).
Route::prefix('partner/v1')->middleware('throttle:partner-api')->group(function () {
    Route::get('events', [PartnerEventController::class, 'index'])->middleware('partner');
    Route::post('verify', [PartnerScanController::class, 'verify'])->middleware('partner:scans:verify');
    Route::post('scans', [PartnerScanController::class, 'store'])->middleware('partner:scans:write');
    Route::get('scans', [PartnerScanController::class, 'index'])->middleware('partner:scans:read');
    Route::get('scans/{scan}', [PartnerScanController::class, 'show'])->middleware('partner:scans:read');
});
