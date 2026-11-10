<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** "Authorization: Bearer <token>" -> đăng nhập đúng tài khoản sở hữu token (chỉ cho request này). */
class EnsureApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::findByApiToken((string) $request->bearerToken());

        if (! $user) {
            return response()->json(['message' => 'Token không hợp lệ. Tạo token ở trang "Cài đặt" trên web.'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
