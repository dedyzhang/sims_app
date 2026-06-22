<!DOCTYPE html>
<html lang="id" x-data="appShell()" :class="{ 'dark': darkMode }" x-cloak>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ $namaSekolah ?? 'Edu Nusantara' }}</title>

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

        // Cek apakah background sidebar gelap
        $sbgHex = str_replace('#', '', $pref->sidebar_bg ?? '#fceadb');
        if (strlen($sbgHex) === 3) {
            $sbgHex = $sbgHex[0].$sbgHex[0].$sbgHex[1].$sbgHex[1].$sbgHex[2].$sbgHex[2];
        }
        $r = hexdec(substr($sbgHex, 0, 2) ?: 'fc');
        $g = hexdec(substr($sbgHex, 2, 2) ?: 'ea');
        $b = hexdec(substr($sbgHex, 4, 2) ?: 'db');
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        $isSidebarDark = $yiq < 140; // threshold untuk mendeteksi sidebar gelap
    @endphp

    <style id="theme-vars">
        :root {
            --cp:  {{ $pref->primary_color ?? '#7ba088' }};
            --cps: {{ $pref->secondary_color ?? '#9db89f' }};
            --ca:  {{ $pref->accent_color ?? '#e5996c' }};
            --sbg: {{ $pref->sidebar_bg ?? '#fceadb' }};
            --stx: {{ $pref->sidebar_text ?? '#57534e' }};
            --sia: {{ $isSidebarDark ? '#ffffff' : ($pref->primary_color ?? '#7ba088') }};
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

    <script defer src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
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
        .app-bg { background: linear-gradient(135deg, color-mix(in srgb, var(--cp) 7%, white) 0%, color-mix(in srgb, var(--cps) 3%, white) 50%, #ffffff 100%); }
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
        .nav-link:hover { background: color-mix(in srgb, var(--cp) 12%, transparent); color: {{ $isSidebarDark ? '#ffffff' : 'color-mix(in srgb, var(--stx) 95%, black)' }}; }
        .nav-link.active {
            background: {{ $isSidebarDark ? 'rgba(255, 255, 255, 0.12)' : 'color-mix(in srgb, var(--cp) 16%, transparent)' }};
            color: {{ $isSidebarDark ? '#ffffff' : 'color-mix(in srgb, var(--cp) 78%, black)' }};
            font-weight:700;
        }
        .nav-link.active .nav-icon { color: var(--sia); }
        .dark .nav-link.active { color:#e2e8f0; }
        .nav-section { color: color-mix(in srgb, var(--stx) 45%, transparent); }
        .dark .nav-section { color:#64748b; }
        /* Tombol grup (kategori) + submenu */
        .nav-group { color: color-mix(in srgb, var(--stx) 78%, transparent); border-radius:14px; transition:all .16s; cursor:pointer; }
        .nav-group:hover { background: color-mix(in srgb, var(--cp) 10%, transparent); color: {{ $isSidebarDark ? '#ffffff' : 'color-mix(in srgb, var(--stx) 95%, black)' }}; }
        .nav-group.has-active { color: {{ $isSidebarDark ? '#ffffff' : 'color-mix(in srgb, var(--cp) 78%, black)' }}; }
        .nav-group.has-active .nav-icon { color: var(--sia); }
        .dark .nav-group.has-active { color:#e2e8f0; }
        .nav-submenu { border-left:1.5px solid color-mix(in srgb, var(--stx) 16%, transparent); }
        .dark .nav-submenu { border-left-color: rgba(255,255,255,.1); }
        .nav-sublink { font-weight:500; }

        /* ===== Buttons ===== */
        .btn-primary { background:var(--cp); color:#fff; transition:all .18s; box-shadow:0 6px 16px -6px color-mix(in srgb, var(--cp) 60%, transparent); }
        .btn-primary:hover { filter:brightness(1.06); transform:translateY(-1px); box-shadow:0 10px 22px -6px color-mix(in srgb, var(--cp) 65%, transparent); }
        .btn-primary:active { transform:translateY(0); }
        .btn-ghost { transition:all .18s; }
        .btn-ghost:hover { background:var(--cp); color:#fff; border-color:var(--cp); }

        /* ===== Cards (tint halus ikut tema) ===== */
        .card { background: color-mix(in srgb, var(--cp) 3.5%, #fff); border:1px solid color-mix(in srgb, var(--cp) 11%, #e2e8f0); border-radius:22px; box-shadow:0 4px 18px -10px rgba(15,23,42,.08); transition:box-shadow .2s, transform .2s, border-color .2s; }
        .card-hover:hover { box-shadow:0 16px 36px -16px color-mix(in srgb, var(--cp) 30%, rgba(15,23,42,.18)); transform:translateY(-3px); }
        .dark .card { background:#1e293b; border-color:#334155; }

        /* ===== Table ===== */
        .data-table { width:100%; border-collapse:collapse; }
        .data-table thead th { background: color-mix(in srgb, var(--cp) 4%, #f8fafc); font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color: color-mix(in srgb, var(--cp) 55%, #64748b); padding:13px 18px; text-align:left; border-bottom:1px solid color-mix(in srgb, var(--cp) 11%, #e2e8f0); }
        .dark .data-table thead th { background:#0f172a; color:#94a3b8; border-color:#334155; }
        .data-table tbody td { padding:14px 18px; border-bottom:1px solid color-mix(in srgb, var(--cp) 6%, #f1f5f9); font-size:14px; }
        .dark .data-table tbody td { border-color:#293548; }
        .data-table tbody tr { transition:background .12s; }
        .data-table tbody tr:hover td { background: color-mix(in srgb, var(--cp) 5%, #fff); }
        .dark .data-table tbody tr:hover td { background: color-mix(in srgb, var(--cp) 12%, #1e293b); }
        .data-table tbody tr:last-child td { border-bottom:none; }
        /* ===== Tabel responsif: bisa di-scroll horizontal di mobile ===== */
        .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; max-width:100%; }
        .table-responsive .data-table { width:max-content; min-width:100%; }
        .data-table th, .data-table td { white-space:nowrap; }

        .badge { display:inline-flex; align-items:center; gap:4px; padding:3px 11px; border-radius:9999px; font-size:11px; font-weight:600; }

        .form-input, .form-select { width:100%; border:1.5px solid color-mix(in srgb, var(--cp) 11%, #cbd5e1); border-radius:14px; padding:.65rem .9rem; font-size:.875rem; background:#fff; color: color-mix(in srgb, var(--cp) 85%, #1e293b); transition:border-color .15s, box-shadow .15s; }
        .form-input:focus, .form-select:focus { border-color:var(--cp); box-shadow:0 0 0 4px color-mix(in srgb, var(--cp) 14%, transparent); outline:none; }
        .form-input::placeholder { color: color-mix(in srgb, var(--cp) 25%, #94a3b8); }
        .dark .form-input, .dark .form-select { background:#0f172a; border-color:#334155; color:#e2e8f0; }
        .form-label { display:block; font-size:.8rem; font-weight:600; color: color-mix(in srgb, var(--cp) 45%, #475569); margin-bottom:.4rem; }
        .dark .form-label { color:#94a3b8; }

        /* ===== TomSelect — samakan dengan form & pastikan dropdown opaque + di atas ===== */
        .ts-wrapper { width:100%; }
        .ts-wrapper.single .ts-control { border:1.5px solid color-mix(in srgb, var(--cp) 11%, #cbd5e1) !important; border-radius:14px !important; padding:.55rem .9rem !important; min-height:42px; background:#fff !important; box-shadow:none !important; font-size:.875rem; color: color-mix(in srgb, var(--cp) 85%, #1e293b); }
        .ts-wrapper.focus .ts-control { border-color:var(--cp) !important; box-shadow:0 0 0 4px color-mix(in srgb, var(--cp) 14%, transparent) !important; }
        .dark .ts-wrapper.single .ts-control { background:#0f172a !important; border-color:#334155 !important; color:#e2e8f0; }
        .ts-control .ts-input::placeholder, .ts-control input::placeholder { color: color-mix(in srgb, var(--cp) 25%, #94a3b8); }
        .ts-dropdown { z-index:9999 !important; background:#fff !important; border:1px solid color-mix(in srgb, var(--cp) 11%, #e2e8f0) !important; border-radius:14px !important; box-shadow:0 16px 38px -12px rgba(15,23,42,.15) !important; overflow:hidden; margin-top:6px !important; }
        .dark .ts-dropdown { background:#1e293b !important; border-color:#334155 !important; }
        .ts-dropdown .option { padding:9px 14px !important; font-size:.875rem; color: color-mix(in srgb, var(--cp) 80%, #334155); background:transparent; }
        .dark .ts-dropdown .option { color:#cbd5e1; }
        .ts-dropdown .option.active, .ts-dropdown .ts-dropdown-content .active { background:color-mix(in srgb, var(--cp) 12%, #fff) !important; color:var(--cp) !important; }
        .dark .ts-dropdown .option.active { background:color-mix(in srgb, var(--cp) 22%, #1e293b) !important; }
        .ts-dropdown .no-results { padding:9px 14px; color:#94a3b8; font-size:.85rem; }

        .page-title { font-size:1.5rem; font-weight:800; color:#3f3a34; letter-spacing:-.02em; }
        .dark .page-title { color:#f1f5f9; }

        .modal-backdrop { position:fixed; inset:0; margin:0 !important; background:rgba(40,35,30,.5); backdrop-filter:blur(6px); z-index:80; display:flex; align-items:center; justify-content:center; padding:1rem; }
        .modal-box { background:#fff; border-radius:24px; width:100%; max-height:92vh; overflow-y:auto; box-shadow:0 30px 60px -15px rgba(0,0,0,.3); }
        .dark .modal-box { background:#1e293b; }

        /* ===== jQuery-confirm — disesuaikan tema & dibatasi lebarnya ===== */
        .jconfirm .jconfirm-bg { background:rgba(40,35,30,.5) !important; backdrop-filter:blur(5px); }
        /* paksa lebar container (override grid bootstrap col-md-* yang melebar penuh) */
        .jconfirm .jc-bs3-row, .jconfirm .jconfirm-row { display:flex !important; justify-content:center !important; align-items:flex-start !important; }
        .jconfirm .jconfirm-box-container { width:420px !important; max-width:calc(100vw - 40px) !important; flex:0 0 auto !important; margin:0 !important; float:none !important; left:0 !important; }
        .jconfirm.jconfirm-material .jconfirm-box { width:100% !important; border-radius:20px !important; padding:24px 26px 18px !important; box-shadow:0 30px 60px -15px rgba(0,0,0,.32) !important; font-family:'Plus Jakarta Sans','Inter',sans-serif; }
        .jconfirm.jconfirm-material .jconfirm-title { font-weight:700 !important; color:#0f172a; font-size:1.05rem; }
        .jconfirm.jconfirm-material .jconfirm-content { color:#64748b; font-size:.9rem; line-height:1.5; }
        .jconfirm .jconfirm-buttons { text-align:right; padding-top:10px !important; }
        .jconfirm .jconfirm-buttons button { border-radius:11px !important; font-weight:600 !important; text-transform:none !important; padding:8px 18px !important; margin:0 0 0 8px !important; box-shadow:none !important; transition:filter .15s, background .15s; }
        .jconfirm .jconfirm-buttons button:hover { filter:brightness(1.06); }
        .jconfirm .jconfirm-buttons button.btn-red { background:#ef4444 !important; color:#fff !important; }
        .jconfirm .jconfirm-buttons button.btn-blue { background:var(--cp) !important; color:#fff !important; }
        .jconfirm .jconfirm-buttons button.btn-warning { background:#f59e0b !important; color:#fff !important; }
        .jconfirm .jconfirm-buttons button.btn-default { background:#f1f5f9 !important; color:#475569 !important; }
        .dark .jconfirm.jconfirm-material .jconfirm-box { background:#1e293b !important; }
        .dark .jconfirm.jconfirm-material .jconfirm-title { color:#f1f5f9; }
        .dark .jconfirm.jconfirm-material .jconfirm-content { color:#94a3b8; }
        .dark .jconfirm .jconfirm-buttons button.btn-default { background:#334155 !important; color:#cbd5e1 !important; }

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

        @media (max-width:640px){ main{padding:1rem 1.25rem} .page-title{font-size:1.2rem} .hide-mobile{display:none!important} }

        /* ============================================================
           GAYA "CORPORATE / ANALYZER" — sidebar gelap, kartu putih tegas,
           latar krem, tanpa motif. Tetap ikut warna tema lewat var(--cp).
           ============================================================ */
        body[data-style="corporate"] {
            --sbg: color-mix(in srgb, var(--cp) 42%, #0a140f);  /* sidebar gelap pekat = tint primary */
            --stx: #eef2ee;                                      /* teks sidebar terang */
        }
        body[data-style="corporate"] { background: linear-gradient(135deg, color-mix(in srgb, var(--cp) 4%, #f8fafc) 0%, #ffffff 100%) !important; }
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

@php $myFace = auth()->user()?->siswa?->face_photo_url ?? auth()->user()?->guru?->face_photo_url; @endphp

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
                <span x-show="!collapsed" class="font-extrabold text-[15px] truncate" style="color:var(--stx)">{{ $namaSekolah ?? 'Edu Nusantara' }}</span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">
            @php
                $access  = auth()->user()?->access;
                $isAdmin = in_array($access, ['superadmin','admin']);

                // Grup menu: key => [label, ikon, items[]]; item = [route, [pattern...], ikon, label]
                $groups = [];
                if ($isAdmin) {
                    $groups['master'] = ['Data Master', 'database', [
                        ['guru.index',      ['guru.*'],            'users',          'Data Guru'],
                        ['kelas.index',     ['kelas.*'],           'door-open',      'Data Kelas'],
                        ['siswa.index',     ['siswa.*'],           'graduation-cap', 'Data Siswa'],
                        ['pelajaran.index', ['pelajaran.*'],       'book-open-text', 'Mata Pelajaran'],
                    ]];
                    $groups['presensi'] = ['Absensi & Presensi', 'clipboard-check', [
                        ['absensi.index',       ['absensi.*'],                           'clipboard-check', 'Absensi Siswa'],
                        ['presensi-guru.index', ['presensi-guru.*'],                     'user-check',      'Presensi Guru'],
                        ['wajah.galeri',        ['wajah.*'],                             'scan-face',       'Validasi Wajah'],
                        ['qr.absensi',          ['qr.*'],                                'qr-code',         'QR Absensi'],
                    ]];
                }

                // Grup Penilaian & Rapor (bisa diakses Admin, Guru, & Siswa)
                $penilaianItems = [];
                $penilaianItems[] = ['classroom.index', ['classroom.*'], 'graduation-cap', 'Ruang Kelas'];
                
                if ($isAdmin) {
                    $penilaianItems[] = ['jadwal.index', ['jadwal.*'], 'calendar-clock', 'Jadwal Pelajaran'];
                }
                
                if (auth()->user()?->guru) {
                    $penilaianItems[] = ['agenda.index', ['agenda.index','agenda.create','agenda.edit'], 'clipboard-pen-line', 'Agenda Guru'];
                }
                if (auth()->user()?->guru || $isAdmin) {
                    $penilaianItems[] = ['nilai.index', ['nilai.*'], 'pencil-line', $isAdmin ? 'Penilaian' : 'Buku Guru'];
                    $penilaianItems[] = ['ekskul.index', ['ekskul.*'], 'volleyball', 'Ekstrakurikuler'];
                }
                if ($isAdmin || in_array(auth()->user()?->access, ['kurikulum','kepala']) || auth()->user()?->guru?->walikelas) {
                    $penilaianItems[] = ['rekap.nilai', ['rekap.*'], 'table-2', 'Rekap Nilai'];
                    $penilaianItems[] = ['cetak.rapor.index', ['cetak.*'], 'printer', 'Cetak Rapor'];
                }
                if ($isAdmin || in_array(auth()->user()?->access, ['kurikulum','kepala'])) {
                    $penilaianItems[] = ['agenda.rekap', ['agenda.rekap','agenda.validasi'], 'calendar-check-2', 'Rekap Agenda'];
                }

                if (!empty($penilaianItems)) {
                    $groups['penilaian'] = ['Penilaian & Rapor', 'notebook-pen', $penilaianItems];
                }

                if ($isAdmin) {
                    $groups['sistem'] = ['Sistem', 'sliders-horizontal', [
                        ['setting.index', ['setting.*'], 'settings-2', 'Pengaturan'],
                    ]];
                }
                // (Akun Saya dipindah ke dropdown profil di navbar)

                // Grup yang memuat halaman aktif → dibuka otomatis saat load
                $activeGroup = '';
                foreach ($groups as $gk => $g) {
                    foreach ($g[2] as $it) {
                        if (request()->routeIs(...$it[1])) { $activeGroup = $gk; break 2; }
                    }
                }
            @endphp

            {{-- Menu utama (selalu tampil) --}}
            <p x-show="!collapsed" class="nav-section px-3 pt-2 pb-2 text-[11px] font-bold uppercase tracking-[.1em]">Navigasi</p>
            <a href="{{ route('dashboard') }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i data-lucide="layout-dashboard" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">Dashboard</span>
            </a>
            @if(auth()->user()?->siswa || auth()->user()?->guru)
            <a href="{{ route('absen.qr') }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs('absen.qr') ? 'active' : '' }}">
                <i data-lucide="qr-code" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">Absen QR</span>
            </a>
            @endif

            @can('viewAny', App\Models\ForumTopic::class)
            <a href="{{ route('forum.index') }}" class="nav-link flex items-center gap-3 px-3 py-2.5 {{ request()->routeIs('forum.*') ? 'active' : '' }}">
                <i data-lucide="messages-square" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!collapsed" class="text-sm truncate">Forum Diskusi</span>
            </a>
            @endcan

            {{-- Grup kategori: accordion saat lebar, ikon datar saat ringkas --}}
            @foreach($groups as $gk => $g)
            @php [$glabel, $gicon, $gitems] = $g; @endphp

            {{-- Mode lebar: tombol grup + submenu collapsible --}}
            <div x-show="!collapsed" class="pt-1">
                <button type="button" @click="toggleGroup('{{ $gk }}')"
                        class="nav-group w-full flex items-center gap-3 px-3 py-2.5 {{ $activeGroup===$gk ? 'has-active' : '' }}">
                    <i data-lucide="{{ $gicon }}" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                    <span class="text-sm font-semibold truncate flex-1 text-left">{{ $glabel }}</span>
                    <i data-lucide="chevron-down" class="w-4 h-4 flex-shrink-0 transition-transform duration-200" :class="openGroup==='{{ $gk }}' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="openGroup==='{{ $gk }}'" x-collapse class="nav-submenu ml-[22px] pl-2.5 mt-0.5 space-y-0.5">
                    @foreach($gitems as [$iroute, $ipatterns, $iicon, $ilabel])
                    <a href="{{ route($iroute) }}" class="nav-link nav-sublink flex items-center gap-2.5 px-3 py-2 {{ request()->routeIs(...$ipatterns) ? 'active' : '' }}">
                        <i data-lucide="{{ $iicon }}" class="nav-icon w-4 h-4 flex-shrink-0"></i>
                        <span class="text-[13px] truncate">{{ $ilabel }}</span>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Mode ringkas (icon-only): anak grup jadi ikon datar langsung --}}
            <div x-show="collapsed" class="space-y-0.5 pt-1">
                @foreach($gitems as [$iroute, $ipatterns, $iicon, $ilabel])
                <a href="{{ route($iroute) }}" title="{{ $ilabel }}" class="nav-link flex items-center justify-center px-3 py-2.5 {{ request()->routeIs(...$ipatterns) ? 'active' : '' }}">
                    <i data-lucide="{{ $iicon }}" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                </a>
                @endforeach
            </div>
            @endforeach
        </nav>

        <div class="p-3 flex-shrink-0">
            <div class="flex items-center gap-2.5 p-2.5 rounded-2xl bg-white/50 dark:bg-white/5">
                <div class="w-9 h-9 rounded-xl text-white grid place-items-center text-sm font-bold flex-shrink-0 shadow overflow-hidden {{ $myFace ? 'cursor-zoom-in' : '' }}" style="background:linear-gradient(135deg,var(--ca),var(--cp))" @if($myFace) @click="avatarZoom=true" title="Lihat foto profil" @endif>
                    @if($myFace)<img src="{{ $myFace }}" class="w-full h-full object-cover" alt="profil">@else{{ strtoupper(substr(auth()->user()?->guru?->nama ?? auth()->user()?->siswa?->nama ?? auth()->user()?->username ?? 'U', 0, 1)) }}@endif
                </div>
                <div x-show="!collapsed" class="min-w-0 flex-1">
                    <p class="text-[13px] font-bold truncate leading-tight" style="color:var(--stx)">{{ auth()->user()?->guru?->nama ?? auth()->user()?->siswa?->nama ?? auth()->user()?->username }}</p>
                    <p class="text-[10px] capitalize opacity-50" style="color:var(--stx)">{{ auth()->user()?->access }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- ============ MAIN ============ --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <header class="h-16 flex items-center justify-between px-5 md:px-7 flex-shrink-0 gap-4 z-30">
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

                {{-- Dropdown Notifikasi --}}
                <div class="relative" x-data="notificationDropdown()" x-init="init()" @keydown.escape.window="nOpen=false">
                    <button type="button" @click="nOpen=!nOpen" class="relative grid place-items-center w-9 h-9 rounded-xl hover:bg-black/5 text-slate-500 dark:text-slate-400 transition" title="Notifikasi">
                        <i data-lucide="bell" class="w-[18px] h-[18px]"></i>
                        <template x-if="unreadCount > 0">
                            <span class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white rounded-full text-[9px] font-bold grid place-items-center leading-none" x-text="unreadCount"></span>
                        </template>
                    </button>

                    <div x-show="nOpen" style="display:none" @click.away="nOpen=false"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute -right-12 sm:right-0 mt-2 w-80 max-w-[calc(100vw-1.5rem)] rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-2 z-50">
                        
                        <div class="px-4 py-2 flex items-center justify-between border-b border-slate-100 dark:border-slate-700">
                            <span class="font-bold text-sm text-slate-700 dark:text-slate-100">Notifikasi</span>
                            <template x-if="unreadCount > 0">
                                <button type="button" @click="markAllAsRead()" class="text-xs font-semibold text-primary hover:underline transition" style="color:var(--cp)">Tandai semua dibaca</button>
                            </template>
                        </div>
                        
                        <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">
                            <template x-for="n in notifications" :key="n.id">
                                <div class="px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition cursor-pointer flex gap-3"
                                     :class="!n.read_at ? 'bg-slate-50/40 dark:bg-slate-700/20' : ''"
                                     @click="clickNotification(n)">
                                    <div class="w-8 h-8 rounded-full flex-shrink-0 grid place-items-center text-white"
                                         :style="{ background: n.data.type === 'forum_reply' ? 'var(--cp)' : 'var(--ca)' }">
                                        <i :data-lucide="n.data.type === 'forum_reply' ? 'messages-square' : 'graduation-cap'" class="w-4 h-4"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-slate-700 dark:text-slate-200 font-medium leading-normal" x-text="n.data.message"></p>
                                        <p class="text-[10px] text-slate-400 mt-1" x-text="n.time_ago"></p>
                                    </div>
                                </div>
                            </template>
                            
                            <template x-if="notifications.length === 0">
                                <div class="px-4 py-8 text-center text-slate-400 text-xs italic">Tidak ada notifikasi.</div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="w-px h-6 bg-slate-200 dark:bg-slate-700 mx-1.5"></div>
                @php
                    $akunNama = auth()->user()?->guru?->nama ?? auth()->user()?->siswa?->nama ?? auth()->user()?->username;
                    $akunDepan = \Illuminate\Support\Str::of($akunNama)->explode(' ')->first();
                    $akunInisial = strtoupper(substr($akunNama ?? 'U', 0, 1));
                @endphp
                {{-- Dropdown profil / akun --}}
                <div class="relative" x-data="{ pOpen:false }" @keydown.escape.window="pOpen=false">
                    <button type="button" @click="pOpen=!pOpen" class="flex items-center gap-2 rounded-full pl-2.5 pr-1 py-1 hover:bg-black/5 transition">
                        <span class="hidden sm:block text-sm font-semibold text-slate-600 dark:text-slate-300 max-w-[120px] truncate">{{ $akunDepan }}</span>
                        <span class="w-9 h-9 rounded-full ring-2 ring-white shadow overflow-hidden grid place-items-center text-white text-sm font-bold flex-shrink-0" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
                            @if($myFace)<img src="{{ $myFace }}" class="w-full h-full object-cover" alt="profil">@else{{ $akunInisial }}@endif
                        </span>
                        <i data-lucide="chevron-down" class="hidden sm:block w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': pOpen }"></i>
                    </button>

                    <div x-show="pOpen" style="display:none" @click.away="pOpen=false"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 mt-2 w-60 max-w-[calc(100vw-1.5rem)] rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-2 z-50">
                        {{-- Identitas --}}
                        <div class="px-4 py-2.5 flex items-center gap-3 border-b border-slate-100 dark:border-slate-700">
                            <span class="w-10 h-10 rounded-full overflow-hidden grid place-items-center text-white font-bold flex-shrink-0" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
                                @if($myFace)<img src="{{ $myFace }}" class="w-full h-full object-cover" alt="profil">@else{{ $akunInisial }}@endif
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-700 dark:text-slate-100 truncate">{{ $akunNama }}</p>
                                <p class="text-[11px] capitalize text-slate-400">{{ auth()->user()?->access }}</p>
                            </div>
                        </div>
                        <a href="{{ route('profile.index') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition"><i data-lucide="user-round" class="w-4 h-4"></i> Profil</a>
                        <a href="{{ route('profile.preference') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition"><i data-lucide="palette" class="w-4 h-4"></i> Tampilan</a>
                        @if($myFace)
                        <button type="button" @click="avatarZoom=true; pOpen=false" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition"><i data-lucide="image" class="w-4 h-4"></i> Lihat Foto</button>
                        @endif
                        <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition"><i data-lucide="log-out" class="w-4 h-4"></i> Keluar</button>
                        </form>
                    </div>
                </div>
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

        <main class="flex-1 overflow-y-auto px-5 md:px-7 py-4">
            <div class="anim-fade">@yield('content')</div>
            {{-- Footer --}}
            <footer class="mt-8 pt-4 border-t border-slate-200/70 dark:border-slate-700/60 text-center text-xs text-slate-400">
                &copy; {{ date('Y') }} <span class="font-semibold text-slate-500 dark:text-slate-400">{{ $namaSekolah ?? 'Edu Nusantara' }}</span>. Seluruh hak cipta dilindungi.
            </footer>
        </main>
    </div>
</div>

@if($myFace)
{{-- Lightbox foto profil (klik avatar untuk membuka) --}}
<div x-show="avatarZoom" x-cloak @click="avatarZoom=false" @keydown.escape.window="avatarZoom=false" class="fixed inset-0 z-[10000] flex items-center justify-center p-6" style="display:none; background:rgba(15,12,10,.78); backdrop-filter:blur(6px)">
    <div class="text-center" @click.stop>
        <img src="{{ $myFace }}" class="max-h-[78vh] max-w-[92vw] rounded-3xl shadow-2xl ring-4 ring-white/15" alt="Foto profil">
        <p class="text-white/70 text-xs mt-3">Klik di mana saja untuk menutup</p>
    </div>
</div>
@endif

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
            avatarZoom: false,
            openGroup: ('{{ $activeGroup ?? '' }}' || localStorage.getItem('sb_group') || ''),
            toggleGroup(g){ this.openGroup = (this.openGroup === g ? '' : g); localStorage.setItem('sb_group', this.openGroup); this.$nextTick(()=>lucide.createIcons()); },
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
    function notificationDropdown() {
        return {
            nOpen: false,
            notifications: [],
            unreadCount: 0,
            init() {
                this.fetchNotifications();
                // Polling setiap 10 detik untuk notifikasi baru
                setInterval(() => this.fetchNotifications(), 10000);
            },
            async fetchNotifications() {
                try {
                    let response = await fetch('{{ route('notifications.json') }}');
                    if (response.ok) {
                        let data = await response.json();
                        this.notifications = data.notifications;
                        this.unreadCount = data.unreadCount;
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                    }
                } catch (e) {
                    console.error("Error fetching notifications:", e);
                }
            },
            async clickNotification(n) {
                if (!n.read_at) {
                    try {
                        await fetch(`/notifications/${n.id}/read`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                    } catch (e) {
                        console.error(e);
                    }
                }
                
                // Arahkan ke URL target sesuai tipe notifikasi
                if (n.data.type === 'forum_reply') {
                    window.location.href = `/forum/${n.data.topic_slug}#c-${n.data.comment_id}`;
                } else if (n.data.type === 'classroom_comment') {
                    let url = `/ruang-kelas/${n.data.commentable_type}/${n.data.commentable_id}`;
                    if (n.data.classroom_id) {
                        url += `?class=${n.data.classroom_id}`;
                    }
                    window.location.href = `${url}#c-${n.data.comment_id}`;
                }
            },
            async markAllAsRead() {
                try {
                    let response = await fetch('{{ route('notifications.readAll') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });
                    if (response.ok) {
                        this.fetchNotifications();
                    }
                } catch (e) {
                    console.error(e);
                }
            }
        }
    }
    $.confirm.options = { theme:'material', animation:'scale', closeIcon:true, backgroundDismiss:true, useBootstrap:false, boxWidth:'420px' };
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

        @if(session('reset_account'))
        $.confirm({
            title: '🔑 Password Berhasil Direset',
            content: `
                <div class="space-y-3.5 text-left text-slate-600 dark:text-slate-300">
                    <p class="text-sm">Berikut kredensial baru untuk <strong>{{ session('reset_account.name') }}</strong> ({{ session('reset_account.role') }}):</p>
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Username</span>
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-200 select-all">{{ session('reset_account.username') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Password Baru</span>
                            <span class="font-mono font-bold text-amber-600 dark:text-amber-400 select-all">{{ session('reset_account.password') }}</span>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium">💡 Anda dapat menyalin kredensial di atas dengan mengklik tombol di bawah ini.</p>
                </div>
            `,
            buttons: {
                copy: {
                    text: '📋 Salin Kredensial',
                    btnClass: 'btn-blue',
                    action: function() {
                        const text = "Username: {{ session('reset_account.username') }}\nPassword: {{ session('reset_account.password') }}";
                        navigator.clipboard.writeText(text).then(() => {
                            showToast('Kredensial berhasil disalin!');
                        });
                    }
                },
                tutup: {
                    text: 'Tutup',
                    btnClass: 'btn-default'
                }
            }
        });
        @endif
    });
</script>

{{-- Global Loading Spinner --}}
<div id="global-loading-spinner" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[9999] flex items-center justify-center hidden" x-cloak>
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-xl flex flex-col items-center gap-3">
        <svg class="animate-spin h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="color:var(--cp)">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Sedang memproses...</span>
    </div>
</div>

<script>
    function showGlobalSpinner() {
        const spinner = document.getElementById('global-loading-spinner');
        if (spinner) spinner.classList.remove('hidden');
    }
    function hideGlobalSpinner() {
        const spinner = document.getElementById('global-loading-spinner');
        if (spinner) spinner.classList.add('hidden');
    }

    // Tampilkan spinner loading secara otomatis saat form submit non-AJAX dilakukan
    window.addEventListener('submit', function(e) {
        setTimeout(() => {
            if (!e.defaultPrevented) {
                showGlobalSpinner();
            }
        }, 0);
    });
</script>

@stack('scripts')
</body>
</html>
