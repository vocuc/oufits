<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'OOTD Prompt')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <header class="topbar">
        <div class="wrap topbar__inner">
            <a href="{{ auth()->check() ? route('outfits.index') : route('login') }}" class="brand">OOTD<span>prompt</span></a>
            @auth
                @php($me = auth()->user())
                <nav class="nav">
                    <details class="account" data-account>
                        <summary class="account__toggle" aria-label="Tài khoản">
                            <span class="account__avatar">{{ mb_strtoupper(mb_substr($me->name ?: $me->email, 0, 1)) }}</span>
                            <span class="account__name">{{ $me->name ?: $me->email }}</span>
                            <span class="account__caret" aria-hidden="true">▾</span>
                        </summary>
                        <div class="account__menu">
                            <div class="account__info">
                                <b>{{ $me->name }}</b>
                                <span class="muted">{{ $me->email }}</span>
                                <span class="muted">{{ $me->outfits()->count() }} outfit</span>
                            </div>
                            <a href="{{ route('outfits.create') }}" class="account__item">+ Thêm outfit</a>
                            <a href="{{ route('renders.index') }}" class="account__item">Thư viện ảnh</a>
                            <a href="{{ route('settings.edit') }}" class="account__item">Prompt dùng chung</a>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button class="account__item account__item--danger">Đăng xuất</button>
                            </form>
                        </div>
                    </details>
                </nav>
            @else
                <nav class="nav">
                    <a href="{{ route('login') }}" class="nav__link">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn--primary">Đăng ký</a>
                </nav>
            @endauth
        </div>
    </header>

    <main class="wrap">
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    <script>
        // Đóng menu tài khoản khi bấm ra ngoài hoặc nhấn Esc
        document.addEventListener('click', (e) => {
            document.querySelectorAll('[data-account][open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') document.querySelectorAll('[data-account][open]').forEach((d) => { d.open = false; });
        });

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-copy]');
            if (!btn) return;
            const el = document.getElementById(btn.dataset.copy);
            const text = el.value ?? el.textContent;
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                const ta = document.createElement('textarea');
                ta.value = text; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); ta.remove();
            }
            const old = btn.textContent;
            btn.textContent = 'Đã copy ✓';
            setTimeout(() => btn.textContent = old, 1500);
        });
    </script>
</body>
</html>
