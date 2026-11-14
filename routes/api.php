<?php

use App\Http\Controllers\Api\OutfitApiController;
use App\Http\Controllers\Api\StylingIdeaApiController;
use App\Http\Controllers\Api\StylingRuleApiController;
use App\Http\Middleware\EnsureApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureApiToken::class)->group(function () {
    Route::get('outfits', [OutfitApiController::class, 'index']);
    Route::post('outfits', [OutfitApiController::class, 'store']);
    Route::post('outfits/{outfit}/cover', [OutfitApiController::class, 'cover']);
    Route::put('outfits/{outfit}/hashtags', [OutfitApiController::class, 'hashtags']);
    Route::get('compose', [OutfitApiController::class, 'compose']);

    // Kho kiến thức phối đồ Claude học được (D:\FlowKit\a51-skills\outfit-video\thiet-ke-kien-thuc.txt)
    Route::get('styling-rules', [StylingRuleApiController::class, 'index']);
    Route::put('styling-rules', [StylingRuleApiController::class, 'upsert']);
    Route::delete('styling-rules/{id}', [StylingRuleApiController::class, 'destroy'])->whereNumber('id');

    Route::get('styling-ideas', [StylingIdeaApiController::class, 'index']);
    Route::get('styling-ideas/keys', [StylingIdeaApiController::class, 'keys']);
    Route::put('styling-ideas', [StylingIdeaApiController::class, 'upsert']);
    Route::post('styling-ideas/{id}/used', [StylingIdeaApiController::class, 'used'])->whereNumber('id');
    Route::delete('styling-ideas/{id}', [StylingIdeaApiController::class, 'destroy'])->whereNumber('id');
});
