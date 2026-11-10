<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Outfit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Nhập mật khẩu.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('outfits.index'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Nhập tên.',
            'email.required' => 'Nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã có tài khoản.',
            'password.required' => 'Nhập mật khẩu.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'password.min' => 'Mật khẩu tối thiểu 8 ký tự.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $isFirst = ! User::exists();
            $user = User::create($data);

            // Outfit có từ trước khi có tài khoản thuộc về tài khoản đầu tiên.
            if ($isFirst) {
                Outfit::whereNull('user_id')->update(['user_id' => $user->id]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('outfits.index')->with('status', 'Đã tạo tài khoản.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
