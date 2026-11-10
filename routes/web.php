<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [OutfitController::class, 'index'])->name('outfits.index');
    Route::get('compose', [OutfitController::class, 'compose'])->name('outfits.compose');
    Route::get('outfits/{outfit}/cover', [OutfitController::class, 'cover'])->name('outfits.cover');
    Route::resource('outfits', OutfitController::class)->except('index');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings/prompts', [SettingsController::class, 'updatePrompts'])->name('settings.prompts');
    Route::delete('settings/prompts', [SettingsController::class, 'resetPrompts'])->name('settings.prompts.reset');
    Route::post('settings/token', [SettingsController::class, 'issueToken'])->name('settings.token');
});
