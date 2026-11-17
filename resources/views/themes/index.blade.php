@extends('layouts.app')

@section('title', 'Chủ đề · OOTD Prompt')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Chủ đề</h1>
        <p class="muted" style="margin:0">
            Chủ đề của lô phối đồ. Mỗi lô, worker chọn <b>ngẫu nhiên</b> 1 chủ đề <b>đã duyệt</b> còn đủ outfit,
            lấy outfit có <b>đủ các hashtag</b> của chủ đề (lô có sản phẩm thêm hashtag giày), và lấy ngẫu nhiên 1 câu hook làm tiêu đề lô.
            Hook mỗi dòng một câu, từ khoá đặt trong <code>*...*</code>.
        </p>
        <div class="chips">
            <a href="{{ route('themes.index') }}" class="chip {{ $status ? '' : 'is-active' }}">Tất cả <small>{{ $counts->sum() }}</small></a>
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
            @php($n = $theme->outfitCount())
            <form method="post" action="{{ route('themes.update', $theme) }}" class="theme" id="chu-de-{{ $theme->id }}">
                @csrf
                @method('PUT')
                <div class="theme__head">
                    <input name="name" value="{{ $theme->name }}" required maxlength="255" aria-label="Tên chủ đề">
                    <span class="ctpl__status ctpl__status--{{ $theme->status }}">{{ $theme->statusLabel() }}</span>
                    <a class="theme__count" href="{{ route('outfits.index', ['tags' => implode(',', $theme->tagList())]) }}">{{ $n }} outfit khớp</a>
                </div>
                <label class="theme__field">Hashtag bắt buộc (outfit phải có đủ)
                    <input name="tags" value="{{ $theme->tags }}" required>
                </label>
                <label class="theme__field">Mùa
                    <select name="season">
                        <option value="">Mọi mùa</option>
                        @foreach ($seasons as $key => $label)
                            <option value="{{ $key }}" @selected($theme->season === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="theme__field">Hook (mỗi dòng một câu, {{ count($theme->hookList()) }} câu)
                    <textarea name="hooks" rows="{{ max(3, min(10, count($theme->hookList()) + 1)) }}" required>{{ $theme->hooks }}</textarea>
                </label>
                <label class="theme__field">Ghi chú
                    <textarea name="note" rows="1">{{ $theme->note }}</textarea>
                </label>
                <div class="gl__actions">
                    <button class="btn" name="status" value="">Lưu</button>
                    @if ($theme->status !== \App\Models\OutfitTheme::APPROVED)
                        <button class="btn btn--primary" name="status" value="approved">Duyệt</button>
                    @endif
                    @if ($theme->status !== \App\Models\OutfitTheme::REJECTED)
                        <button class="btn btn--danger" name="status" value="rejected">Bỏ</button>
                    @endif
                    @if ($theme->status !== \App\Models\OutfitTheme::PENDING)
                        <button class="btn" name="status" value="pending">Về chờ duyệt</button>
                    @endif
                    <button class="btn btn--danger" form="xoa-{{ $theme->id }}" onclick="return confirm('Xoá chủ đề này?')">Xoá</button>
                </div>
            </form>
            <form method="post" action="{{ route('themes.destroy', $theme) }}" id="xoa-{{ $theme->id }}" hidden>
                @csrf
                @method('DELETE')
            </form>
        @empty
            <p class="muted">Chưa có chủ đề nào.</p>
        @endforelse

        <form method="post" action="{{ route('themes.store') }}" class="theme theme--new">
            @csrf
            <h2>Thêm chủ đề</h2>
            <label class="theme__field">Tên chủ đề
                <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Váy ngắn + tất cao cổ">
            </label>
            <label class="theme__field">Hashtag bắt buộc (outfit phải có đủ)
                <input name="tags" value="{{ old('tags') }}" required placeholder="chanvaymini tatcaoco">
            </label>
            <label class="theme__field">Mùa
                <select name="season">
                    <option value="">Mọi mùa</option>
                    @foreach ($seasons as $key => $label)
                        <option value="{{ $key }}" @selected(old('season') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="theme__field">Hook (mỗi dòng một câu)
                <textarea name="hooks" rows="4" required placeholder="Váy ngắn mix *tất cao cổ* xinh hết nấc">{{ old('hooks') }}</textarea>
            </label>
            <label class="theme__field">Ghi chú
                <textarea name="note" rows="1">{{ old('note') }}</textarea>
            </label>
            <div class="gl__actions"><button class="btn btn--primary">Thêm (chờ duyệt)</button></div>
        </form>
    </section>
@endsection
