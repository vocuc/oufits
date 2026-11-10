<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ảnh bìa của outfit, lưu trên disk "local" (riêng tư), xem qua route outfits.cover. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('items');
        });
    }

    public function down(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
