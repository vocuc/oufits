<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quy tắc chủ shop tự viết (trang "Quy tắc" /guidelines): tạo outfit, ảnh bìa, đặt hook.
 * Worker phối đồ đọc các quy tắc đang bật qua GET /api/guidelines trước khi làm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'category', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidelines');
    }
};
