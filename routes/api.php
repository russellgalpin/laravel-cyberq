<?php

use App\Http\Controllers\Api\ControllerSettingsController;
use App\Http\Controllers\Api\CookComparisonsController;
use App\Http\Controllers\Api\CooksController;
use App\Http\Controllers\Api\CookTimelinesController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EndedCooksController;
use App\Http\Controllers\Api\GurusController;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\PasskeyChallengesController;
use App\Http\Controllers\Api\PasskeyTokensController;
use App\Http\Controllers\Api\TokensController;
use Illuminate\Support\Facades\Route;

Route::get('/instance', [InstanceController::class, 'show'])->middleware('throttle:60,1')->name('api.instance.show');

Route::middleware('throttle:6,1')->group(function () {
    Route::post('/tokens', [TokensController::class, 'store'])->name('api.tokens.store');
    Route::post('/passkey-challenges', [PasskeyChallengesController::class, 'store'])->name('api.passkeyChallenges.store');
    Route::post('/passkey-tokens', [PasskeyTokensController::class, 'store'])->name('api.passkeyTokens.store');
});

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::delete('/tokens/current', [TokensController::class, 'destroy'])->name('tokens.destroy');
    Route::get('/user', [CurrentUserController::class, 'show'])->name('user.show');

    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard.show');
    Route::get('/gurus', [GurusController::class, 'index'])->name('gurus.index');

    Route::apiResource('cooks', CooksController::class);
    Route::post('/cooks/{cook}/end', [EndedCooksController::class, 'store'])->name('cooks.end');
    Route::get('/cooks/{cook}/timeline', [CookTimelinesController::class, 'show'])->name('cooks.timeline');
    Route::get('/cook-comparisons', [CookComparisonsController::class, 'show'])->name('cookComparisons.show');

    Route::get('/controller-settings', [ControllerSettingsController::class, 'show'])->name('controllerSettings.show');
    Route::patch('/controller-settings', [ControllerSettingsController::class, 'update'])->name('controllerSettings.update');
});
