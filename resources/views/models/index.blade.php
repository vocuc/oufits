@extends('layouts.app')

@section('title', 'Người mẫu · OOTD Prompt')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Người mẫu</h1>
        <p class="muted" style="margin:0">
            Tải ảnh người mẫu và ghi chú bạn muốn (dáng người, da, tóc, thần thái...). Claude xem ảnh + ghi chú rồi viết
            <b>prompt mô tả người mẫu</b> để ChatGPT vẽ ảnh outfit. Ảnh chỉ bạn xem được. Sửa ghi chú / ảnh thì prompt sẽ được đánh dấu cần viết lại.
        </p>
    </section>

    @if ($errors->any())
        <div class="errors" style="margin-top:12px">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <details class="mdl__new" @if ($models->isEmpty() || $errors->any()) open @endif>
        <summary class="btn btn--primary">+ Thêm người mẫu</summary>
        <form method="post" action="{{ route('models.store') }}" enctype="multipart/form-data" class="form">
            @csrf
            <label>Tên người mẫu
                <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="vd: Mẫu A – hotgirl dáng mảnh"></label>
            <label>Ảnh <small class="muted">(chọn được nhiều ảnh, tối đa 20, mỗi ảnh ≤ 20MB)</small>
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
            <label>Ghi chú <small class="muted">(bạn muốn người mẫu trông thế nào)</small>
                <textarea name="notes" rows="5" placeholder="vd: dáng mảnh, eo nhỏ, ngực to mông to, da trắng, tóc đen dài uốn lơi, thần thái sexy sang, không được béo">{{ old('notes') }}</textarea></label>
            <div><button class="btn btn--primary">Lưu người mẫu</button></div>
        </form>
    </details>

    @if ($models->isEmpty())
        <p class="muted">Chưa có người mẫu nào.</p>
    @endif

    <div class="mdl">
        @foreach ($models as $m)
            <article class="mdl__row" id="nguoi-mau-{{ $m->id }}">
                <div class="ctpl__title">
                    <b>{{ $m->name }}</b>
                    @if ($m->is_default)
                        <span class="ctpl__status ctpl__status--approved">Mặc định</span>
                    @endif
                    @if (blank($m->prompt))
                        <span class="ctpl__status mdl__status--need">Chưa có prompt</span>
                    @elseif ($m->needsPrompt())
                        <span class="ctpl__status mdl__status--need">Ghi chú / ảnh mới — prompt cần viết lại</span>
                    @else
                        <span class="ctpl__status">Prompt viết lúc {{ $m->prompt_updated_at?->format('d/m/Y H:i') }}</span>
                    @endif
                </div>

                <div class="mdl__photos">
                    @forelse ($m->photos as $p)
                        <figure class="mdl__photo">
                            <a href="{{ $p->url() }}" target="_blank"><img src="{{ $p->url(true) }}" alt="Ảnh {{ $m->name }}" loading="lazy"></a>
                            <button class="mdl__del" form="xoa-anh-{{ $p->id }}" title="Xoá ảnh này">×</button>
                        </figure>
                        <form method="post" action="{{ route('models.photos.destroy', ['model' => $m->id, 'photo' => $p->id]) }}"
                              id="xoa-anh-{{ $p->id }}" onsubmit="return confirm('Xoá ảnh này?')" hidden>
                            @csrf
                            @method('DELETE')
                        </form>
                    @empty
                        <div class="ctpl__noimg mdl__photo">Chưa có ảnh</div>
                    @endforelse
                    <form method="post" action="{{ route('models.photos.store', ['model' => $m->id]) }}" enctype="multipart/form-data" class="mdl__add">
                        @csrf
                        <label class="ctpl__noimg mdl__photo mdl__pick">+ Thêm ảnh
                            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple hidden onchange="this.form.submit()"></label>
                    </form>
                </div>

                <form method="post" action="{{ route('models.update', ['model' => $m->id]) }}" class="mdl__notes">
                    @csrf
                    @method('PUT')
                    <label>Tên <input name="name" value="{{ $m->name }}" required maxlength="255"></label>
                    <label>Ghi chú
                        <textarea name="notes" rows="4" placeholder="Bạn muốn người mẫu trông thế nào...">{{ $m->notes }}</textarea></label>
                    <div class="ctpl__save">
                        <button class="btn btn--primary">Lưu ghi chú</button>
                        @unless ($m->is_default)
                            <button class="btn" form="mac-dinh-{{ $m->id }}">Đặt làm mặc định</button>
                        @endunless
                        <button class="btn btn--danger" form="xoa-{{ $m->id }}">Xoá người mẫu</button>
                    </div>
                </form>
                <form method="post" action="{{ route('models.default', ['model' => $m->id]) }}" id="mac-dinh-{{ $m->id }}" hidden>@csrf</form>
                <form method="post" action="{{ route('models.destroy', ['model' => $m->id]) }}" id="xoa-{{ $m->id }}"
                      onsubmit="return confirm('Xoá hẳn người mẫu {{ addslashes($m->name) }} và toàn bộ ảnh? Không khôi phục được.')" hidden>
                    @csrf
                    @method('DELETE')
                </form>

                <details class="ctpl__prompt" @if (filled($m->prompt)) open @endif>
                    <summary>Prompt mô tả người mẫu {{ blank($m->prompt) ? '(chưa có — gọi Claude viết)' : '' }}</summary>
                    <form method="post" action="{{ route('models.prompt', ['model' => $m->id]) }}" class="ctpl__edit">
                        @csrf
                        @method('PUT')
                        <textarea name="prompt" rows="8" placeholder="Claude sẽ viết đoạn này từ ảnh + ghi chú. Bạn có thể tự sửa.">{{ $m->prompt }}</textarea>
                        <button class="btn">Lưu prompt</button>
                    </form>
                </details>
            </article>
        @endforeach
    </div>
@endsection
