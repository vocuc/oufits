<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/** Chia sẻ toàn bộ outfit của mình cho tài khoản OOTD khác (trang Cài đặt). */
class OutfitShareController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Nhập email tài khoản OOTD muốn chia sẻ.',
            'email.email' => 'Email không hợp lệ.',
        ]);
        $me = $request->user();
        $viewer = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();

        if (! $viewer) {
            return back()->withErrors(['email' => 'Không có tài khoản OOTD nào dùng email này.'])->withInput();
        }
        if ($viewer->id === $me->id) {
            return back()->withErrors(['email' => 'Không cần chia sẻ cho chính mình.'])->withInput();
        }

        $me->sharedWith()->syncWithoutDetaching([$viewer->id]);

        return back()->with('status', "Đã chia sẻ toàn bộ outfit cho {$viewer->name}.");
    }

    public function destroy(Request $request, User $viewer)
    {
        $request->user()->sharedWith()->detach($viewer->id);

        return back()->with('status', "Đã thôi chia sẻ outfit với {$viewer->name}.");
    }
}
