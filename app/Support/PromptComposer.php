<?php

namespace App\Support;

use App\Models\Outfit;
use Illuminate\Support\Collection;

/**
 * Ghép prompt LAYOUT (Mẫu ảnh outfit đã duyệt) + danh sách outfit thành một prompt hoàn chỉnh.
 * Không còn "prompt dùng chung": phần khung ảnh / người mẫu / chữ nằm trong prompt của layout (có chỗ {HOOK}).
 */
class PromptComposer
{
    /** Hook mặc định khi ghép tay trên web (worker luôn truyền hook riêng). */
    public const DEFAULT_HOOK = 'Mặc xinh mỗi ngày';

    public static function defaultFix(): string
    {
        return trim(file_get_contents(resource_path('prompts/sua-nhanh.txt')));
    }

    /** Câu mở đầu, đổi theo số outfit. Kiểu ảnh do prompt của layout quyết định. */
    public static function header(int $count): string
    {
        if ($count <= 1) {
            return 'Tạo MỘT ảnh cho outfit trong phần OUTFIT bên dưới, làm đúng theo các yêu cầu sau.';
        }

        // MỘT ảnh duy nhất chứa đủ N outfit (layout chia khung/cột). Câu cũ "Tạo N ảnh RIÊNG BIỆT" mâu thuẫn với
        // layout nhiều outfit -> ChatGPT chỉ vẽ 1 outfit hoặc lặp outfit 1 ở mọi cột (lô 896, 2026-11-16).
        return "Tạo MỘT ảnh DUY NHẤT gồm đủ {$count} outfit trong DANH SÁCH OUTFIT bên dưới, mỗi outfit một khung theo bố cục bên dưới, "
            ."đúng thứ tự (khung 1 = OUTFIT 1, khung 2 = OUTFIT 2, ...). {$count} khung là {$count} bộ KHÁC NHAU rõ ràng, "
            .'KHÔNG lặp lại một outfit ở nhiều khung, không lẫn món của nhau; cùng một người mẫu ở mọi khung. Làm đúng theo các yêu cầu sau.';
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

    /** Prompt layout với {HOOK} đã thay bằng hook (dấu " trong hook đổi thành ' để không vỡ câu trích). */
    public static function layoutText(string $layoutPrompt, ?string $hook = null): string
    {
        $hook = str_replace('"', "'", trim((string) $hook) ?: self::DEFAULT_HOOK);

        return str_replace('{HOOK}', $hook, trim($layoutPrompt));
    }

    /** @param  Collection<int, Outfit>  $outfits */
    public static function compose(Collection $outfits, string $layoutPrompt, ?string $hook = null): string
    {
        $count = $outfits->count();
        $list = $outfits->values()
            ->map(fn (Outfit $o, int $i) => self::outfitBlock($o, $count > 1 ? $i + 1 : null))
            ->implode("\n\n");

        return self::header($count)."\n\n"
            .self::layoutText($layoutPrompt, $hook)."\n\n"
            .($count > 1 ? "DANH SÁCH OUTFIT:\n\n" : '')
            .$list;
    }
}
