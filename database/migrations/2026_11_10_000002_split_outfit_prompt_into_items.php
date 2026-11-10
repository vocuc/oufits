<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outfit chỉ lưu item + cách phối (cột items), không lưu câu lệnh hoàn chỉnh.
 * Phần dùng chung (giày, người mẫu, tạo dáng, bối cảnh, sửa nhanh) nằm ở resources/prompts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->longText('items')->nullable()->after('source_position');
        });

        DB::table('outfits')->orderBy('id')->each(function ($row) {
            DB::table('outfits')->where('id', $row->id)->update([
                'items' => self::extractItems((string) $row->prompt),
            ]);
        });

        Schema::table('outfits', function (Blueprint $table) {
            $table->dropColumn(['prompt', 'fix_prompt']);
        });
    }

    public function down(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->longText('prompt')->nullable();
            $table->text('fix_prompt')->nullable();
        });

        DB::table('outfits')->update(['prompt' => DB::raw('items')]);

        Schema::table('outfits', function (Blueprint $table) {
            $table->dropColumn('items');
        });
    }

    /** Lấy đoạn giữa "Trang phục và cách mặc:" và "Tạo dáng:"; không thấy thì giữ nguyên. */
    private static function extractItems(string $prompt): string
    {
        if (preg_match('/Trang phục và cách mặc:\s*(.+?)\s*(?:\n\s*Tạo dáng:|\z)/su', $prompt, $m)) {
            return trim($m[1]);
        }

        return trim($prompt);
    }
};
