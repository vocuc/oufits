<?php

namespace App\Support;

use App\Models\Outfit;
use Illuminate\Support\Collection;

/** Ghép phần dùng chung + danh sách outfit thành một prompt hoàn chỉnh. */
class PromptComposer
{
    public static function defaultCommon(): string
    {
        return trim(file_get_contents(resource_path('prompts/chung.txt')));
    }

    public static function defaultFix(): string
    {
        return trim(file_get_contents(resource_path('prompts/sua-nhanh.txt')));
    }

    /** Câu mở đầu, đổi theo số outfit. */
    public static function header(int $count): string
    {
        if ($count <= 1) {
            return 'Tạo MỘT ảnh dọc tỉ lệ 4:5, selfie toàn thân trước gương, người mẫu mặc outfit trong phần OUTFIT bên dưới.';
        }

        return "Tạo MỘT ảnh dọc tỉ lệ 4:5 dạng ghép {$count} khung xếp đều nhau, mỗi khung là một ảnh selfie toàn thân trước gương của CÙNG một người mẫu, "
            .'mỗi khung mặc một outfit theo đúng thứ tự trong DANH SÁCH OUTFIT bên dưới (khung 1 = outfit 1, ...). '
            .'Tất cả các khung đều mang cùng đôi giày trong ảnh tôi tải lên.';
    }

    /** Một outfit dạng khối văn bản: tên, phong cách, item. */
    public static function outfitBlock(Outfit $outfit, ?int $number = null): string
    {
        $head = ($number ? "OUTFIT {$number}" : 'OUTFIT').' – '.$outfit->title;
        if ($outfit->style) {
            $head .= "\nPhong cách: ".$outfit->style;
        }

        return $head."\n".trim((string) $outfit->items);
    }

    /** @param  Collection<int, Outfit>  $outfits */
    public static function compose(Collection $outfits, string $common): string
    {
        $count = $outfits->count();
        $list = $outfits->values()
            ->map(fn (Outfit $o, int $i) => self::outfitBlock($o, $count > 1 ? $i + 1 : null))
            ->implode("\n\n");

        return self::header($count)."\n\n"
            .trim($common)."\n\n"
            .($count > 1 ? "DANH SÁCH OUTFIT:\n\n" : '')
            .$list;
    }
}
