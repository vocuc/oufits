@extends('layouts.app')

@section('title', 'Đăng nhập · OOTD Prompt')

@section('content')
    <div class="auth">
        <h1>Đăng nhập</h1>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('login') }}" class="form">
            @csrf
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </label>
            <label>Mật khẩu
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="check"><input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập</label>
            <button class="btn btn--primary">Đăng nhập</button>
            <small class="muted">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></small>
        </form>
    </div>
@endsection
