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
            <a href="{{ route('outfits.index') }}" class="brand">OOTD<span>prompt</span></a>
            <a href="{{ route('outfits.create') }}" class="btn btn--primary">+ Thêm outfit</a>
        </div>
    </header>

    <main class="wrap">
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    <script>
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
