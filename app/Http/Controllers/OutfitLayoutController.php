<?php

namespace App\Http\Controllers;

use App\Models\OutfitLayout;
use Illuminate\Http\Request;

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

    /** Chủ shop tự sửa prompt layout trên web (giữ nguyên trạng thái duyệt; không có câu sửa nhanh). */
    public function updatePrompt(Request $request, string $template)
    {
        $t = $this->find($request, $template);
        $data = $request->validate(['prompt' => ['required', 'string', 'max:30000']]);
        $t->fill(['prompt' => str_replace("\r\n", "\n", trim($data['prompt']))])->save();

        return redirect()->to(url()->previous(route(OutfitLayout::ROUTE.'.index')).'#mau-'.$t->id)
            ->with('status', "Đã lưu prompt layout {$t->code}.");
    }
}
