<?php

namespace App\Http\Controllers;

use App\Models\OutfitLayout;

/** Trang "Mẫu ảnh outfit": layout ảnh outfit ChatGPT vẽ. Chủ shop duyệt / bỏ + ghi yêu cầu; worker chỉ dùng mẫu đã duyệt. */
class OutfitLayoutController extends CoverTemplateController
{
    protected string $model = OutfitLayout::class;

    protected function page(): array
    {
        return [
            'title' => 'Mẫu ảnh outfit',
            'intro' => 'Các layout ảnh outfit cho lô phối đồ A51 (ChatGPT vẽ theo prompt của layout). Worker chỉ dùng layout đã duyệt và đọc cột "Yêu cầu" trước khi làm. Layout chưa có ảnh mẫu: worker sẽ vẽ thử ở lần chạy tới rồi đưa ảnh lên đây.',
            'show_prompt' => true,
        ];
    }
}
