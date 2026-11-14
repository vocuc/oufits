@extends('layouts.app')

@section('title', $outfit->title.' · OOTD Prompt')

@section('content')
    <a href="{{ url()->previous() === url()->current() ? route('outfits.index') : url()->previous() }}" class="back">← Quay lại</a>

    <div class="detail">
        @if ($outfit->cover_path)
            <a href="{{ $outfit->coverUrl() }}" target="_blank" class="detail__cover">
                <img src="{{ $outfit->coverUrl() }}" alt="{{ $outfit->title }}">
            </a>
        @endif
        <h1>{{ $outfit->title }}</h1>
        <p class="muted">{{ $outfit->is_public ? '🌐 Công khai' : '🔒 Riêng tư' }}@unless ($outfit->isOwnedBy(auth()->user())) · của {{ $outfit->user?->name }}@endunless</p>
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

        <p class="hint">Đây chỉ là item + cách phối. Bấm "Tạo prompt" để ghép với phần dùng chung, hoặc chọn nhiều outfit ở trang chủ để tạo mỗi outfit một ảnh.</p>

        <section class="block">
            <div class="block__head">
                <h2>Item &amp; cách phối</h2>
                <button type="button" class="btn" data-copy="items">Copy outfit</button>
                <a href="{{ route('renders.index', ['outfit' => $outfit->id]) }}" class="btn">Ảnh đã tạo ({{ $outfit->renders_count }})</a>
                <a href="{{ route('outfits.compose', ['ids' => [$outfit->id]]) }}" class="btn btn--primary">Tạo prompt</a>
            </div>
            <pre id="items" class="prompt">{{ \App\Support\PromptComposer::outfitBlock($outfit) }}</pre>
        </section>
        @if ($outfit->isOwnedBy(auth()->user()))
        <div class="detail__actions">
            <a href="{{ route('outfits.edit', $outfit) }}" class="btn">Sửa</a>
            <form method="post" action="{{ route('outfits.destroy', $outfit) }}"
                  onsubmit="return confirm('Xóa outfit này?')">
                @csrf
                @method('DELETE')
                <button class="btn btn--danger">Xóa</button>
            </form>
        </div>
        @endif
    </div>
@endsection
