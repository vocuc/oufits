<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CoverTemplateController;
use App\Http\Controllers\FashionModelController;
use App\Http\Controllers\GuidelineController;
use App\Http\Controllers\OutfitLayoutController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\OutfitShareController;
use App\Http\Controllers\OutfitThemeController;
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
    Route::put('renders/{render}/rating', [RenderController::class, 'rate'])->name('renders.rate');

    Route::get('cover-templates', [CoverTemplateController::class, 'index'])->name('cover-templates.index');
    Route::put('cover-templates/{template}', [CoverTemplateController::class, 'update'])->name('cover-templates.update');
    Route::delete('cover-templates/{template}', [CoverTemplateController::class, 'destroy'])->name('cover-templates.destroy');
    Route::get('cover-templates/{template}/image', [CoverTemplateController::class, 'image'])->name('cover-templates.image');

    Route::get('outfit-layouts', [OutfitLayoutController::class, 'index'])->name('outfit-layouts.index');
    Route::put('outfit-layouts/{template}', [OutfitLayoutController::class, 'update'])->name('outfit-layouts.update');
    Route::put('outfit-layouts/{template}/prompt', [OutfitLayoutController::class, 'updatePrompt'])->name('outfit-layouts.prompt');
    Route::delete('outfit-layouts/{template}', [OutfitLayoutController::class, 'destroy'])->name('outfit-layouts.destroy');
    Route::get('outfit-layouts/{template}/image', [OutfitLayoutController::class, 'image'])->name('outfit-layouts.image');

    Route::get('themes', [OutfitThemeController::class, 'index'])->name('themes.index');
    Route::post('themes', [OutfitThemeController::class, 'store'])->name('themes.store');
    Route::put('themes/{theme}', [OutfitThemeController::class, 'update'])->name('themes.update')->whereNumber('theme');
    Route::delete('themes/{theme}', [OutfitThemeController::class, 'destroy'])->name('themes.destroy')->whereNumber('theme');
    Route::post('themes/{theme}/hooks', [OutfitThemeController::class, 'storeHook'])->name('themes.hooks.store')->whereNumber('theme');
    Route::put('theme-hooks/{hook}', [OutfitThemeController::class, 'updateHook'])->name('theme-hooks.update')->whereNumber('hook');
    Route::delete('theme-hooks/{hook}', [OutfitThemeController::class, 'destroyHook'])->name('theme-hooks.destroy')->whereNumber('hook');

    Route::get('models', [FashionModelController::class, 'index'])->name('models.index');
    Route::post('models', [FashionModelController::class, 'store'])->name('models.store');
    Route::put('models/{model}', [FashionModelController::class, 'update'])->name('models.update')->whereNumber('model');
    Route::delete('models/{model}', [FashionModelController::class, 'destroy'])->name('models.destroy')->whereNumber('model');
    Route::put('models/{model}/prompt', [FashionModelController::class, 'updatePrompt'])->name('models.prompt')->whereNumber('model');
    Route::post('models/{model}/default', [FashionModelController::class, 'makeDefault'])->name('models.default')->whereNumber('model');
    Route::post('models/{model}/photos', [FashionModelController::class, 'addPhotos'])->name('models.photos.store')->whereNumber('model');
    Route::get('models/{model}/photos/{photo}', [FashionModelController::class, 'photo'])->name('models.photo')->whereNumber(['model', 'photo']);
    Route::delete('models/{model}/photos/{photo}', [FashionModelController::class, 'destroyPhoto'])->name('models.photos.destroy')->whereNumber(['model', 'photo']);

    Route::get('guidelines', [GuidelineController::class, 'index'])->name('guidelines.index');
    Route::post('guidelines', [GuidelineController::class, 'store'])->name('guidelines.store');
    Route::put('guidelines/{guideline}', [GuidelineController::class, 'update'])->name('guidelines.update');
    Route::delete('guidelines/{guideline}', [GuidelineController::class, 'destroy'])->name('guidelines.destroy');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('settings/token', [SettingsController::class, 'issueToken'])->name('settings.token');
    Route::post('settings/shares', [OutfitShareController::class, 'store'])->name('shares.store');
    Route::delete('settings/shares/{viewer}', [OutfitShareController::class, 'destroy'])->name('shares.destroy')->whereNumber('viewer');
});
