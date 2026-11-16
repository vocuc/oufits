<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chủ outfit (owner) chia sẻ TOÀN BỘ outfit của mình (cả riêng tư) cho tài khoản khác (viewer), chỉ xem. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['owner_id', 'viewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfit_shares');
    }
};
