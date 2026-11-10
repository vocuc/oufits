@extends('layouts.app')

@php
    // Bật/tắt một hashtag trong bộ lọc hiện tại
    $toggle = function (string $tag) use ($selected) {
        $next = in_array($tag, $selected, true)
            ? array_values(array_diff($selected, [$tag]))
            : [...$selected, $tag];

        return route('outfits.index', $next ? ['tags' => implode(',', $next)] : []);
    };
@endphp

@section('content')
    <section class="filters">
        <form method="get" action="{{ route('outfits.index') }}" class="search">
            <input type="search" name="tags" value="{{ implode(' ', $selected) }}" data-tag-input
                   placeholder="Nhập hashtag, vd: thudong vay giaycaogot" autocomplete="off">
            <button class="btn btn--primary">Tìm</button>
        </form>

        @if ($selected)
            <div class="active">
                <span class="muted">Đang lọc:</span>
                @foreach ($selected as $tag)
                    <a href="{{ $toggle($tag) }}" class="chip is-active" title="Bỏ hashtag này">#{{ $tag }} ✕</a>
                @endforeach
                <a href="{{ route('outfits.index') }}" class="clear">Xóa lọc</a>
            </div>
        @endif

        {{-- Mặc định hiện 30 hashtag phổ biến nhất; khi gõ thì hiện 30 hashtag gần giống nhất --}}
        <div class="chips" data-tag-list>
            @foreach ($hashtags as $tag)
                <a href="{{ $toggle($tag->name) }}" data-tag="{{ $tag->name }}" @if ($loop->index >= 30) hidden @endif
                   class="chip {{ in_array($tag->name, $selected, true) ? 'is-active' : '' }}">
                    #{{ $tag->name }} <small>{{ $tag->outfits_count }}</small>
                </a>
            @endforeach
            <span class="muted chips__empty" data-tag-empty hidden>Không có hashtag nào gần giống.</span>
        </div>
    </section>

    <script>
        (() => {
            const LIMIT = 30;
            const input = document.querySelector("[data-tag-input]");
            const chips = [...document.querySelectorAll("[data-tag-list] [data-tag]")]; // đã xếp theo độ phổ biến
            const empty = document.querySelector("[data-tag-empty]");
            if (!input || !chips.length) return;

            // Giống Hashtag::normalize: chữ thường, bỏ dấu, đ→d, chỉ giữ chữ số
            const normalize = (s) => s.toLowerCase().replace(/đ/g, "d")
                .normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]/g, "");

            const levenshtein = (a, b) => {
                let prev = Array.from({ length: b.length + 1 }, (_, j) => j);
                for (let i = 1; i <= a.length; i++) {
                    const cur = [i];
                    for (let j = 1; j <= b.length; j++) {
                        cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
                    }
                    prev = cur;
                }
                return prev[b.length];
            };
            const isSubsequence = (q, t) => { let i = 0; for (const c of t) if (c === q[i]) i++; return i === q.length; };

            // Điểm càng nhỏ càng giống; null = không khớp
            const score = (q, t) => {
                if (t === q) return 0;
                if (t.startsWith(q)) return 1 + (t.length - q.length) / 100;
                const at = t.indexOf(q);
                if (at >= 0) return 2 + at / 100;
                if (isSubsequence(q, t)) return 3 + (t.length - q.length) / 100;
                const d = levenshtein(q, t.slice(0, q.length + 2));
                return d <= Math.max(1, Math.floor(q.length / 3)) ? 4 + d : null;
            };

            const render = () => {
                const words = input.value.split(/[\s,#]+/);
                const query = normalize(words.pop() || "");        // từ đang gõ (cuối ô)
                const typed = new Set(words.map(normalize));        // các từ đã gõ xong thì không gợi ý lại

                let shown;
                if (!query) {
                    shown = chips.filter((c) => !typed.has(c.dataset.tag)).slice(0, LIMIT);
                } else {
                    shown = chips
                        .map((c, rank) => ({ c, rank, s: typed.has(c.dataset.tag) ? null : score(query, c.dataset.tag) }))
                        .filter((x) => x.s !== null)
                        .sort((a, b) => a.s - b.s || a.rank - b.rank)
                        .slice(0, LIMIT)
                        .map((x) => x.c);
                }

                const list = chips[0].parentElement;
                const keep = new Set(shown);
                chips.forEach((c) => { c.hidden = !keep.has(c); });
                shown.forEach((c) => list.insertBefore(c, empty));    // xếp theo thứ tự gợi ý
                empty.hidden = shown.length > 0;
            };

            input.addEventListener("input", render);
            render();
        })();
    </script>

    <p class="muted count">{{ $total }} outfit</p>

    @if ($outfits->isEmpty())
        <div class="empty">Không có outfit nào có đủ các hashtag này.</div>
    @else
        <form method="get" action="{{ route('outfits.compose') }}" id="pick-form">
        <div class="grid" data-outfit-grid>
            @include('outfits._cards')
        </div>

        @if ($nextOffset !== null)
            {{-- Cuộn tới đây thì tự tải thêm; bấm được nếu trình duyệt không tự tải --}}
            <div class="loadmore" data-loadmore>
                <a class="btn" href="{{ route('outfits.index', array_filter(['tags' => implode(',', $selected) ?: null, 'offset' => $nextOffset])) }}"
                   data-next="{{ $nextOffset }}">Tải thêm</a>
            </div>
        @endif

        <div class="composebar" hidden>
            <span><b data-pick-count>0</b> outfit đã chọn</span>
            <button class="btn btn--primary">Ghép prompt →</button>
        </div>
        </form>

        <script>
            (() => {
                const form = document.getElementById("pick-form");
                const bar = form.querySelector(".composebar");
                const sync = () => {
                    const n = form.querySelectorAll("input[name=\"ids[]\"]:checked").length;
                    bar.hidden = n === 0;
                    bar.querySelector("[data-pick-count]").textContent = n;
                };
                form.addEventListener("change", sync);
                sync();

                // Cuộn gần cuối trang thì tải thêm outfit
                const box = document.querySelector("[data-loadmore]");
                if (!box) return;
                const grid = document.querySelector("[data-outfit-grid]");
                const link = box.querySelector("[data-next]");
                let loading = false;

                const loadMore = async () => {
                    if (loading || !link.dataset.next) return;
                    loading = true;
                    link.textContent = "Đang tải…";
                    try {
                        const url = new URL(link.href);
                        url.searchParams.set("offset", link.dataset.next);
                        const res = await fetch(url, { headers: { Accept: "application/json" } });
                        if (!res.ok) throw new Error(res.status);
                        const data = await res.json();
                        grid.insertAdjacentHTML("beforeend", data.html);
                        if (data.next_offset === null) {
                            observer.disconnect();
                            box.remove();
                            return;
                        }
                        link.dataset.next = data.next_offset;
                        link.textContent = "Tải thêm";
                    } catch {
                        link.textContent = "Lỗi tải, bấm để thử lại";
                    } finally {
                        loading = false;
                    }
                };

                const observer = new IntersectionObserver((entries) => {
                    if (entries.some((e) => e.isIntersecting)) loadMore();
                }, { rootMargin: "600px 0px" });
                observer.observe(box);
                link.addEventListener("click", (e) => { e.preventDefault(); loadMore(); });
            })();
        </script>
    @endif
@endsection
