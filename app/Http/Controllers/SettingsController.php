<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Trang Cài đặt: chỉ còn API token của tài khoản. */
class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('settings.edit', [
            'hasToken' => filled($user->api_token),
            'newToken' => session('api_token'),
        ]);
    }

    public function issueToken(Request $request)
    {
        $token = $request->user()->issueApiToken();

        return back()->with('api_token', $token)->with('status', 'Đã tạo API token mới. Token cũ (nếu có) không còn dùng được.');
    }
}
