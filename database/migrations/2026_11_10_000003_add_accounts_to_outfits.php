<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mỗi tài khoản có prompt dùng chung, câu sửa nhanh, API token riêng và các outfit của mình.
 * Outfit cũ (user_id = null) được giao cho tài khoản đăng ký đầu tiên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('common_prompt')->nullable();
            $table->text('fix_prompt')->nullable();
            $table->string('api_token', 64)->nullable()->unique();
        });

        Schema::table('outfits', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token']);
            $table->dropColumn(['common_prompt', 'fix_prompt', 'api_token']);
        });
    }
};
