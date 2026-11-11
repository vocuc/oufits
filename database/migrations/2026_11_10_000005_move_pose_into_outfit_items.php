<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tạo dáng chuyển từ prompt dùng chung vào nội dung từng outfit (cuối cột items).
 * Outfit đã có được thêm dáng cũ để ảnh tạo ra không đổi.
 */
return new class extends Migration
{
    private const POSE = "\n\nTạo dáng:\n"
        ."- Tay phía bên phải ảnh giơ chiếc iPhone màu trắng che kín toàn bộ khuôn mặt, điện thoại dọc, khuỷu tay khép sát người.\n"
        ."- Tay phía bên trái ảnh buông tự nhiên, cầm hoặc đặt lên túi / phụ kiện của outfit.\n"
        .'- Đứng thẳng, dáng nữ tính.';

    public function up(): void
    {
        DB::table('outfits')->where('items', 'not like', '%Tạo dáng:%')->orderBy('id')
            ->each(fn ($o) => DB::table('outfits')->where('id', $o->id)
                ->update(['items' => rtrim($o->items).self::POSE]));
    }

    public function down(): void
    {
        DB::table('outfits')->where('items', 'like', '%'.self::POSE)->orderBy('id')
            ->each(fn ($o) => DB::table('outfits')->where('id', $o->id)
                ->update(['items' => substr($o->items, 0, -strlen(self::POSE))]));
    }
};
