@extends('layouts.app')

@php
    // Bật/tắt một hashtag trong bộ lọc hiện tại
    $toggle = function (string $tag) use ($selected) {
        $next = in_array($tag, $selected, true)
            ? array_values(array_diff($selected, [$tag]))
            : [...$selected, $tag];

        return route('outfits.index', $next ? ['tags' => implode(',', $next)] : []);
    };
@endphp

@section('content')
    <section class="filters">
        <form method="get" action="{{ route('outfits.index') }}" class="search">
            <input type="search" name="tags" value="{{ implode(' ', $selected) }}"
                   placeholder="Nhập hashtag, vd: thudong vay giaycaogot" autocomplete="off">
            <button class="btn btn--primary">Tìm</button>
        </form>

        @if ($selected)
            <div class="active">
                <span class="muted">Đang lọc:</span>
                @foreach ($selected as $tag)
                    <a href="{{ $toggle($tag) }}" class="chip is-active" title="Bỏ hashtag này">#{{ $tag }} ✕</a>
                @endforeach
                <a href="{{ route('outfits.index') }}" class="clear">Xóa lọc</a>
            </div>
        @endif

        <div class="chips">
            @foreach ($hashtags as $tag)
                <a href="{{ $toggle($tag->name) }}"
                   class="chip {{ in_array($tag->name, $selected, true) ? 'is-active' : '' }}">
                    #{{ $tag->name }} <small>{{ $tag->outfits_count }}</small>
                </a>
            @endforeach
        </div>
    </section>

    <p class="muted count">{{ $outfits->count() }} outfit</p>

    @if ($outfits->isEmpty())
        <div class="empty">Không có outfit nào có đủ các hashtag này.</div>
    @else
        <div class="grid">
            @foreach ($outfits as $outfit)
                <article class="card">
                    <a href="{{ route('outfits.show', $outfit) }}" class="card__title">{{ $outfit->title }}</a>
                    @if ($outfit->style)
                        <p class="muted">{{ $outfit->style }}</p>
                    @endif

                    <div class="tags">
                        @foreach ($outfit->hashtags as $tag)
                            <a href="{{ $toggle($tag->name) }}"
                               class="tag {{ in_array($tag->name, $selected, true) ? 'is-hit' : '' }}">#{{ $tag->name }}</a>
                        @endforeach
                    </div>

                    <textarea id="p-{{ $outfit->id }}" class="sr-only" readonly tabindex="-1">{{ $outfit->prompt }}</textarea>
                    <div class="card__actions">
                        <button type="button" class="btn btn--primary" data-copy="p-{{ $outfit->id }}">Copy prompt</button>
                        <a href="{{ route('outfits.show', $outfit) }}" class="btn">Chi tiết</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
