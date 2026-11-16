@extends('layouts.app')

@section('title', 'Cài đặt · OOTD Prompt')

@section('content')
    <a href="{{ route('outfits.index') }}" class="back">← Quay lại</a>

    <div class="detail">
        <h1>Cài đặt</h1>
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

        <section class="block">
            <div class="block__head">
                <h2>Chia sẻ outfit</h2>
            </div>
            <div class="block__body">
                <p>Tài khoản được thêm vào sẽ xem được <b>toàn bộ</b> outfit của bạn (cả outfit riêng tư), trên web
                    (trang chủ, mục "Outfit của") và qua API <code>GET /api/outfits?owner={{ auth()->id() }}</code>.
                    Họ chỉ xem, không sửa / xoá được.</p>

                @if ($sharedWith->isNotEmpty())
                    <ul class="shares">
                        @foreach ($sharedWith as $viewer)
                            <li>
                                <span><b>{{ $viewer->name }}</b> <span class="muted">{{ $viewer->email }}</span></span>
                                <form method="post" action="{{ route('shares.destroy', $viewer) }}"
                                      onsubmit="return confirm('Thôi chia sẻ outfit với {{ addslashes($viewer->name) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn--danger">Thôi chia sẻ</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="muted">Chưa chia sẻ cho tài khoản nào.</p>
                @endif

                <form method="post" action="{{ route('shares.store') }}" class="share-form">
                    @csrf
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="Email tài khoản OOTD" required>
                    <button class="btn btn--primary">Thêm</button>
                </form>
                @error('email')
                    <div class="errors" style="margin-top:12px">{{ $message }}</div>
                @enderror

                @if ($sharedOwners->isNotEmpty())
                    <h3>Được chia sẻ cho bạn</h3>
                    <ul class="shares">
                        @foreach ($sharedOwners as $owner)
                            <li>
                                <span><b>{{ $owner->name }}</b> <span class="muted">{{ $owner->email }}</span></span>
                                <a class="btn" href="{{ route('outfits.index', ['owner' => $owner->id]) }}">Xem outfit</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
@endsection
