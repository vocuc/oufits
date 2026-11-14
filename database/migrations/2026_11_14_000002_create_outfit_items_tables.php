<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hiểu từng món đồ + độ hợp giữa các món (D:\FlowKit\a51-skills\outfit-video\thiet-ke-item.txt):
 * - outfit_items: mỗi dòng "- ..." trong items của outfit tách thành 1 món có thuộc tính chuẩn hoá (slug)
 * - item_pairs: độ hợp từng cặp ở nhiều cấp, tính lại toàn bộ bởi POST /api/styling/learn
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outfit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('slot', 20); // top | outer | dress | bottom | socks | shoes | bag | hat | jewelry | belt | scarf | hair | other
            $table->string('category', 60);
            $table->string('color', 40)->nullable();
            $table->string('color2', 40)->nullable();
            $table->string('pattern', 40)->nullable();
            $table->string('material', 40)->nullable();
            $table->string('fit', 40)->nullable();
            $table->string('length', 40)->nullable();
            $table->string('neckline', 40)->nullable();
            $table->string('sleeve', 40)->nullable();
            $table->json('details')->nullable();
            $table->json('wear')->nullable();
            $table->text('raw')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'slot']);
            $table->index(['outfit_id', 'position']);
        });

        Schema::create('item_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20); // cat | cat_color | color | material | pattern | style_full | cat_shoe | color_shoe | cat_ctx
            $table->string('a', 160);
            $table->string('b', 160);
            $table->unsignedInteger('count');
            $table->decimal('expected', 10, 3);
            $table->decimal('lift', 10, 3);
            $table->decimal('score', 5, 3); // -1..+1
            $table->json('examples')->nullable(); // outfit_id
            $table->timestamps();

            $table->unique(['user_id', 'level', 'a', 'b']);
            $table->index(['user_id', 'level', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_pairs');
        Schema::dropIfExists('outfit_items');
    }
};
