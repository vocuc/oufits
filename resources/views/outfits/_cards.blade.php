{{-- Các thẻ outfit; dùng cho trang chủ và cho phần tải thêm khi cuộn --}}
@php
    // Bật/tắt một hashtag trong bộ lọc hiện tại
    $toggle ??= function (string $tag) use ($selected) {
        $next = in_array($tag, $selected, true)
            ? array_values(array_diff($selected, [$tag]))
            : [...$selected, $tag];

        return route('outfits.index', $next ? ['tags' => implode(',', $next)] : []);
    };
@endphp
@foreach ($outfits as $outfit)
    <article class="card">
        <a href="{{ route('outfits.show', $outfit) }}" class="card__cover" tabindex="-1">
            @if ($outfit->cover_path)
                <img src="{{ $outfit->coverUrl() }}" alt="{{ $outfit->title }}" loading="lazy">
            @else
                <span class="card__nocover">Chưa có ảnh bìa</span>
            @endif
        </a>
        <label class="pick"><input type="checkbox" name="ids[]" value="{{ $outfit->id }}"> Chọn để ghép</label>
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

        <textarea id="p-{{ $outfit->id }}" class="sr-only" readonly tabindex="-1">{{ \App\Support\PromptComposer::outfitBlock($outfit) }}</textarea>
        <div class="card__actions">
            <button type="button" class="btn btn--primary" data-copy="p-{{ $outfit->id }}">Copy outfit</button>
            <a href="{{ route('outfits.show', $outfit) }}" class="btn">Chi tiết</a>
        </div>
    </article>
@endforeach
