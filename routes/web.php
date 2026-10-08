<?php

use App\Http\Controllers\Web\ApiDocController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BrandController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\ExportController;
use App\Http\Controllers\Web\PassController;
use App\Http\Controllers\Web\PassTypeController;
use App\Http\Controllers\Web\PublicPassController;
use App\Http\Controllers\Web\ScanController;
use App\Http\Controllers\Web\StaffController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\UserScanController;
use Illuminate\Support\Facades\Route;

// Lien encodé dans le QR code : enregistrement du véhicule par l'usager.
Route::get('p/{token}', [PublicPassController::class, 'show'])->name('public.pass');
Route::post('p/{token}', [PublicPassController::class, 'register'])->middleware('throttle:10,1')->name('public.pass.register');

// Documentation Swagger de l'API mobile (désactivable via API_DOCS_ENABLED=false).
Route::middleware('api-docs')->group(function () {
    Route::get('docs/api', [ApiDocController::class, 'index'])->name('api-docs');
    Route::get('docs/api/openapi.yaml', [ApiDocController::class, 'spec'])->name('api-docs.spec');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    // Journal d'audit : propriétaire uniquement
    Route::middleware('can:owner')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/export', [AuditLogController::class, 'export'])->name('audit.export');
    });

    // Administration
    Route::middleware('can:admin')->group(function () {
        Route::get('events/code-suggestion', [EventController::class, 'suggestCode'])->name('events.code-suggestion');
        Route::resource('events', EventController::class)->except(['index', 'show']);
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('users/{user}/scans', [UserScanController::class, 'index'])->name('users.scans');

        Route::resource('brands', BrandController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('brands/{brand}/toggle', [BrandController::class, 'toggle'])->name('brands.toggle');

        Route::prefix('events/{event}')->name('events.')->group(function () {
            Route::scopeBindings()->group(function () {
                Route::post('types', [PassTypeController::class, 'store'])->name('types.store');
                Route::put('types/{passType}', [PassTypeController::class, 'update'])->name('types.update');
                Route::delete('types/{passType}', [PassTypeController::class, 'destroy'])->name('types.destroy');
                Route::post('types/{passType}/generate', [PassTypeController::class, 'generate'])->name('types.generate');

                Route::delete('passes', [PassController::class, 'bulkDestroy'])->name('passes.bulk-destroy');
                Route::delete('passes/{pass}', [PassController::class, 'destroy'])->name('passes.destroy');
                Route::delete('scans', [ScanController::class, 'bulkDestroy'])->name('scans.bulk-destroy');
                Route::delete('scans/{scan}', [ScanController::class, 'destroy'])->name('scans.destroy');
            });

            Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
            Route::put('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
            Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');

            Route::get('export/qrcodes/lots', [ExportController::class, 'qrCodesPlan'])->name('export.qrcodes.plan');
            Route::get('export/qrcodes', [ExportController::class, 'qrCodes'])->name('export.qrcodes');
        });
    });

    // Supervision : admin ou chef agent de l'événement
    Route::prefix('events/{event}')->name('events.')->middleware('can:supervise,event')->scopeBindings()->group(function () {
        Route::get('/', [EventController::class, 'show'])->name('show');
        Route::get('passes', [PassController::class, 'index'])->name('passes.index');
        Route::get('passes/{pass}', [PassController::class, 'show'])->name('passes.show');
        Route::get('passes/{pass}/qr.svg', [PassController::class, 'qr'])->name('passes.qr');
        Route::put('passes/{pass}/vehicle', [PassController::class, 'updateVehicle'])->name('passes.vehicle');
        Route::post('passes/{pass}/revoke', [PassController::class, 'revoke'])->name('passes.revoke');
        Route::post('passes/{pass}/restore', [PassController::class, 'restore'])->name('passes.restore');
        Route::post('passes/{pass}/reset', [PassController::class, 'reset'])->name('passes.reset');
        Route::get('scans', [ScanController::class, 'index'])->name('scans.index');
        Route::get('export/passes', [ExportController::class, 'passes'])->name('export.passes');
    });
});
