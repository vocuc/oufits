@extends('layouts.app')

@section('title', 'Ghép prompt · OOTD Prompt')

@section('content')
    <a href="{{ route('outfits.index') }}" class="back">← Quay lại</a>

    <div class="detail">
        <h1>Ghép prompt</h1>

        @if ($outfits->isEmpty())
            <div class="empty">Chưa chọn outfit nào. Quay lại trang chủ, tick "Chọn để ghép" ở các outfit rồi bấm "Ghép prompt".</div>
        @else
            <p class="muted">
                {{ $outfits->count() }} outfit:
                @foreach ($outfits as $i => $o)
                    <a href="{{ route('outfits.show', $o) }}">{{ $i + 1 }}. {{ $o->title }}</a>@if (! $loop->last), @endif
                @endforeach
            </p>
            @if (! $layout)
                <div class="errors">{{ \App\Models\OutfitLayout::NO_APPROVED }}
                    <a href="{{ route('outfit-layouts.index') }}">Mở Mẫu ảnh outfit</a></div>
            @else
                <div class="chips">
                    <span class="muted">Layout:</span>
                    @foreach ($layouts as $l)
                        <a href="{{ request()->fullUrlWithQuery(['layout' => $l->code]) }}"
                           class="chip {{ $l->is($layout) ? 'is-active' : '' }}">{{ $l->code }} · {{ $l->name }}</a>
                    @endforeach
                </div>
                <p class="hint">Cách dùng: mở chat mới → chỉ tải lên ảnh giày → dán prompt. Trái/phải tính theo người xem ảnh.
                    Câu hook trên ảnh đang là "{{ \App\Support\PromptComposer::DEFAULT_HOOK }}", sửa trong prompt nếu cần.</p>

                <section class="block">
                    <div class="block__head">
                        <h2>Prompt hoàn chỉnh</h2>
                        <button type="button" class="btn btn--primary" data-copy="prompt">Copy prompt</button>
                    </div>
                    <pre id="prompt" class="prompt">{{ $prompt }}</pre>
                </section>
            @endif
        @endif

        <section class="block">
            <div class="block__head">
                <h2>Câu sửa nhanh</h2>
                <button type="button" class="btn" data-copy="fix">Copy</button>
            </div>
            <pre id="fix" class="prompt prompt--soft">{{ $fix }}</pre>
        </section>
    </div>
@endsection
