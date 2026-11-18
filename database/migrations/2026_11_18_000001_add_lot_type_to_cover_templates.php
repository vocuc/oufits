<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mẫu ảnh bìa dùng cho loại lô nào (chủ shop chọn trên trang Mẫu ảnh bìa, 2026-11-18):
 * all = cả hai, product = lô có sản phẩm, no_product = lô không sản phẩm. Mẫu cũ = all (giữ như trước).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cover_templates', function (Blueprint $table) {
            $table->string('lot_type', 20)->default('all')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cover_templates', function (Blueprint $table) {
            $table->dropColumn('lot_type');
        });
    }
};
