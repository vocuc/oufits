<?php

namespace App\Http\Controllers;

use App\Support\PromptComposer;
use Illuminate\Http\Request;

/** Prompt dùng chung, câu sửa nhanh và API token của tài khoản đang đăng nhập. */
class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('settings.edit', [
            'common' => $user->commonPrompt(),
            'fix' => $user->fixPrompt(),
            'hasToken' => filled($user->api_token),
            'newToken' => session('api_token'),
        ]);
    }

    public function updatePrompts(Request $request)
    {
        $data = $request->validate([
            'common_prompt' => ['required', 'string', 'max:20000'],
            'fix_prompt' => ['nullable', 'string', 'max:20000'],
        ], [
            'common_prompt.required' => 'Prompt dùng chung không được để trống.',
        ]);

        // Giống mặc định thì lưu null để sau này đổi mặc định vẫn được cập nhật theo.
        $request->user()->update([
            'common_prompt' => trim($data['common_prompt']) === PromptComposer::defaultCommon() ? null : $data['common_prompt'],
            'fix_prompt' => trim((string) $data['fix_prompt']) === PromptComposer::defaultFix() ? null : $data['fix_prompt'],
        ]);

        return back()->with('status', 'Đã lưu prompt dùng chung.');
    }

    public function resetPrompts(Request $request)
    {
        $request->user()->update(['common_prompt' => null, 'fix_prompt' => null]);

        return back()->with('status', 'Đã khôi phục prompt mặc định.');
    }

    public function issueToken(Request $request)
    {
        $token = $request->user()->issueApiToken();

        return back()->with('api_token', $token)->with('status', 'Đã tạo API token mới. Token cũ (nếu có) không còn dùng được.');
    }
}
