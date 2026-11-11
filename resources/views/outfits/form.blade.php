@extends('layouts.app')

@section('title', ($outfit->exists ? 'Sửa' : 'Thêm').' outfit · OOTD Prompt')

@section('content')
    <a href="{{ $outfit->exists ? route('outfits.show', $outfit) : route('outfits.index') }}" class="back">← Quay lại</a>

    <h1>{{ $outfit->exists ? 'Sửa outfit' : 'Thêm outfit' }}</h1>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="post" class="form" enctype="multipart/form-data"
          action="{{ $outfit->exists ? route('outfits.update', $outfit) : route('outfits.store') }}">
        @csrf
        @if ($outfit->exists) @method('PUT') @endif

        <div class="cover-field">
            @if ($outfit->cover_path)
                <img src="{{ $outfit->coverUrl() }}" alt="Ảnh bìa hiện tại" class="cover-field__img">
            @endif
            <label>Ảnh bìa
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                <small class="muted">JPG, PNG hoặc WEBP, tối đa 8 MB. Nên dùng ảnh dọc 4:5 (ảnh đã tạo từ prompt).
                    @if ($outfit->cover_path) Chọn ảnh mới để thay ảnh hiện tại. @endif</small>
            </label>
            @if ($outfit->cover_path)
                <label class="check"><input type="checkbox" name="remove_cover" value="1"> Xóa ảnh bìa hiện tại</label>
            @endif
        </div>

        <label>Tên outfit *
            <input name="title" value="{{ old('title', $outfit->title) }}" required>
        </label>

        <label>Phong cách
            <input name="style" value="{{ old('style', $outfit->style) }}" placeholder="vd. Tiệc tối sang chảnh">
        </label>

        <label>Hashtags *
            <input name="hashtags" value="{{ old('hashtags', $hashtagText) }}" required
                   placeholder="thudong vay aokhoac quanshort giaycaogot">
            <small class="muted">Cách nhau bằng dấu cách hoặc dấu phẩy. Tự chuyển thành chữ thường, không dấu, viết liền. Nhớ thêm hashtag loại giày (giaycaogot, giayboot, giaymule…).</small>
        </label>

        <fieldset>
            <legend>Nguồn RedNote</legend>
            <div class="row">
                <label>Tên bài <input name="source_title" value="{{ old('source_title', $outfit->source_title) }}"></label>
                <label>Tác giả <input name="source_author" value="{{ old('source_author', $outfit->source_author) }}"></label>
            </div>
            <div class="row">
                <label>Link <input type="url" name="source_url" value="{{ old('source_url', $outfit->source_url) }}"></label>
                <label>Vị trí khung ảnh <input name="source_position" value="{{ old('source_position', $outfit->source_position) }}"></label>
            </div>
        </fieldset>

        <label>Item &amp; cách phối *
            <textarea name="items" rows="14" required
                      placeholder="- Áo len đen oversize trễ vai...&#10;- Thắt lưng da đen khóa bạc thắt ngang eo đè lên áo...&#10;- Túi hobo da đen đeo vai bên trái ảnh...&#10;&#10;Tạo dáng:&#10;- Tay phía bên phải ảnh giơ iPhone trắng che kín mặt...&#10;- Tay phía bên trái ảnh đặt lên quai túi...">{{ old('items', $outfit->items) }}</textarea>
            <small class="muted">Ghi các món đồ và cách mặc / phối, cuối cùng là mục "Tạo dáng:". Giày, người mẫu, bối cảnh nằm ở phần dùng chung.</small>
        </label>
        <div class="detail__actions">
            <button class="btn btn--primary">Lưu</button>
        </div>
    </form>
@endsection
