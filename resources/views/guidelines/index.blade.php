@extends('layouts.app')

@section('title', 'Quy tắc · OOTD Prompt')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Quy tắc</h1>
        <p class="muted" style="margin:0">
            Quy tắc bạn viết cho worker phối đồ. Worker đọc mọi quy tắc <b>đang bật</b> trước khi tạo outfit, làm ảnh bìa và đặt hook.
            Mỗi dòng một quy tắc, viết ngắn gọn, rõ ràng.
        </p>
        <div class="chips">
            @foreach ($categories as $key => $label)
                <a href="#{{ $key }}" class="chip">{{ $label }} <small>{{ ($rules[$key] ?? collect())->where('is_active', true)->count() }}</small></a>
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

    @foreach ($categories as $key => $label)
        <section class="gl" id="{{ $key }}">
            <h2>{{ $label }}</h2>
            @forelse ($rules[$key] ?? [] as $i => $rule)
                <form method="post" action="{{ route('guidelines.update', $rule) }}" class="gl__row {{ $rule->is_active ? '' : 'is-off' }}" id="quy-tac-{{ $rule->id }}">
                    @csrf
                    @method('PUT')
                    <span class="gl__no">{{ $loop->iteration }}</span>
                    <textarea name="body" rows="2" required>{{ $rule->body }}</textarea>
                    <div class="gl__actions">
                        <label class="gl__toggle"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)> Bật</label>
                        <button class="btn btn--primary">Lưu</button>
                        <button class="btn btn--danger" form="xoa-{{ $rule->id }}" onclick="return confirm('Xoá quy tắc này?')">Xoá</button>
                    </div>
                </form>
                <form method="post" action="{{ route('guidelines.destroy', $rule) }}" id="xoa-{{ $rule->id }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @empty
                <p class="muted">Chưa có quy tắc nào.</p>
            @endforelse

            <form method="post" action="{{ route('guidelines.store') }}" class="gl__row gl__row--new">
                @csrf
                <input type="hidden" name="category" value="{{ $key }}">
                <span class="gl__no">+</span>
                <textarea name="body" rows="2" required placeholder="Thêm quy tắc {{ mb_strtolower($label) }}..."></textarea>
                <div class="gl__actions">
                    <button class="btn">Thêm</button>
                </div>
            </form>
        </section>
    @endforeach
@endsection
