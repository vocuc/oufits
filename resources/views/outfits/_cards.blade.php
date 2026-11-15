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
            {{-- Ảnh bìa = ảnh tạo gần nhất (Thư viện ảnh); chưa có ảnh tạo thì dùng ảnh bìa tải lên --}}
            @if ($outfit->latestRender)
                <img src="{{ $outfit->latestRender->url(true) }}" alt="{{ $outfit->title }}" loading="lazy">
            @elseif ($outfit->cover_path)
                <img src="{{ $outfit->coverUrl() }}" alt="{{ $outfit->title }}" loading="lazy">
            @else
                <span class="card__nocover">Chưa có ảnh bìa</span>
            @endif
        </a>
        <label class="pick"><input type="checkbox" name="ids[]" value="{{ $outfit->id }}"> Chọn để ghép</label>
        <a href="{{ route('outfits.show', $outfit) }}" class="card__title">@unless ($outfit->is_public)🔒 @endunless{{ $outfit->title }}</a>
        @if ($outfit->style)
            <p class="muted">{{ $outfit->style }}</p>
        @endif

        <div class="tags">
            @foreach ($outfit->hashtags as $tag)
                <a href="{{ $toggle($tag->name) }}"
                   class="tag {{ in_array($tag->name, $selected, true) ? 'is-hit' : '' }}">#{{ $tag->name }}</a>
            @endforeach
        </div>

        <div class="card__actions">
            <a href="{{ route('outfits.show', $outfit) }}" class="btn">Chi tiết</a>
            @if (($outfit->renders_count ?? 0) > 0)
                <a href="{{ route('renders.index', ['outfit' => $outfit->id]) }}" class="btn">Ảnh ({{ $outfit->renders_count }})</a>
            @endif
            <form method="post" action="{{ route('outfits.destroy', $outfit) }}" style="display:inline"
                  onsubmit="return confirm('Xóa outfit &quot;{{ addslashes($outfit->title) }}&quot;?{{ ($outfit->renders_count ?? 0) > 0 ? ' Sẽ xóa luôn '.$outfit->renders_count.' ảnh trong Thư viện ảnh và điểm đã chấm của outfit này.' : '' }} Không khôi phục được.')">
                @csrf
                @method('DELETE')
                <button class="btn btn--danger" title="Xóa outfit này">Xóa</button>
            </form>
        </div>
    </article>
@endforeach
