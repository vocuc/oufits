@extends('layouts.app')

@section('title', 'Đăng ký · OOTD Prompt')

@section('content')
    <div class="auth">
        <h1>Đăng ký</h1>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('register') }}" class="form">
            @csrf
            <label>Tên
                <input name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            </label>
            <label>Mật khẩu
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                <small class="muted">Tối thiểu 8 ký tự.</small>
            </label>
            <label>Nhập lại mật khẩu
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </label>
            <button class="btn btn--primary">Tạo tài khoản</button>
            <small class="muted">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></small>
        </form>
    </div>
@endsection
