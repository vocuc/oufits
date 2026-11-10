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

    <form method="post" class="form"
          action="{{ $outfit->exists ? route('outfits.update', $outfit) : route('outfits.store') }}">
        @csrf
        @if ($outfit->exists) @method('PUT') @endif

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

        <label>Prompt *
            <textarea name="prompt" rows="16" required>{{ old('prompt', $outfit->prompt) }}</textarea>
        </label>

        <label>Câu sửa nhanh
            <textarea name="fix_prompt" rows="8">{{ old('fix_prompt', $outfit->fix_prompt) }}</textarea>
        </label>

        <div class="detail__actions">
            <button class="btn btn--primary">Lưu</button>
        </div>
    </form>
@endsection
