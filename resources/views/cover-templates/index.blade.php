@extends('layouts.app')

@section('title', 'Thư viện ảnh bìa · OOTD Prompt')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Thư viện ảnh bìa</h1>
        <p class="muted" style="margin:0">
            Các cách làm ảnh bìa cho lô phối đồ A51. Worker phối đồ <b>chỉ làm bìa theo mẫu đã duyệt</b> và đọc cột
            "Yêu cầu" của mẫu đó trước khi làm.
        </p>
        <div class="chips">
            <a href="{{ route('cover-templates.index') }}" class="chip {{ $status ? '' : 'is-active' }}">Tất cả <small>{{ $counts->sum() }}</small></a>
            @foreach (\App\Models\CoverTemplate::STATUSES as $key => $label)
                <a href="{{ route('cover-templates.index', ['status' => $key]) }}" class="chip {{ $status === $key ? 'is-active' : '' }}">
                    {{ $label }} <small>{{ $counts[$key] ?? 0 }}</small>
                </a>
            @endforeach
        </div>
    </section>

    @if ($errors->any())
        <div class="errors" style="margin-top:12px">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($templates->isEmpty())
        <p class="muted">Chưa có mẫu nào.</p>
    @else
        <div class="ctpl">
            <div class="ctpl__head">
                <span>Ảnh mẫu</span><span>Cách làm</span><span>Yêu cầu</span>
            </div>
            @foreach ($templates as $t)
                <article class="ctpl__row" id="mau-{{ $t->id }}">
                    <div class="ctpl__img">
                        @if ($t->url())
                            <a href="{{ $t->url() }}" target="_blank"><img src="{{ $t->url(true) }}" alt="Mẫu {{ $t->code }}" loading="lazy"></a>
                        @endif
                    </div>
                    <div class="ctpl__how">
                        <div class="ctpl__title">
                            <b>{{ $t->code }} · {{ $t->name }}</b>
                            <span class="ctpl__status ctpl__status--{{ $t->status }}">{{ $t->statusLabel() }}</span>
                        </div>
                        @if ($t->hook_type)
                            <span class="chip">Hook: {{ $t->hook_type }}</span>
                        @endif
                        <div class="ctpl__text">{{ $t->how_to }}</div>
                    </div>
                    <form method="post" action="{{ route('cover-templates.update', $t) }}" class="ctpl__req">
                        @csrf
                        @method('PUT')
                        <textarea name="request" rows="7" placeholder="Ghi yêu cầu cho mẫu này...">{{ $t->request }}</textarea>
                        <div class="ctpl__save">
                            <button class="btn" name="status" value="" title="Chỉ lưu yêu cầu">Lưu yêu cầu</button>
                            @if ($t->status !== \App\Models\CoverTemplate::APPROVED)
                                <button class="btn btn--primary" name="status" value="approved">Duyệt</button>
                            @endif
                            @if ($t->status !== \App\Models\CoverTemplate::REJECTED)
                                <button class="btn btn--danger" name="status" value="rejected">Bỏ</button>
                            @endif
                            @if ($t->status !== \App\Models\CoverTemplate::PENDING)
                                <button class="btn" name="status" value="pending">Về chờ duyệt</button>
                            @endif
                        </div>
                        @if ($t->request_updated_at || $t->reviewed_at)
                            <small class="muted">
                                @if ($t->request_updated_at) Yêu cầu sửa {{ $t->request_updated_at->format('d/m/Y H:i') }}. @endif
                                @if ($t->reviewed_at) Duyệt / bỏ lúc {{ $t->reviewed_at->format('d/m/Y H:i') }}. @endif
                            </small>
                        @endif
                    </form>
                </article>
            @endforeach
        </div>
    @endif
@endsection
