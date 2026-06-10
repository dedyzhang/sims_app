<!DOCTYPE html>
<html lang="id" x-data="appShell()" :class="{ 'dark': darkMode }" x-cloak>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Edu Nusantara</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @php
        $pref = auth()->user()?->preference()->firstOrCreate(
            ['user_uuid' => auth()->id()],
            \App\Models\UserPreference::defaults()
        );
        $fontMap = ['sm' => ['11px','13px','15px'], 'md' => ['12px','14px','16px'], 'lg' => ['13px','15px','17px']];
        $fonts = $fontMap[$pref->font_size ?? 'md'];
    @endphp

    <style id="theme-vars">
        :root {
            --cp:  {{ $pref->primary_color ?? '#7ba088' }};
            --cps: {{ $pref->secondary_color ?? '#9db89f' }};
            --ca:  {{ $pref->accent_color ?? '#e5996c' }};
            --sbg: {{ $pref->sidebar_bg ?? '#fceadb' }};
            --stx: {{ $pref->sidebar_text ?? '#57534e' }};
            --fsm: {{ $fonts[0] }};
            --fmd: {{ $fonts[1] }};
            --flg: {{ $fonts[2] }};
        }
    </style>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans','Inter','sans-serif'], display: ['Plus Jakarta Sans','sans-serif'] },
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

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <style>
        * { font-family: 'Plus Jakarta Sans','Inter',sans-serif; }
        [x-cloak] { display:none !important; }
        body { font-size: var(--fmd); }

        ::-webkit-scrollbar { width:7px; height:7px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:rgba(120,120,120,.25); border-radius:8px; }

        /* ===== App background (full screen, ikut tema) ===== */
        .app-bg { background: linear-gradient(135deg, color-mix(in srgb, var(--cp) 9%, white) 0%, color-mix(in srgb, var(--ca) 6%, white) 42%, #ffffff 100%); }
        .dark .app-bg { background:#0f172a; }
        /* Motif decorations (hanya yang aktif tampil) */
        .motif-set { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; display:none; }
        .motif-set.on { display:block; }

        /* ===== Sidebar (light gradient) ===== */
        .sidebar {
            background: linear-gradient(180deg, color-mix(in srgb, var(--sbg) 92%, white) 0%, var(--sbg) 55%, color-mix(in srgb, var(--sbg) 80%, white) 100%);
            color: var(--stx);
            transition: width .28s cubic-bezier(.4,0,.2,1), transform .28s cubic-bezier(.4,0,.2,1);
        }
        .dark .sidebar { background:#111c30; color:#cbd5e1; }
        .nav-link { position:relative; color: color-mix(in srgb, var(--stx) 72%, transparent); border-radius:14px; transition:all .16s; }
        .nav-link:hover { background: color-mix(in srgb, var(--cp) 12%, transparent); color: color-mix(in srgb, var(--stx) 95%, black); }
        .nav-link.active { background: color-mix(in srgb, var(--cp) 16%, transparent); color: color-mix(in srgb, var(--cp) 78%, black); font-weight:700; }
        .nav-link.active .nav-icon { color: var(--cp); }
        .dark .nav-link.active { color:#e2e8f0; }
        .nav-section { color: color-mix(in srgb, var(--stx) 45%, transparent); }
        .dark .nav-section { color:#64748b; }

        /* ===== Buttons ===== */
        .btn-primary { background:var(--cp); color:#fff; transition:all .18s; box-shadow:0 6px 16px -6px color-mix(in srgb, var(--cp) 60%, transparent); }
        .btn-primary:hover { filter:brightness(1.06); transform:translateY(-1px); box-shadow:0 10px 22px -6px color-mix(in srgb, var(--cp) 65%, transparent); }
        .btn-primary:active { transform:translateY(0); }
        .btn-ghost { transition:all .18s; }
        .btn-ghost:hover { background:var(--cp); color:#fff; border-color:var(--cp); }

        /* ===== Cards (tint halus ikut tema) ===== */
        .card { background: color-mix(in srgb, var(--cp) 3.5%, #fff); border:1px solid color-mix(in srgb, var(--cp) 11%, #f1ede7); border-radius:22px; box-shadow:0 4px 18px -10px rgba(60,50,40,.12); transition:box-shadow .2s, transform .2s, border-color .2s; }
        .card-hover:hover { box-shadow:0 16px 36px -16px color-mix(in srgb, var(--cp) 30%, rgba(60,50,40,.22)); transform:translateY(-3px); }
        .dark .card { background:#1e293b; border-color:#334155; }

        /* ===== Table ===== */
        .data-table { width:100%; border-collapse:collapse; }
        .data-table thead th { background:#faf6f1; font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#9a8f82; padding:13px 18px; text-align:left; border-bottom:1px solid #f1ede7; }
        .dark .data-table thead th { background:#0f172a; color:#94a3b8; border-color:#334155; }
        .data-table tbody td { padding:14px 18px; border-bottom:1px solid #f7f3ee; font-size:14px; }
        .dark .data-table tbody td { border-color:#293548; }
        .data-table tbody tr { transition:background .12s; }
        .data-table tbody tr:hover td { background: color-mix(in srgb, var(--cp) 5%, #fff); }
        .dark .data-table tbody tr:hover td { background: color-mix(in srgb, var(--cp) 12%, #1e293b); }
        .data-table tbody tr:last-child td { border-bottom:none; }

        .badge { display:inline-flex; align-items:center; gap:4px; padding:3px 11px; border-radius:9999px; font-size:11px; font-weight:600; }

        .form-input, .form-select { width:100%; border:1.5px solid #ece6df; border-radius:14px; padding:.65rem .9rem; font-size:.875rem; background:#fff; color:#44403c; transition:border-color .15s, box-shadow .15s; }
        .form-input:focus, .form-select:focus { border-color:var(--cp); box-shadow:0 0 0 4px color-mix(in srgb, var(--cp) 14%, transparent); outline:none; }
        .form-input::placeholder { color:#b8ada0; }
        .dark .form-input, .dark .form-select { background:#0f172a; border-color:#334155; color:#e2e8f0; }
        .form-label { display:block; font-size:.8rem; font-weight:600; color:#6b6157; margin-bottom:.4rem; }
        .dark .form-label { color:#94a3b8; }

        .page-title { font-size:1.5rem; font-weight:800; color:#3f3a34; letter-spacing:-.02em; }
        .dark .page-title { color:#f1f5f9; }

        .modal-backdrop { position:fixed; inset:0; margin:0 !important; background:rgba(40,35,30,.5); backdrop-filter:blur(6px); z-index:80; display:flex; align-items:center; justify-content:center; padding:1rem; }
        .modal-box { background:#fff; border-radius:24px; width:100%; max-height:92vh; overflow-y:auto; box-shadow:0 30px 60px -15px rgba(0,0,0,.3); }
        .dark .modal-box { background:#1e293b; }

        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(40,35,30,.45); backdrop-filter:blur(2px); z-index:40; }
        @media (max-width:1024px){ .mob-open .sidebar-overlay{ display:block; } .mob-open .sidebar{ transform:translateX(0)!important; } }

        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }
        @keyframes slideToast { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
        /* fill mode forwards (bukan both) supaya transform akhir = none → tidak jadi containing block bagi modal position:fixed */
        .anim-fade { animation:fadeUp .4s cubic-bezier(.2,.8,.2,1); }
        .stagger > * { animation:fadeUp .45s cubic-bezier(.2,.8,.2,1) both; }
        .stagger > *:nth-child(1){animation-delay:.02s} .stagger > *:nth-child(2){animation-delay:.07s}
        .stagger > *:nth-child(3){animation-delay:.12s} .stagger > *:nth-child(4){animation-delay:.17s}
        .stagger > *:nth-child(5){animation-delay:.22s} .stagger > *:nth-child(6){animation-delay:.27s}

        @media (max-width:640px){ main{padding:1rem} .page-title{font-size:1.2rem} .hide-mobile{display:none!important} }

        /* ============================================================
           GAYA "CORPORATE / ANALYZER" — sidebar gelap, kartu putih tegas,
           latar krem, tanpa motif. Tetap ikut warna tema lewat var(--cp).
           ============================================================ */
        body[data-style="corporate"] {
            --sbg: color-mix(in srgb, var(--cp) 42%, #0a140f);  /* sidebar gelap pekat = tint primary */
            --stx: #eef2ee;                                      /* teks sidebar terang */
        }
        body[data-style="corporate"] { background: linear-gradient(135deg, color-mix(in srgb, var(--ca) 9%, #f8f4ed) 0%, color-mix(in srgb, var(--cp) 5%, #f5f1e9) 100%) !important; }
        .dark body[data-style="corporate"] { background:#0b1220 !important; }
        body[data-style="corporate"] .motif-set { display:none !important; }

        /* Sidebar flat gelap */
        body[data-style="corporate"] .sidebar { background: var(--sbg) !important; box-shadow: 1px 0 0 rgba(0,0,0,.04); }
        body[data-style="corporate"] .nav-link { border-radius:10px; color: color-mix(in srgb, var(--stx) 70%, transparent); }
        body[data-style="corporate"] .nav-link:hover { background: rgba(255,255,255,.08); color:#fff; }
        body[data-style="corporate"] .nav-link.active { background: rgba(255,255,255,.13); color:#fff; }
        body[data-style="corporate"] .nav-link.active::before { display:none; }
        body[data-style="corporate"] .nav-link.active .nav-icon { color:#fff; filter:none; }
        body[data-style="corporate"] .nav-section { color: rgba(233,239,234,.4); }

        /* Kartu: putih, border tipis, sudut lebih kecil, shadow halus */
        body[data-style="corporate"] .card { background:#fff !important; border:1px solid #ececec !important; border-radius:14px !important; box-shadow:0 1px 3px rgba(0,0,0,.05) !important; }
        .dark body[data-style="corporate"] .card { background:#1e293b !important; border-color:#334155 !important; }
        body[data-style="corporate"] .card-hover:hover { transform:none !important; box-shadow:0 6px 18px -8px rgba(0,0,0,.14) !important; }

        /* Tombol & input sedikit lebih kotak */
        body[data-style="corporate"] .btn-primary { border-radius:10px; box-shadow:none; }
        body[data-style="corporate"] .btn-primary:hover { transform:none; }
        body[data-style="corporate"] .form-input, body[data-style="corporate"] .form-select { border-radius:10px; }
        body[data-style="corporate"] .modal-box { border-radius:16px; }
        body[data-style="corporate"] .data-table thead th { background:#f7f9f8; }
    </style>
    @stack('styles')
</head>
<body class="app-bg antialiased text-slate-800 dark:text-slate-100 relative overflow-hidden" data-motif="{{ $pref->motif ?? 'botanical' }}" data-style="{{ $pref->ui_style ?? 'soft' }}">

{{-- ===== Dekorasi motif (ikut tema pilihan) ===== --}}
@include('partials.decorations')

<div class="h-screen flex relative z-10" :class="{ 'mob-open': mobileOpen }">

    <div class="sidebar-overlay lg:hidden" @click="mobileOpen=false"></div>

    {{-- ============ SIDEBAR ============ --}}
    <aside class="sidebar flex flex-col flex-shrink-0 z-50 fixed inset-y-0 left-0 lg:relative -translate-x-full lg:translate-x-0"
           :class="collapsed ? 'w-[78px]' : 'w-[258px]'">

        <div class="flex items-center gap-3 h-16 px-5 pt-2 flex-shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 shadow" style="background:linear-gradient(135deg,var(--cp),var(--cps))">
                    <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-white" stroke="currentColor" stroke-width="2.2"><path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <span x-show="!collapsed" class="font-extrabold text-[15px] truncate" style="color:var(--stx)">Edu Nusantara</span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">
            @php $access = auth()->user()?->access; @endphp

            <p x-show="!collapsed" class="nav-section px-3 pt-2 pb-2 text-[11px] font-bold uppercase tracking-[.1em]">Navigasi</p>
            <a href="{{ route('dashboard') }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i data-lucide="layout-dashboard" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">Dashboard</span>
            </a>

            @if(in_array($access, ['superadmin','admin']))
            @foreach([
                ['guru.index','guru.*','users','Data Guru'],
                ['kelas.index','kelas.*','door-open','Data Kelas'],
                ['siswa.index','siswa.*','graduation-cap','Data Siswa'],
                ['pelajaran.index','pelajaran.*','book-open-text','Mata Pelajaran'],
            ] as [$route, $pattern, $icon, $label])
            <a href="{{ route($route) }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs($pattern) ? 'active' : '' }}">
                <i data-lucide="{{ $icon }}" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">{{ $label }}</span>
            </a>
            @endforeach

            <p x-show="!collapsed" class="nav-section px-3 pt-5 pb-2 text-[11px] font-bold uppercase tracking-[.1em]">Sistem</p>
            <a href="{{ route('setting.index') }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs('setting.*') ? 'active' : '' }}">
                <i data-lucide="settings-2" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">Pengaturan</span>
            </a>
            @endif

            <p x-show="!collapsed" class="nav-section px-3 pt-5 pb-2 text-[11px] font-bold uppercase tracking-[.1em]">Akun Saya</p>
            <a href="{{ route('profile.index') }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs('profile.index','profile.edit') ? 'active' : '' }}">
                <i data-lucide="user-round" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">Profil</span>
            </a>
            <a href="{{ route('profile.preference') }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs('profile.preference') ? 'active' : '' }}">
                <i data-lucide="palette" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">Tampilan</span>
            </a>
        </nav>

        <div class="p-3 flex-shrink-0">
            <div class="flex items-center gap-2.5 p-2.5 rounded-2xl bg-white/50 dark:bg-white/5">
                <div class="w-9 h-9 rounded-xl text-white grid place-items-center text-sm font-bold flex-shrink-0 shadow" style="background:linear-gradient(135deg,var(--ca),var(--cp))">
                    {{ strtoupper(substr(auth()->user()?->guru?->nama ?? auth()->user()?->siswa?->nama ?? auth()->user()?->username ?? 'U', 0, 1)) }}
                </div>
                <div x-show="!collapsed" class="min-w-0 flex-1">
                    <p class="text-[13px] font-bold truncate leading-tight" style="color:var(--stx)">{{ auth()->user()?->guru?->nama ?? auth()->user()?->siswa?->nama ?? auth()->user()?->username }}</p>
                    <p class="text-[10px] capitalize opacity-50" style="color:var(--stx)">{{ auth()->user()?->access }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" x-show="!collapsed">
                    @csrf
                    <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg hover:bg-black/5 opacity-60 hover:opacity-100 transition" style="color:var(--stx)" title="Keluar"><i data-lucide="log-out" class="w-4 h-4"></i></button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ============ MAIN ============ --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <header class="h-16 flex items-center justify-between px-4 md:px-7 flex-shrink-0 gap-4 z-30">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <button @click="mobileOpen=!mobileOpen" class="lg:hidden grid place-items-center w-9 h-9 rounded-xl hover:bg-black/5 text-slate-500 flex-shrink-0"><i data-lucide="menu" class="w-5 h-5"></i></button>
                <button @click="toggleCollapse()" class="hidden lg:grid place-items-center w-9 h-9 rounded-xl hover:bg-black/5 text-slate-400 flex-shrink-0"><i data-lucide="panel-left" class="w-[18px] h-[18px]"></i></button>
                {{-- Search --}}
                <div class="relative max-w-xs w-full hidden sm:block">
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" placeholder="Cari..." class="w-full bg-white/70 dark:bg-slate-800 border border-transparent focus:border-primary/40 rounded-full pl-10 pr-4 py-2 text-sm focus:outline-none transition">
                </div>
            </div>

            <div class="flex items-center gap-1.5 flex-shrink-0">
                {{-- Toggle gaya tampilan (Soft <-> Analyzer) --}}
                <button @click="toggleStyle()" class="grid place-items-center w-9 h-9 rounded-xl hover:bg-black/5 text-slate-500 dark:text-slate-400 transition" :title="'Gaya: '+(uiStyle==='soft'?'Soft':'Analyzer')+' (klik untuk ganti)'">
                    <i data-lucide="layout-template" class="w-[18px] h-[18px]" x-show="uiStyle==='soft'"></i>
                    <i data-lucide="layout-dashboard" class="w-[18px] h-[18px]" x-show="uiStyle==='corporate'"></i>
                </button>
                <button @click="toggleDark()" class="grid place-items-center w-9 h-9 rounded-xl hover:bg-black/5 text-slate-500 dark:text-slate-400 transition" title="Mode gelap/terang">
                    <i data-lucide="moon" class="w-[18px] h-[18px]" x-show="!darkMode"></i>
                    <i data-lucide="sun" class="w-[18px] h-[18px]" x-show="darkMode"></i>
                </button>
                <a href="{{ route('profile.preference') }}" class="grid place-items-center w-9 h-9 rounded-xl hover:bg-black/5 text-slate-500 dark:text-slate-400 transition" title="Tampilan"><i data-lucide="paintbrush" class="w-[18px] h-[18px]"></i></a>
                <div class="w-px h-6 bg-slate-200 dark:bg-slate-700 mx-1.5"></div>
                <a href="{{ route('profile.index') }}" class="flex items-center gap-2.5">
                    <span class="hidden sm:block text-sm font-semibold text-slate-600 dark:text-slate-300">Hi, {{ \Illuminate\Support\Str::of(auth()->user()?->guru?->nama ?? auth()->user()?->siswa?->nama ?? auth()->user()?->username)->explode(' ')->first() }}</span>
                    <div class="w-9 h-9 rounded-full text-white grid place-items-center text-sm font-bold ring-2 ring-white shadow" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
                        {{ strtoupper(substr(auth()->user()?->username ?? 'U', 0, 1)) }}
                    </div>
                </a>
            </div>
        </header>

        {{-- Breadcrumb strip --}}
        <div class="px-4 md:px-7 pb-1 hidden md:flex items-center gap-1.5 text-xs text-slate-400 flex-shrink-0">
            <a href="{{ route('dashboard') }}" class="hover:text-slate-600 grid place-items-center"><i data-lucide="home" class="w-3.5 h-3.5"></i></a>
            @isset($breadcrumbs)
                @foreach($breadcrumbs as $bc)
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    @if(!$loop->last)<a href="{{ $bc['url'] }}" class="hover:text-slate-600 truncate">{{ $bc['label'] }}</a>
                    @else<span class="text-slate-600 dark:text-slate-300 font-semibold truncate">{{ $bc['label'] }}</span>@endif
                @endforeach
            @else
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-slate-600 dark:text-slate-300 font-semibold truncate">@yield('title', 'Dashboard')</span>
            @endisset
        </div>

        <main class="flex-1 overflow-y-auto px-4 md:px-7 py-4">
            <div class="anim-fade">@yield('content')</div>
        </main>
    </div>
</div>

{{-- Toasts --}}
<div class="fixed bottom-6 right-6 z-[9999] space-y-2" id="toastWrap">
    @if(session('success'))
    <div class="toast-item card !rounded-2xl border-l-4 !border-l-emerald-500 px-4 py-3 flex items-start gap-3 min-w-[290px] max-w-md shadow-xl" style="animation:slideToast .35s both">
        <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900 grid place-items-center flex-shrink-0"><i data-lucide="check" class="w-4 h-4 text-emerald-600"></i></div>
        <div class="flex-1 min-w-0"><p class="font-bold text-sm text-slate-800 dark:text-slate-100">Berhasil</p><p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 break-words">{{ session('success') }}</p></div>
        <button onclick="this.closest('.toast-item').remove()" class="text-slate-300 hover:text-slate-500"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
    @endif
    @if(session('error') || $errors->any())
    <div class="toast-item card !rounded-2xl border-l-4 !border-l-rose-500 px-4 py-3 flex items-start gap-3 min-w-[290px] max-w-md shadow-xl" style="animation:slideToast .35s both">
        <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-900 grid place-items-center flex-shrink-0"><i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i></div>
        <div class="flex-1 min-w-0"><p class="font-bold text-sm text-slate-800 dark:text-slate-100">Terjadi Kesalahan</p>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 space-y-0.5">@if(session('error'))<p>{{ session('error') }}</p>@endif @foreach($errors->all() as $err)<p>{{ $err }}</p>@endforeach</div>
        </div>
        <button onclick="this.closest('.toast-item').remove()" class="text-slate-300 hover:text-slate-500"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
    @endif
</div>

<script>
    function appShell() {
        return {
            collapsed: localStorage.getItem('sb_collapsed') === '1',
            mobileOpen: false,
            darkMode: (localStorage.getItem('theme_mode') ?? '{{ $pref->theme_mode ?? 'light' }}') === 'dark',
            uiStyle: '{{ $pref->ui_style ?? 'soft' }}',
            toggleCollapse(){ this.collapsed=!this.collapsed; localStorage.setItem('sb_collapsed', this.collapsed?'1':'0'); this.$nextTick(()=>lucide.createIcons()); },
            toggleDark(){ this.darkMode=!this.darkMode; localStorage.setItem('theme_mode', this.darkMode?'dark':'light'); },
            toggleStyle(){
                this.uiStyle = this.uiStyle === 'soft' ? 'corporate' : 'soft';
                setStyle(this.uiStyle);
                fetch('{{ route('profile.style') }}', { method:'POST', headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content') }, body: JSON.stringify({ ui_style: this.uiStyle }) })
                    .then(()=> showToast('Gaya: ' + (this.uiStyle==='soft'?'Soft':'Analyzer')));
                this.$nextTick(()=>lucide.createIcons());
            },
            init(){ this.$nextTick(()=>lucide.createIcons()); }
        }
    }
    $.confirm.options = { theme:'material', animation:'scale', closeIcon:true, backgroundDismiss:true, columnClass:'medium', useBootstrap:false };
    window.confirmDelete = function(form){ $.confirm({ title:'Hapus data ini?', content:'Tindakan ini permanen dan tidak dapat dibatalkan.', type:'red', icon:'', buttons:{ hapus:{ text:'Ya, Hapus', btnClass:'btn-red', keys:['enter'], action:function(){ form.submit(); } }, batal:{ text:'Batal' } } }); return false; };
    window.confirmAction = function(form, msg, color){ $.confirm({ title:'Konfirmasi', content: msg || 'Lanjutkan?', type: color || 'orange', buttons:{ ya:{ text:'Ya, Lanjutkan', btnClass:'btn-blue', keys:['enter'], action:function(){ form.submit(); } }, batal:{ text:'Batal' } } }); return false; };
    window.showToast = function(msg, type='success'){
        const colors = { success:['#10b981','check'], error:['#ef4444','alert-triangle'], info:['#3b82f6','info'] };
        const [bg, icon] = colors[type] || colors.success;
        const el = document.createElement('div');
        el.className = 'card !rounded-2xl px-4 py-3 flex items-center gap-3 min-w-[260px] shadow-xl';
        el.style.cssText = 'animation:slideToast .35s both; border-left:4px solid '+bg;
        el.innerHTML = `<i data-lucide="${icon}" class="w-5 h-5" style="color:${bg}"></i><span class="text-sm font-semibold text-slate-700 dark:text-slate-200">${msg}</span>`;
        document.getElementById('toastWrap').appendChild(el); lucide.createIcons();
        setTimeout(()=>{ el.style.transition='opacity .3s, transform .3s'; el.style.opacity='0'; el.style.transform='translateX(40px)'; setTimeout(()=>el.remove(),300); }, 3500);
    };
    setTimeout(()=>document.querySelectorAll('#toastWrap .toast-item').forEach(t=>{ t.style.transition='opacity .3s,transform .3s'; t.style.opacity='0'; t.style.transform='translateX(40px)'; setTimeout(()=>t.remove(),300); }), 5000);
    // ===== Motif background switcher =====
    window.setMotif = function(name){
        document.querySelectorAll('.motif-set').forEach(el => el.classList.toggle('on', el.dataset.motif === name));
        document.body.dataset.motif = name;
    };
    // ===== UI style switcher (soft <-> corporate) =====
    window.setStyle = function(name){ document.body.dataset.style = name; };
    document.addEventListener('DOMContentLoaded', ()=>{
        setMotif(document.body.dataset.motif || 'botanical');
        lucide.createIcons();
    });
</script>
@stack('scripts')
</body>
</html>
