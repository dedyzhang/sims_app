<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Asisten Sekolah') — {{ $namaSekolah ?? 'Edutive' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @php
        // Ambil warna tema SIMS milik user agar chatbot terintegrasi (sama dengan layouts.app).
        $pref = auth()->user()?->preference()->firstOrCreate(
            ['user_uuid' => auth()->id()],
            \App\Models\UserPreference::defaults()
        );
        $cpHex  = $pref->primary_color   ?? '#7ba088';
        $cpsHex = $pref->secondary_color ?? '#9db89f';
        $caHex  = $pref->accent_color    ?? '#e5996c';
    @endphp
    <style>
        :root {
            --cp:  {{ $cpHex }};
            --cps: {{ $cpsHex }};
            --ca:  {{ $caHex }};
        }
    </style>

    {{-- Selaraskan mode gelap dengan SIMS (key localStorage 'theme_mode', se-origin).
         Set lebih awal agar tidak berkedip; dengar 'storage' untuk sinkron live saat
         user men-toggle dari halaman induk selagi widget terbuka. --}}
    <script>
        (function () {
            try {
                var dark = (localStorage.getItem('theme_mode') ?? '{{ $pref->theme_mode ?? 'light' }}') === 'dark';
                document.documentElement.classList.toggle('dark', dark);
                window.addEventListener('storage', function (e) {
                    if (e.key === 'theme_mode') document.documentElement.classList.toggle('dark', e.newValue === 'dark');
                });
            } catch (_) {}
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans','Inter','sans-serif'] },
                    colors: {
                        primary: {
                            DEFAULT: 'var(--cp)',
                            50:  'color-mix(in srgb, var(--cp) 9%, white)',
                            100: 'color-mix(in srgb, var(--cp) 16%, white)',
                            200: 'color-mix(in srgb, var(--cp) 28%, white)',
                            300: 'color-mix(in srgb, var(--cp) 46%, white)',
                            400: 'color-mix(in srgb, var(--cp) 72%, white)',
                            500: 'var(--cp)',
                            600: 'var(--cp)',
                            700: 'color-mix(in srgb, var(--cp) 82%, black)',
                            800: 'color-mix(in srgb, var(--cp) 66%, black)',
                            900: 'color-mix(in srgb, var(--cp) 52%, black)',
                        },
                        accent: { DEFAULT: 'var(--ca)', 100:'color-mix(in srgb, var(--ca) 16%, white)', 500:'var(--ca)', 600:'color-mix(in srgb, var(--ca) 86%, black)' },
                    },
                }
            }
        }
    </script>

    <style>
        * { font-family: 'Plus Jakarta Sans','Inter',sans-serif; }
        html, body { height: 100%; margin: 0; }
        ::-webkit-scrollbar { width:7px; height:7px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:rgba(120,120,120,.25); border-radius:8px; }
    </style>
    @stack('styles')
</head>
<body class="antialiased text-slate-800 dark:text-slate-100 dark:bg-slate-900">
    @yield('content')
    @stack('scripts')
</body>
</html>
