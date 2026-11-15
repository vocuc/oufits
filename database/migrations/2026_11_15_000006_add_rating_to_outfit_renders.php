<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chủ shop chấm ảnh phối đồ 1-10 trên Thư viện ảnh (điểm càng cao outfit càng hợp shop).
 * StylingEngine học điểm này thành cấp fb / fb_shoe của item_pairs (yêu cầu người dùng 2026-11-15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_renders', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('a51_output_id');
            $table->timestamp('rated_at')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('outfit_renders', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rated_at']);
        });
    }
};
