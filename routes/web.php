<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CoverTemplateController;
use App\Http\Controllers\GuidelineController;
use App\Http\Controllers\OutfitLayoutController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\RenderController;
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

    Route::get('renders', [RenderController::class, 'index'])->name('renders.index');
    Route::get('renders/{render}/image', [RenderController::class, 'image'])->name('renders.image');

    Route::get('cover-templates', [CoverTemplateController::class, 'index'])->name('cover-templates.index');
    Route::put('cover-templates/{template}', [CoverTemplateController::class, 'update'])->name('cover-templates.update');
    Route::get('cover-templates/{template}/image', [CoverTemplateController::class, 'image'])->name('cover-templates.image');

    Route::get('outfit-layouts', [OutfitLayoutController::class, 'index'])->name('outfit-layouts.index');
    Route::put('outfit-layouts/{template}', [OutfitLayoutController::class, 'update'])->name('outfit-layouts.update');
    Route::get('outfit-layouts/{template}/image', [OutfitLayoutController::class, 'image'])->name('outfit-layouts.image');

    Route::get('guidelines', [GuidelineController::class, 'index'])->name('guidelines.index');
    Route::post('guidelines', [GuidelineController::class, 'store'])->name('guidelines.store');
    Route::put('guidelines/{guideline}', [GuidelineController::class, 'update'])->name('guidelines.update');
    Route::delete('guidelines/{guideline}', [GuidelineController::class, 'destroy'])->name('guidelines.destroy');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('settings/token', [SettingsController::class, 'issueToken'])->name('settings.token');
});
