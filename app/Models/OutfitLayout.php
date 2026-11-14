<?php

namespace App\Models;

/**
 * Mẫu ảnh outfit (layout ảnh ChatGPT vẽ cho từng outfit): ảnh mẫu + cách làm + prompt layout, trạng thái duyệt,
 * yêu cầu chủ shop. prompt = bản thay cho "prompt dùng chung" (khung ảnh, bố cục, chữ), có chỗ {HOOK};
 * prompt rỗng = dùng prompt dùng chung ở Cài đặt (layout lookbook gốc). Worker chỉ dùng mẫu đã duyệt.
 */
class OutfitLayout extends CoverTemplate
{
    public const ROUTE = 'outfit-layouts';

    public const NO_APPROVED = 'Không có layout ảnh outfit nào được duyệt. Vào trang "Mẫu ảnh outfit" để duyệt ít nhất một layout (có prompt).';

    protected $table = 'outfit_layouts';

    /** Layout đã duyệt + có prompt của tài khoản, theo thứ tự trên trang. */
    public static function usable(User $user)
    {
        return static::query()->where('user_id', $user->id)->where('status', self::APPROVED)
            ->whereNotNull('prompt')->where('prompt', '!=', '')->orderBy('sort')->orderBy('code');
    }

    /** Layout đã duyệt theo mã (không truyền mã = layout đầu tiên); null = không có layout dùng được. */
    public static function pick(User $user, ?string $code = null): ?self
    {
        return static::usable($user)->when($code, fn ($q) => $q->where('code', $code))->first();
    }
}
