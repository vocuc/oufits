@extends('layouts.app')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Thư viện ảnh</h1>
        <p class="muted" style="margin:0">Ảnh gốc ChatGPT tạo cho lô phối đồ A51 (chưa in câu hook).</p>
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
                <article class="card">
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
                </article>
            @endforeach
        </div>
        <div style="margin:18px 0">{{ $renders->links() }}</div>
    @endif
@endsection
