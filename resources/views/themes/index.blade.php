@extends('layouts.app')

@section('title', 'Chủ đề · OOTD Prompt')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Chủ đề</h1>
        <p class="muted" style="margin:0">
            Chủ đề chỉ để gom nhóm các câu hook. Mỗi lô, worker chọn ngẫu nhiên 1 chủ đề rồi 1 hook <b>đã duyệt</b> còn đủ outfit:
            câu hook là tiêu đề lô, outfit phải có <b>đủ các hashtag của hook</b> (lô có sản phẩm: hook phải có hashtag của sản phẩm).
            Hook nói về mùa thì thêm tag mùa (<code>thudong</code> / <code>xuanhe</code>). Từ khoá trong hook đặt trong <code>*...*</code>.
        </p>
        <div class="chips">
            <a href="{{ route('themes.index') }}" class="chip {{ $status ? '' : 'is-active' }}">Tất cả hook <small>{{ $counts->sum() }}</small></a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('themes.index', ['status' => $key]) }}" class="chip {{ $status === $key ? 'is-active' : '' }}">
                    {{ $label }} <small>{{ $counts[$key] ?? 0 }}</small>
                </a>
            @endforeach
        </div>
    </section>

    @if ($errors->any())
        <div class="errors" style="margin-top:12px">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="gl">
        @forelse ($themes as $theme)
            @php($hooks = $status ? $theme->hooks->where('status', $status) : $theme->hooks)
            @continue($status && $hooks->isEmpty())
            <div class="theme" id="chu-de-{{ $theme->id }}">
                <form method="post" action="{{ route('themes.update', $theme) }}" class="theme__head">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $theme->name }}" required maxlength="255" aria-label="Tên chủ đề">
                    <input name="note" value="{{ $theme->note }}" placeholder="Ghi chú" aria-label="Ghi chú" class="theme__note">
                    <button class="btn">Lưu</button>
                    <button class="btn btn--danger" form="xoa-{{ $theme->id }}" onclick="return confirm('Xoá chủ đề và mọi hook của nó?')">Xoá chủ đề</button>
                </form>
                <form method="post" action="{{ route('themes.destroy', $theme) }}" id="xoa-{{ $theme->id }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>

                <div class="hooks">
                    <div class="hooks__row hooks__row--head"><span>Câu hook</span><span>Hashtag tìm outfit</span><span></span></div>
                    @foreach ($hooks as $hook)
                        <form method="post" action="{{ route('theme-hooks.update', $hook) }}" class="hooks__row" id="hook-{{ $hook->id }}">
                            @csrf
                            @method('PUT')
                            <input name="body" value="{{ $hook->body }}" required maxlength="500" aria-label="Câu hook">
                            <input name="tags" value="{{ $hook->tags }}" required aria-label="Hashtag">
                            <div class="hooks__meta">
                                <span class="ctpl__status ctpl__status--{{ $hook->status }}">{{ $hook->statusLabel() }}</span>
                                <a class="theme__count" href="{{ route('outfits.index', ['tags' => implode(',', $hook->tagList())]) }}">{{ $hook->outfitCount() }} outfit</a>
                            </div>
                            <div class="gl__actions hooks__actions">
                                <button class="btn" name="status" value="">Lưu</button>
                                @if ($hook->status !== \App\Models\OutfitThemeHook::APPROVED)
                                    <button class="btn btn--primary" name="status" value="approved">Duyệt</button>
                                @endif
                                @if ($hook->status !== \App\Models\OutfitThemeHook::REJECTED)
                                    <button class="btn btn--danger" name="status" value="rejected">Bỏ</button>
                                @endif
                                @if ($hook->status !== \App\Models\OutfitThemeHook::PENDING)
                                    <button class="btn" name="status" value="pending">Về chờ duyệt</button>
                                @endif
                                <button class="btn btn--danger" form="xoa-hook-{{ $hook->id }}" onclick="return confirm('Xoá hook này?')">Xoá</button>
                            </div>
                        </form>
                        <form method="post" action="{{ route('theme-hooks.destroy', $hook) }}" id="xoa-hook-{{ $hook->id }}" hidden>
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach
                    <form method="post" action="{{ route('themes.hooks.store', $theme) }}" class="hooks__row hooks__row--new">
                        @csrf
                        <input name="body" required maxlength="500" placeholder="Thêm câu hook...">
                        <input name="tags" required placeholder="chanvaymini tatcaoco thudong">
                        <div class="gl__actions hooks__actions"><button class="btn">Thêm hook</button></div>
                    </form>
                </div>
            </div>
        @empty
            <p class="muted">Chưa có chủ đề nào.</p>
        @endforelse

        <form method="post" action="{{ route('themes.store') }}" class="theme theme--new">
            @csrf
            <h2>Thêm chủ đề</h2>
            <div class="theme__head">
                <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Tên chủ đề, vd. Váy ngắn mùa đông">
                <input name="note" value="{{ old('note') }}" placeholder="Ghi chú" class="theme__note">
                <button class="btn btn--primary">Thêm chủ đề</button>
            </div>
        </form>
    </section>
@endsection
