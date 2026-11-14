@extends('layouts.app')

@section('title', 'Cài đặt · OOTD Prompt')

@section('content')
    <a href="{{ route('outfits.index') }}" class="back">← Quay lại</a>

    <div class="detail">
        <h1>Cài đặt</h1>
        <p class="hint">
            Prompt khung ảnh (giày, người mẫu, bố cục, chữ) nằm trong từng layout ở trang
            <a href="{{ route('outfit-layouts.index') }}">Mẫu ảnh outfit</a>; ảnh outfit chỉ tạo theo layout đã duyệt.
        </p>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('settings.prompts') }}" class="form">
            @csrf
            @method('PUT')

            <label>Câu sửa nhanh
                <textarea name="fix_prompt" rows="12">{{ old('fix_prompt', $fix) }}</textarea>
            </label>

            <div class="detail__actions">
                <button class="btn btn--primary">Lưu</button>
            </div>
        </form>

        <form method="post" action="{{ route('settings.prompts.reset') }}"
              onsubmit="return confirm('Khôi phục câu sửa nhanh về mặc định?')">
            @csrf
            @method('DELETE')
            <button class="btn">Khôi phục mặc định</button>
        </form>

        <section class="block">
            <div class="block__head">
                <h2>API token</h2>
            </div>
            <div class="block__body">
                <p>Dùng để thêm outfit vào tài khoản này qua API: <code>POST /api/outfits</code> với header
                    <code>Authorization: Bearer &lt;token&gt;</code>.</p>

                @if ($newToken)
                    <p><b>Token mới, chỉ hiện một lần. Copy và lưu lại ngay:</b></p>
                    <div class="block">
                        <div class="block__head">
                            <span class="muted">Token</span>
                            <button type="button" class="btn btn--primary" data-copy="token">Copy</button>
                        </div>
                        <pre id="token" class="token">{{ $newToken }}</pre>
                    </div>
                @elseif ($hasToken)
                    <p class="muted">Tài khoản đã có token. Token không được lưu dạng đọc lại được; nếu quên thì tạo token mới.</p>
                @else
                    <p class="muted">Chưa có token.</p>
                @endif

                <form method="post" action="{{ route('settings.token') }}"
                      @if ($hasToken) onsubmit="return confirm('Tạo token mới? Token cũ sẽ không dùng được nữa.')" @endif>
                    @csrf
                    <button class="btn">{{ $hasToken ? 'Tạo token mới' : 'Tạo token' }}</button>
                </form>
            </div>
        </section>
    </div>
@endsection
