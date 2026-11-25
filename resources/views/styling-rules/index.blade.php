@extends('layouts.app')

@section('title', 'Luật cấm phối · OOTD Prompt')

@section('content')
    <section class="filters">
        <h1 style="margin:0 0 6px">Luật cấm phối</h1>
        <p class="muted" style="margin:0">
            Mỗi luật: <b>giày</b> này không được phối với <b>món</b> kia. Luật <b>đã duyệt</b> có hiệu lực ngay với worker phối đồ và lệnh
            lập kế hoạch: outfit có cả hashtag giày lẫn hashtag món bị cấm sẽ không được chọn; lô theo sản phẩm còn bỏ outfit có món bị cấm
            với đôi giày của sản phẩm. Ghi đúng hashtag outfit (vd <code>depquai</code>, <code>aomangto</code>).
            Luật viết bằng chữ (khía cạnh "khác") hoặc không ghi giày chỉ để tham khảo, không tự lọc.
        </p>
    </section>

    @if ($errors->any())
        <div class="errors" style="margin-top:12px">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="gl">
        <form method="post" action="{{ route('styling-rules.store') }}" class="theme theme--new">
            @csrf
            <h2>Thêm luật cấm</h2>
            <div class="theme__head" style="flex-wrap:wrap">
                <input name="shoes" value="{{ old('shoes') }}" required placeholder="Hashtag giày, vd. depquai depkep deplong" aria-label="Hashtag giày" style="flex:2 1 220px">
                <select name="dimension" aria-label="Khía cạnh" style="flex:1 1 160px">
                    @foreach ($dimensions as $key => $label)
                        <option value="{{ $key }}" @selected(old('dimension', 'outer') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input name="values" value="{{ old('values') }}" required placeholder="Hashtag món bị cấm, vd. aokhoaclong aomangto" aria-label="Hashtag món bị cấm" style="flex:2 1 220px">
                <input name="note" value="{{ old('note') }}" placeholder="Ghi chú, vd. Dép không phối áo khoác dài" aria-label="Ghi chú" class="theme__note" style="flex:3 1 260px">
                <button class="btn btn--primary">Thêm luật</button>
            </div>
        </form>

        <div class="theme">
            <h2>{{ $rules->count() }} luật "tránh"</h2>
            <div class="hooks">
                @forelse ($rules as $rule)
                    @php($tracked = $violations[$rule->id] !== null)
                    <div class="hooks__row" id="luat-{{ $rule->id }}" style="grid-template-columns:minmax(0,3fr) minmax(0,2fr) auto">
                        <div>
                            <b>{{ $rule->shoe_type ?? 'Mọi giày' }}</b>
                            <span class="muted">✕ {{ $dimensions[$rule->dimension] ?? 'Khác' }}:</span>
                            <code>{{ $rule->value }}</code>
                            @if ($rule->note)
                                <div class="muted" style="font-size:13px">{{ $rule->note }}</div>
                            @endif
                        </div>
                        <div class="hooks__meta">
                            <span class="ctpl__status ctpl__status--{{ $rule->is_confirmed ? 'approved' : 'pending' }}">{{ $rule->is_confirmed ? 'Đã duyệt' : 'Chưa duyệt' }}</span>
                            <span class="muted">{{ ['user' => 'Bạn thêm', 'claude' => 'Claude', 'ootd_stats' => 'Thống kê', 'tiktok' => 'TikTok'][$rule->source] ?? $rule->source }}</span>
                            @if ($tracked)
                                <a class="theme__count" href="{{ route('outfits.index', ['tags' => $rule->shoe_type.','.$rule->value]) }}" title="Outfit đang có cả giày và món bị cấm">{{ $violations[$rule->id] }} outfit vi phạm</a>
                            @else
                                <span class="muted" title="Không có giày hoặc khía cạnh không phải hashtag">chỉ tham khảo</span>
                            @endif
                        </div>
                        <div class="gl__actions">
                            <form method="post" action="{{ route('styling-rules.update', $rule) }}" style="display:inline">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_confirmed" value="{{ $rule->is_confirmed ? 0 : 1 }}">
                                <button class="btn {{ $rule->is_confirmed ? '' : 'btn--primary' }}">{{ $rule->is_confirmed ? 'Bỏ duyệt' : 'Duyệt' }}</button>
                            </form>
                            @if ($rule->source === 'user')
                                <form method="post" action="{{ route('styling-rules.destroy', $rule) }}" style="display:inline" onsubmit="return confirm('Xoá luật này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn--danger">Xoá</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="muted">Chưa có luật "tránh" nào.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
