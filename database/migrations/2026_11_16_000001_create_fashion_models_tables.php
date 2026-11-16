<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Người mẫu (/models): chủ shop tải ảnh người mẫu + ghi chú; Claude đọc ảnh + ghi chú rồi viết prompt mô tả
 * người mẫu (API PUT /api/models/{id}/prompt) để dùng khi cho ChatGPT vẽ ảnh outfit.
 * content_updated_at = lần cuối ghi chú / ảnh đổi; prompt_updated_at = lần cuối prompt được ghi.
 * content_updated_at > prompt_updated_at (hoặc chưa có prompt) -> "cần viết lại prompt".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fashion_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('notes')->nullable();
            $table->longText('prompt')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamp('prompt_updated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('fashion_model_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fashion_model_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fashion_model_photos');
        Schema::dropIfExists('fashion_models');
    }
};
