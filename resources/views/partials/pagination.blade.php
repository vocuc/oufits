{{-- Phân trang theo kiểu chip của app.css (mẫu mặc định của Laravel viết cho Tailwind nên vỡ giao diện) --}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Phân trang">
        @if ($paginator->onFirstPage())
            <span class="chip is-disabled" aria-disabled="true">‹ Trước</span>
        @else
            <a class="chip" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Trước</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pager__dots">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="chip is-active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="chip" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="chip" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau ›</a>
        @else
            <span class="chip is-disabled" aria-disabled="true">Sau ›</span>
        @endif

        <span class="pager__info muted">Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }} · {{ $paginator->total() }} ảnh</span>
    </nav>
@endif
