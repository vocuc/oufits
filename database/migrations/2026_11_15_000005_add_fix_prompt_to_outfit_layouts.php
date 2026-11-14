<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Câu sửa nhanh riêng từng layout ảnh outfit (thay câu sửa nhanh ở Cài đặt, đã bỏ khỏi trang). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfit_layouts', function (Blueprint $table) {
            $table->text('fix_prompt')->nullable()->after('prompt');
        });
    }

    public function down(): void
    {
        Schema::table('outfit_layouts', function (Blueprint $table) {
            $table->dropColumn('fix_prompt');
        });
    }
};
