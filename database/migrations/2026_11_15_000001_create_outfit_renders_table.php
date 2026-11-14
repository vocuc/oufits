<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ảnh gốc (chưa in hook) ChatGPT tạo cho lô phối đồ A51 (D:\FlowKit\a51-skills\outfit-video\thiet-ke.txt mục 3).
 * Gắn với outfit đã dùng + sản phẩm A51; trang "Thư viện ảnh" (/renders) lọc theo outfit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outfit_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('thumb_path')->nullable();
            $table->unsignedBigInteger('a51_user_id')->nullable();
            $table->unsignedBigInteger('a51_product_id')->nullable();
            $table->string('a51_product_name', 255)->nullable();
            $table->unsignedBigInteger('a51_batch_id')->nullable();
            $table->unsignedBigInteger('a51_output_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'outfit_id']);
            $table->index(['user_id', 'a51_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfit_renders');
    }
};
