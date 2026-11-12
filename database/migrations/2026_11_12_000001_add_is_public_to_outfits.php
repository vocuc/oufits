<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chế độ hiển thị của outfit: công khai (mặc định) hoặc riêng tư. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
