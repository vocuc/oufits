@extends('layouts.app')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Thư viện ảnh</h1>
        <p class="muted" style="margin:0">Ảnh gốc ChatGPT tạo cho lô phối đồ A51 (chưa in câu hook). Chấm 1–10 theo độ hợp shop: điểm cao thì các outfit sau phối giống kiểu này hơn, điểm thấp thì tránh.</p>
        <div class="chips">
            @foreach (['' => 'Tất cả', '0' => 'Chưa chấm', '1' => 'Đã chấm'] as $value => $label)
                <a href="{{ route('renders.index', array_filter(['outfit' => $outfit?->id, 'product' => $productId, 'rated' => $value], fn ($v) => $v !== null && $v !== '')) }}"
                   class="chip {{ (string) $rated === (string) $value ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($outfit || $productId)
            <div class="active">
                <span class="muted">Đang lọc:</span>
                @if ($outfit)
                    <a href="{{ route('outfits.show', $outfit) }}" class="chip is-active">Outfit: {{ $outfit->title }}</a>
                @endif
                @if ($productId)
                    <span class="chip is-active">Sản phẩm A51 #{{ $productId }}</span>
                @endif
                <a href="{{ route('renders.index') }}" class="clear">Xóa lọc</a>
            </div>
        @endif
    </section>

    @if ($renders->isEmpty())
        <p class="muted">Chưa có ảnh nào.</p>
    @else
        <div class="grid">
            @foreach ($renders as $render)
                <article class="card" id="render-{{ $render->id }}">
                    <a href="{{ $render->url() }}" target="_blank" class="card__cover">
                        <img src="{{ $render->url(true) }}" alt="" loading="lazy">
                    </a>
                    @if ($render->outfit)
                        <a href="{{ route('renders.index', ['outfit' => $render->outfit_id]) }}" class="card__title">{{ $render->outfit->title }}</a>
                    @endif
                    <p class="muted">
                        @if ($render->a51_product_id)
                            <a href="{{ route('renders.index', ['product' => $render->a51_product_id]) }}">{{ $render->a51_product_name ?: 'Sản phẩm #'.$render->a51_product_id }}</a> ·
                        @endif
                        {{ $render->created_at?->format('d/m/Y H:i') }}
                    </p>
                    <form method="POST" action="{{ route('renders.rate', $render) }}" class="rate">
                        @csrf
                        @method('PUT')
                        <p class="rate__head">
                            <span>Độ hợp shop</span>
                            <strong>{{ $render->rating ? $render->rating.'/10' : 'Chưa chấm' }}</strong>
                        </p>
                        <div class="rate__scale" role="group" aria-label="Chấm điểm từ 1 đến 10">
                            @for ($i = 1; $i <= 10; $i++)
                                <button type="submit" name="rating" value="{{ $render->rating === $i ? '' : $i }}"
                                        class="rate__btn {{ $render->rating && $i <= $render->rating ? 'is-on' : '' }}"
                                        aria-pressed="{{ $render->rating === $i ? 'true' : 'false' }}"
                                        title="{{ $render->rating === $i ? 'Bỏ điểm' : $i.' điểm' }}">{{ $i }}</button>
                            @endfor
                        </div>
                    </form>
                </article>
            @endforeach
        </div>
        <div style="margin:18px 0">{{ $renders->links() }}</div>
    @endif
@endsection
