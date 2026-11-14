<?php

namespace App\Models;

/**
 * Mẫu ảnh outfit (layout ảnh ChatGPT vẽ cho từng outfit): ảnh mẫu + cách làm + prompt layout, trạng thái duyệt,
 * yêu cầu chủ shop, fix_prompt = câu sửa nhanh riêng của layout. prompt = khung ảnh / bố cục / chữ (có chỗ {HOOK});
 * không còn prompt dùng chung. Layout chưa có prompt thì chưa dùng được. Worker chỉ dùng mẫu đã duyệt.
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

    /** Câu sửa nhanh của layout; trống thì dùng câu sửa nhanh chung của tài khoản (mặc định). */
    public function fixFor(User $user): string
    {
        return filled($this->fix_prompt) ? trim($this->fix_prompt) : $user->fixPrompt();
    }

    /** Layout đã duyệt theo mã (không truyền mã = layout đầu tiên); null = không có layout dùng được. */
    public static function pick(User $user, ?string $code = null): ?self
    {
        return static::usable($user)->when($code, fn ($q) => $q->where('code', $code))->first();
    }
}
