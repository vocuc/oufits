<?php

namespace App\Http\Controllers;

use App\Support\PromptComposer;
use Illuminate\Http\Request;

/** Câu sửa nhanh và API token của tài khoản. (Prompt khung ảnh nằm ở layout đã duyệt — trang Mẫu ảnh outfit.) */
class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('settings.edit', [
            'fix' => $user->fixPrompt(),
            'hasToken' => filled($user->api_token),
            'newToken' => session('api_token'),
        ]);
    }

    public function updatePrompts(Request $request)
    {
        $data = $request->validate(['fix_prompt' => ['nullable', 'string', 'max:20000']]);

        // Giống mặc định thì lưu null để sau này đổi mặc định vẫn được cập nhật theo.
        $request->user()->update([
            'fix_prompt' => trim((string) $data['fix_prompt']) === PromptComposer::defaultFix() ? null : $data['fix_prompt'],
        ]);

        return back()->with('status', 'Đã lưu câu sửa nhanh.');
    }

    public function resetPrompts(Request $request)
    {
        $request->user()->update(['fix_prompt' => null]);

        return back()->with('status', 'Đã khôi phục câu sửa nhanh mặc định.');
    }

    public function issueToken(Request $request)
    {
        $token = $request->user()->issueApiToken();

        return back()->with('api_token', $token)->with('status', 'Đã tạo API token mới. Token cũ (nếu có) không còn dùng được.');
    }
}
