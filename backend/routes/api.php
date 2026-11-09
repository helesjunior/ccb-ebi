<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CentralController;
use App\Http\Controllers\Api\V1\ChurchController;
use App\Http\Controllers\Api\V1\EbiController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\SectorController;
use App\Http\Middleware\IdempotenciaMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('health', [HealthController::class, 'health'])->name('health');

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [MeController::class, 'me'])->name('me');

        // Organizacao - leitura autenticada
        Route::get('churches', [ChurchController::class, 'index'])->name('churches.index');
        Route::get('churches/{id}', [ChurchController::class, 'show'])->name('churches.show');
        Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
        Route::get('regions/{id}/sectors', [RegionController::class, 'sectors'])->name('regions.sectors');
        Route::get('sectors', [SectorController::class, 'index'])->name('sectors.index');
        Route::get('ebis', [EbiController::class, 'index'])->name('ebis.index');
        Route::get('central', [CentralController::class, 'show'])->name('central.show');

        // Organizacao - escrita sob o gate provisorio
        Route::middleware('can:gerir-organizacao')->group(function (): void {
            // Escritas de criacao/transferencia sob idempotencia (Idempotency-Key).
            Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
                Route::post('churches', [ChurchController::class, 'store'])->name('churches.store');
                Route::post('churches/{id}/transfers', [ChurchController::class, 'transfer'])->name('churches.transfer');
                Route::post('regions', [RegionController::class, 'store'])->name('regions.store');
                Route::post('sectors', [SectorController::class, 'store'])->name('sectors.store');
                Route::post('ebis', [EbiController::class, 'store'])->name('ebis.store');
            });

            Route::patch('churches/{id}', [ChurchController::class, 'update'])->name('churches.update');
            Route::patch('regions/{id}', [RegionController::class, 'update'])->name('regions.update');
            Route::patch('sectors/{id}', [SectorController::class, 'update'])->name('sectors.update');
            Route::patch('ebis/{id}', [EbiController::class, 'update'])->name('ebis.update');
        });
    });
});
