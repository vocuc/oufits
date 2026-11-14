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

        return "Tạo {$count} ảnh RIÊNG BIỆT, mỗi outfit trong DANH SÁCH OUTFIT bên dưới là một ảnh, theo đúng thứ tự (ảnh 1 = outfit 1, ...). "
            .'Tất cả các ảnh dùng CÙNG một người mẫu và cùng đôi giày trong ảnh tôi tải lên; mỗi ảnh làm đúng theo các yêu cầu sau.';
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
