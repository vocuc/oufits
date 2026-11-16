<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Trang Cài đặt: API token + chia sẻ outfit cho tài khoản khác. */
class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('settings.edit', [
            'hasToken' => filled($user->api_token),
            'newToken' => session('api_token'),
            'sharedWith' => $user->sharedWith()->get(['users.id', 'users.name', 'users.email']),
            'sharedOwners' => $user->sharedOwners()->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    public function issueToken(Request $request)
    {
        $token = $request->user()->issueApiToken();

        return back()->with('api_token', $token)->with('status', 'Đã tạo API token mới. Token cũ (nếu có) không còn dùng được.');
    }
}
