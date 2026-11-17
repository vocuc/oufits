<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chủ đề lô phối đồ (/themes): worker chọn ngẫu nhiên 1 chủ đề đã duyệt cho mỗi lô, lọc outfit có ĐỦ hashtag
 * của chủ đề, tiêu đề lô = 1 câu hook ngẫu nhiên của chủ đề (thay cho Claude chọn chủ đề / viết hook, 2026-11-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('tags', 1000);          // hashtag bắt buộc, cách nhau dấu cách: "chanvaymini tatcaoco"
            $table->text('hooks');                 // mỗi dòng một câu hook (tiêu đề lô), có *từ khoá*
            $table->string('season', 20)->nullable(); // thudong | xuanhe | null = mọi mùa
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfit_themes');
    }
};
