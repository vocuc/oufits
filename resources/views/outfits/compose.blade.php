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
            <p class="hint">Cách dùng: mở chat Gemini mới → chỉ tải lên ảnh giày → dán prompt. Trái/phải tính theo người xem ảnh.</p>

            <section class="block">
                <div class="block__head">
                    <h2>Prompt hoàn chỉnh</h2>
                    <button type="button" class="btn btn--primary" data-copy="prompt">Copy prompt</button>
                </div>
                <pre id="prompt" class="prompt">{{ $prompt }}</pre>
            </section>
        @endif

        <section class="block">
            <div class="block__head">
                <h2>Phần dùng chung</h2>
                <button type="button" class="btn" data-copy="common">Copy</button>
            </div>
            <pre id="common" class="prompt prompt--soft">{{ $common }}</pre>
            <small class="muted" style="padding: 0 14px 12px; display: block"><a href="{{ route('settings.edit') }}">Sửa prompt dùng chung</a></small>
        </section>

        <section class="block">
            <div class="block__head">
                <h2>Câu sửa nhanh</h2>
                <button type="button" class="btn" data-copy="fix">Copy</button>
            </div>
            <pre id="fix" class="prompt prompt--soft">{{ $fix }}</pre>
        </section>
    </div>
@endsection
