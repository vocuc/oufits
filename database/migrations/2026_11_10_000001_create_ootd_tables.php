<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfits', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('style')->nullable();
            $table->string('source_title')->nullable();
            $table->string('source_author')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_position')->nullable();
            $table->longText('prompt');
            $table->text('fix_prompt')->nullable();
            $table->timestamps();
        });

        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('hashtag_outfit', function (Blueprint $table) {
            $table->foreignId('outfit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
            $table->primary(['outfit_id', 'hashtag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hashtag_outfit');
        Schema::dropIfExists('hashtags');
        Schema::dropIfExists('outfits');
    }
};
