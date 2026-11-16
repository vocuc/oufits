{{-- Dùng chung cho "Mẫu ảnh bìa" (/cover-templates) và "Mẫu ảnh outfit" (/outfit-layouts). --}}
@extends('layouts.app')

@section('title', $page['title'].' · OOTD Prompt')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">{{ $page['title'] }}</h1>
        <p class="muted" style="margin:0">{{ $page['intro'] }}</p>
        <div class="chips">
            <a href="{{ route($route.'.index') }}" class="chip {{ $status ? '' : 'is-active' }}">Tất cả <small>{{ $counts->sum() }}</small></a>
            @foreach (\App\Models\CoverTemplate::STATUSES as $key => $label)
                <a href="{{ route($route.'.index', ['status' => $key]) }}" class="chip {{ $status === $key ? 'is-active' : '' }}">
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
                        @else
                            <div class="ctpl__noimg">Chưa có ảnh mẫu</div>
                        @endif
                    </div>
                    <div class="ctpl__how">
                        <div class="ctpl__title">
                            <b>{{ $t->code }} · {{ $t->name }}</b>
                            <span class="ctpl__status ctpl__status--{{ $t->status }}">{{ $t->statusLabel() }}</span>
                        </div>
                        {{-- Mẫu bìa chỉ quyết định cách trình bày hook; kiểu / chủ đề hook do Claude tự chọn theo ảnh bìa --}}
                        @if ($t->hook_type && $route !== 'cover-templates')
                            <span class="chip">Hook: {{ $t->hook_type }}</span>
                        @endif
                        <div class="ctpl__text">{{ $t->how_to }}</div>
                        @if ($page['show_prompt'])
                            <details class="ctpl__prompt">
                                <summary>Xem / sửa prompt layout</summary>
                                <form method="post" action="{{ route($route.'.prompt', ['template' => $t->id]) }}" class="ctpl__edit">
                                    @csrf
                                    @method('PUT')
                                    <label>Prompt layout <small class="muted">(khung ảnh, người mẫu, dáng, bối cảnh, chữ; có <code>{HOOK}</code> thì ảnh có câu hook)</small>
                                        <textarea name="prompt" rows="18" required>{{ old('prompt', $t->prompt) }}</textarea></label>
                                    <button class="btn btn--primary">Lưu prompt</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <form method="post" action="{{ route($route.'.update', ['template' => $t->id]) }}" class="ctpl__req">
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
                            @if (Route::has($route.'.destroy'))
                                <button class="btn btn--danger" form="xoa-{{ $t->id }}" title="Xoá hẳn mẫu này">Xoá</button>
                            @endif
                        </div>
                        @if ($t->request_updated_at || $t->reviewed_at)
                            <small class="muted">
                                @if ($t->request_updated_at) Yêu cầu sửa {{ $t->request_updated_at->format('d/m/Y H:i') }}. @endif
                                @if ($t->reviewed_at) Duyệt / bỏ lúc {{ $t->reviewed_at->format('d/m/Y H:i') }}. @endif
                            </small>
                        @endif
                    </form>
                    @if (Route::has($route.'.destroy'))
                        <form method="post" action="{{ route($route.'.destroy', ['template' => $t->id]) }}" id="xoa-{{ $t->id }}"
                              onsubmit="return confirm('Xoá hẳn mẫu {{ $t->code }} · {{ addslashes($t->name) }}? Không khôi phục được.')" hidden>
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
