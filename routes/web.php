<?php

use App\Http\Controllers\OutfitController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OutfitController::class, 'index'])->name('outfits.index');
Route::resource('outfits', OutfitController::class)->except('index');
