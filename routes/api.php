<?php

use App\Http\Controllers\Api\CoverTemplateApiController;
use App\Http\Controllers\Api\GuidelineApiController;
use App\Http\Controllers\Api\OutfitApiController;
use App\Http\Controllers\Api\OutfitLayoutApiController;
use App\Http\Controllers\Api\RenderApiController;
use App\Http\Controllers\Api\StylingIdeaApiController;
use App\Http\Controllers\Api\StylingItemApiController;
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

    // Ảnh gốc lô phối đồ A51
    Route::post('renders', [RenderApiController::class, 'store']);
    Route::get('renders/used-outfits', [RenderApiController::class, 'usedOutfits']);

    // Quy tắc chủ shop viết trên web (tạo outfit / ảnh bìa / đặt hook); API đọc / thêm / sửa (không xoá)
    Route::get('guidelines', [GuidelineApiController::class, 'index']);
    Route::post('guidelines', [GuidelineApiController::class, 'store']);
    Route::put('guidelines/{id}', [GuidelineApiController::class, 'update'])->whereNumber('id');

    // Mẫu ảnh bìa lô phối đồ (worker chỉ dùng mẫu đã duyệt)
    Route::get('cover-templates', [CoverTemplateApiController::class, 'index']);
    Route::post('cover-templates', [CoverTemplateApiController::class, 'upsert']);

    // Mẫu ảnh outfit (layout ChatGPT vẽ; worker chỉ dùng mẫu đã duyệt)
    Route::get('outfit-layouts', [OutfitLayoutApiController::class, 'index']);
    Route::post('outfit-layouts', [OutfitLayoutApiController::class, 'upsert']);

    // Món đồ + độ hợp giữa các món (D:\FlowKit\a51-skills\outfit-video\thiet-ke-item.txt)
    Route::put('outfits/{outfit}/items', [StylingItemApiController::class, 'putItems']);
    Route::get('outfit-items', [StylingItemApiController::class, 'index']);
    Route::post('styling/learn', [StylingItemApiController::class, 'learn']);
    Route::get('item-pairs', [StylingItemApiController::class, 'pairs']);
    Route::post('styling/score', [StylingItemApiController::class, 'score']);
    Route::post('styling/suggest', [StylingItemApiController::class, 'suggest']);
});
