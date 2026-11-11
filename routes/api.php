<?php

use App\Http\Controllers\Api\OutfitApiController;
use App\Http\Middleware\EnsureApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureApiToken::class)->group(function () {
    Route::get('outfits', [OutfitApiController::class, 'index']);
    Route::post('outfits', [OutfitApiController::class, 'store']);
    Route::post('outfits/{outfit}/cover', [OutfitApiController::class, 'cover']);
    Route::get('compose', [OutfitApiController::class, 'compose']);
});
