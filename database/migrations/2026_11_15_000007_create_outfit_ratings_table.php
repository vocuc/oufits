<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Điểm chủ shop chấm (1-10) lưu bền theo outfit: xoá ảnh (outfit_renders) thì điểm vẫn còn (render_id -> null)
 * và StylingEngine vẫn học được (yêu cầu người dùng 2026-11-15: ảnh có thể bị xoá khi không dùng nữa).
 * Chép các điểm đã chấm trên outfit_renders.rating sang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outfit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('render_id')->nullable()->unique()->constrained('outfit_renders')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->unsignedBigInteger('a51_product_id')->nullable();
            $table->string('a51_product_name', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'outfit_id']);
        });

        $now = now();
        DB::table('outfit_renders')->whereNotNull('rating')->orderBy('id')->each(function ($r) use ($now) {
            DB::table('outfit_ratings')->insert([
                'user_id' => $r->user_id, 'outfit_id' => $r->outfit_id, 'render_id' => $r->id, 'rating' => $r->rating,
                'a51_product_id' => $r->a51_product_id, 'a51_product_name' => $r->a51_product_name,
                'created_at' => $r->rated_at ?? $now, 'updated_at' => $r->rated_at ?? $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfit_ratings');
    }
};
