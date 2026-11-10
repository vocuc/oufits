@extends('layouts.app')

@section('title', $outfit->title.' · OOTD Prompt')

@section('content')
    <a href="{{ url()->previous() === url()->current() ? route('outfits.index') : url()->previous() }}" class="back">← Quay lại</a>

    <div class="detail">
        <h1>{{ $outfit->title }}</h1>
        @if ($outfit->style)
            <p class="muted">{{ $outfit->style }}</p>
        @endif

        <div class="tags">
            @foreach ($outfit->hashtags as $tag)
                <a href="{{ route('outfits.index', ['tags' => $tag->name]) }}" class="tag">#{{ $tag->name }}</a>
            @endforeach
        </div>

        @if ($outfit->source_title || $outfit->source_url)
            <p class="source">
                <span class="muted">Nguồn RedNote:</span>
                @if ($outfit->source_url)
                    <a href="{{ $outfit->source_url }}" target="_blank" rel="noopener">{{ $outfit->source_title ?: $outfit->source_url }}</a>
                @else
                    {{ $outfit->source_title }}
                @endif
                @if ($outfit->source_author) · {{ $outfit->source_author }} @endif
                @if ($outfit->source_position) · <span class="muted">{{ $outfit->source_position }}</span> @endif
            </p>
        @endif

        <p class="hint">Cách dùng: mở chat Gemini mới → chỉ tải lên ảnh giày → dán prompt. Trái/phải tính theo người xem ảnh.</p>

        <section class="block">
            <div class="block__head">
                <h2>Prompt</h2>
                <button type="button" class="btn btn--primary" data-copy="prompt">Copy prompt</button>
            </div>
            <pre id="prompt" class="prompt">{{ $outfit->prompt }}</pre>
        </section>

        @if ($outfit->fix_prompt)
            <section class="block">
                <div class="block__head">
                    <h2>Câu sửa nhanh</h2>
                    <button type="button" class="btn" data-copy="fix">Copy</button>
                </div>
                <pre id="fix" class="prompt prompt--soft">{{ $outfit->fix_prompt }}</pre>
            </section>
        @endif

        <div class="detail__actions">
            <a href="{{ route('outfits.edit', $outfit) }}" class="btn">Sửa</a>
            <form method="post" action="{{ route('outfits.destroy', $outfit) }}"
                  onsubmit="return confirm('Xóa outfit này?')">
                @csrf
                @method('DELETE')
                <button class="btn btn--danger">Xóa</button>
            </form>
        </div>
    </div>
@endsection
