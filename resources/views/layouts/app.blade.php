<!DOCTYPE html>
<html lang="id" x-data="appShell()" :class="{ 'dark': darkMode }" x-cloak>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ $namaSekolah ?? 'Edutive' }}</title>

    @if($sekolahLogoUrl)
        <link rel="shortcut icon" href="{{ $sekolahLogoUrl }}" type="image/x-icon">
    @else
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @php
        // Halaman kiosk publik (lihat EnsureKioskOrPermission) bisa dirender TANPA user login sama
        // sekali → $pref harus tetap objek valid (bukan null) agar semua akses $pref->xxx di bawah aman.
        $pref = auth()->user()?->preference()->firstOrCreate(
            ['user_uuid' => auth()->id()],
            \App\Models\UserPreference::defaults()
        ) ?? new \App\Models\UserPreference(\App\Models\UserPreference::defaults());
        // Mode kiosk: sidebar/header/ticker disembunyikan. Dihitung PER-REQUEST dari variabel
        // $isKiosk yg dikirim controller (lihat AbsensiController::scan/QrAbsensiController::show),
        // BUKAN dari session — supaya membuka link kiosk tak pernah memengaruhi tab lain di
        // browser yang sama yg mungkin sedang login sbg user lain.
        $kioskChrome = (bool) ($isKiosk ?? false);
        // $access/$isAdmin dipakai di luar blok sidebar juga (mis. floating chat) — jadi
        // dihitung di sini, bukan cuma di dalam <aside> yang bisa disembunyikan (mode kiosk).
        $access  = auth()->user()?->access;
        $isAdmin = in_array($access, ['superadmin','admin']);
        $canManageFeedback = auth()->user()?->canAccess('manage_feedback') ?? false;
        // Badge kosmetik — JANGAN sampai menjatuhkan seluruh halaman kalau tabelnya belum
        // dimigrasikan (mis. baru deploy, migration belum jalan → tampil blank di production).
        $feedbackUnreadCount = 0;
        if ($canManageFeedback) {
            try { $feedbackUnreadCount = \App\Models\UserFeedback::where('status', 'baru')->count(); }
            catch (\Throwable $e) { $feedbackUnreadCount = 0; }
        }
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
    
    <!-- DataTables CSS & JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        /* Penyesuaian DataTables dengan Tailwind & Dark Mode */
        .dataTables_wrapper { font-size: var(--fsm); margin-top: 1rem; }
        .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.25rem 0.5rem; outline: none;
        }
        .dark .dataTables_wrapper .dataTables_length select, .dark .dataTables_wrapper .dataTables_filter input {
            background-color: #1e293b; border-color: #334155; color: #f8fafc;
        }
        .dark .dataTables_wrapper .dataTables_length, .dark .dataTables_wrapper .dataTables_filter, .dark .dataTables_wrapper .dataTables_info {
            color: #94a3b8 !important;
        }
        /* Sembunyikan ikon accordion (jika masih tersisa) */
        table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before, table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
            display: none;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--cp) !important; color: white !important; border: none; border-radius: 0.375rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 0.375rem; padding: 0.25rem 0.75rem; }
    </style>
    
    <script>
        $(document).ready(function() {
            // Auto initialize datatables for sarpras tables
            if (window.location.pathname.includes('/sarpras')) {
                // Initialize on generic tables (except .ttd which is for signatures, or explicit .no-dt)
                $('table:not(.ttd, .no-dt)').addClass('display w-full').DataTable({
                    scrollX: true,
                    pageLength: 15,
                    autoWidth: false,
                    // Toolbar di dalam kartu: length + filter di atas, info + paginate di bawah.
                    dom: '<"sarpras-dt-toolbar"lf>t<"sarpras-dt-footer"ip>',
                    language: {
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_",
                        info: "Menampilkan _START_–_END_ dari _TOTAL_",
                        infoEmpty: "Tidak ada data",
                        zeroRecords: "Data tidak ditemukan",
                        paginate: { first: "Awal", last: "Akhir", next: "Selanjutnya", previous: "Sebelumnya" }
                    }
                });
            }
        });
    </script>
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
        body[data-motif="rainbow"].app-bg {
            background:
                linear-gradient(115deg, rgba(66,133,244,.13) 0 18%, transparent 18% 100%),
                linear-gradient(38deg, transparent 0 62%, rgba(251,188,5,.16) 62% 77%, transparent 77% 100%),
                linear-gradient(145deg, transparent 0 42%, rgba(52,168,83,.12) 42% 55%, transparent 55% 100%),
                linear-gradient(135deg, #ffffff 0%, color-mix(in srgb, var(--cp) 7%, white) 44%, color-mix(in srgb, var(--ca) 8%, white) 100%);
        }
        .dark body[data-motif="rainbow"].app-bg {
            background:
                linear-gradient(115deg, rgba(66,133,244,.18) 0 18%, transparent 18% 100%),
                linear-gradient(38deg, transparent 0 62%, rgba(251,188,5,.15) 62% 77%, transparent 77% 100%),
                linear-gradient(145deg, transparent 0 42%, rgba(52,168,83,.15) 42% 55%, transparent 55% 100%),
                #0f172a;
        }
        /* Motif decorations (hanya yang aktif tampil) */
        .motif-set { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; display:none; }
        .motif-set.on { display:block; }

        /* ===== Sidebar (light gradient) ===== */
        .sidebar {
            background: linear-gradient(180deg, color-mix(in srgb, var(--sbg) 92%, white) 0%, var(--sbg) 55%, color-mix(in srgb, var(--sbg) 80%, white) 100%);
            color: var(--stx);
            transition: width .28s cubic-bezier(.4,0,.2,1), transform .28s cubic-bezier(.4,0,.2,1);
        }
        .sidebar-resize-handle {
            position:absolute; top:0; right:-4px; width:8px; height:100%; z-index:60;
            cursor:col-resize; border:0; background:transparent; display:none;
        }
        .sidebar-resize-handle::after {
            content:""; position:absolute; top:50%; right:2px; width:3px; height:54px;
            transform:translateY(-50%); border-radius:9999px; background:color-mix(in srgb, var(--cp) 42%, transparent);
            opacity:0; transition:opacity .15s, height .15s;
        }
        .sidebar-resize-handle:hover::after, .sidebar-resize-handle:focus-visible::after { opacity:1; height:72px; }
        @media (min-width:1024px){ .sidebar:not(.is-mini) .sidebar-resize-handle{ display:block; } }
        /* Dark mode: paksa --sbg & --stx jadi terang agar semua turunan (nav-link, nav-group,
           nama sekolah, nama user) yang memakai var(--stx) tetap terbaca di atas sidebar gelap,
           berapa pun warna preset/tema "cozy" yang dipilih. */
        .dark .sidebar { background:#111c30; color:#cbd5e1; --sbg:#111c30; --stx:#cbd5e1; }
        .nav-link { position:relative; color: color-mix(in srgb, var(--stx) 72%, transparent); border-radius:14px; transition:all .16s; }
        .nav-link:hover { background: color-mix(in srgb, var(--cp) 12%, transparent); color: {{ $isSidebarDark ? '#ffffff' : 'color-mix(in srgb, var(--stx) 95%, black)' }}; }
        .nav-link.active {
            background: {{ $isSidebarDark ? 'rgba(255, 255, 255, 0.18)' : 'color-mix(in srgb, var(--cp) 26%, transparent)' }};
            color: {{ $isSidebarDark ? '#ffffff' : 'color-mix(in srgb, var(--cp) 88%, black)' }};
            font-weight:700;
            box-shadow: inset 0 0 0 1px {{ $isSidebarDark ? 'rgba(255,255,255,0.12)' : 'color-mix(in srgb, var(--cp) 30%, transparent)' }};
        }
        .nav-link.active .nav-icon { color: var(--sia); stroke-width:2.5; }
        .dark .nav-link.active { color:#e2e8f0; }
        .nav-section { color: color-mix(in srgb, var(--stx) 45%, transparent); }
        .dark .nav-section { color:#64748b; }
        /* Tooltip melayang utk sidebar mode ikon (mini) — fixed agar tak terpotong scroll */
        .sb-tip { position:fixed; transform:translateY(-50%); background:#1e293b; color:#fff; font-size:12px;
            font-weight:600; line-height:1; padding:7px 11px; border-radius:9px; box-shadow:0 8px 22px rgba(15,23,42,.28);
            white-space:nowrap; z-index:9999; opacity:0; pointer-events:none; transition:opacity .12s ease; }
        .sb-tip.show { opacity:1; }
        .sb-tip::before { content:""; position:absolute; right:100%; top:50%; transform:translateY(-50%);
            border:5px solid transparent; border-right-color:#1e293b; }
        .dark .sb-tip { background:#334155; }
        .dark .sb-tip::before { border-right-color:#334155; }
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
        .btn-accent { background:var(--ca); color:#fff; transition:all .18s; box-shadow:0 6px 16px -6px color-mix(in srgb, var(--ca) 60%, transparent); }
        .btn-accent:hover { filter:brightness(1.06); transform:translateY(-1px); box-shadow:0 10px 22px -6px color-mix(in srgb, var(--ca) 65%, transparent); }
        .btn-accent:active { transform:translateY(0); }
        .btn-yellow { background:#f59e0b; color:#fff; transition:all .18s; box-shadow:0 6px 16px -6px rgba(245,158,11,0.4); }
        .btn-yellow:hover { filter:brightness(1.06); transform:translateY(-1px); box-shadow:0 10px 22px -6px rgba(245,158,11,0.5); }
        .btn-yellow:active { transform:translateY(0); }
        .btn-ghost { transition:all .18s; }
        .btn-ghost:hover { background:var(--cp); color:#fff; border-color:var(--cp); }

        /* ===== Cards (tint halus ikut tema) ===== */
        .card { background: color-mix(in srgb, var(--cp) 3.5%, #fff); border:1.5px solid color-mix(in srgb, var(--ca) 25%, color-mix(in srgb, var(--cp) 11%, #e2e8f0)); border-radius:22px; box-shadow:0 4px 18px -10px rgba(15,23,42,.08); transition:box-shadow .2s, transform .2s, border-color .2s; }
        .card-hover:hover { box-shadow:0 16px 36px -16px color-mix(in srgb, var(--cp) 30%, rgba(15,23,42,.18)); transform:translateY(-3px); }
        .dark .card { background:#1e293b; border-color: color-mix(in srgb, var(--ca) 15%, #334155); }

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
        .dark .jconfirm .jconfirm-box { background:#1e293b !important; border: 1px solid #334155 !important; }
        .dark .jconfirm .jconfirm-title { color:#f1f5f9 !important; }
        .dark .jconfirm .jconfirm-content { color:#cbd5e1 !important; }
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
        body[data-style="corporate"] .nav-group { border-radius:10px; color: color-mix(in srgb, var(--stx) 70%, transparent); }
        body[data-style="corporate"] .nav-group:hover { background: rgba(255,255,255,.08); color:#fff; }
        body[data-style="corporate"] .nav-group.has-active { color:#fff; }
        body[data-style="corporate"] .nav-group.has-active .nav-icon { color:#fff; }
        body[data-style="corporate"] .nav-section { color: rgba(233,239,234,.4); }

        /* Kartu: putih, border tipis, sudut lebih kecil, shadow halus */
        body[data-style="corporate"] .card { background:#fff !important; border:1.5px solid color-mix(in srgb, var(--ca) 20%, #ececec) !important; border-radius:14px !important; box-shadow:0 1px 3px rgba(0,0,0,.05) !important; }
        .dark body[data-style="corporate"] .card { background:#1e293b !important; border-color: color-mix(in srgb, var(--ca) 15%, #334155) !important; }
        body[data-style="corporate"] .card-hover:hover { transform:none !important; box-shadow:0 6px 18px -8px rgba(0,0,0,.14) !important; }

        /* Tombol & input sedikit lebih kotak */
        body[data-style="corporate"] .btn-primary { border-radius:10px; box-shadow:none; }
        body[data-style="corporate"] .btn-primary:hover { transform:none; }
        body[data-style="corporate"] .form-input, body[data-style="corporate"] .form-select { border-radius:10px; }
        body[data-style="corporate"] .modal-box { border-radius:16px; }
        body[data-style="corporate"] .data-table thead th { background:#f7f9f8; }

        /* Ticker status bar animation */
        @keyframes ticker-scroll {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-50%, 0, 0); }
        }
        .animate-ticker {
            display: flex;
            white-space: nowrap;
            animation: ticker-scroll 35s linear infinite;
        }
        .animate-ticker:hover {
            animation-play-state: paused;
        }
    </style>
    @stack('styles')
</head>
<body class="app-bg antialiased text-slate-800 dark:text-slate-100 relative overflow-hidden" data-motif="{{ $pref->motif ?? 'botanical' }}" data-style="{{ $pref->ui_style ?? 'soft' }}">

{{-- ===== Dekorasi motif (ikut tema pilihan) ===== --}}
@include('partials.decorations')

@php $myFace = auth()->user()?->siswa?->face_photo_url ?? auth()->user()?->guru?->face_photo_url; @endphp

{{-- h-screen (100vh) TIDAK cocok di mobile: address bar yang muncul/hilang bikin 100vh
     ≠ tinggi layar terlihat → konten terpotong & muncul area putih saat scroll. h-[100dvh]
     (dynamic viewport height) mengikuti tinggi layar sebenarnya; h-screen jadi fallback
     utk browser lama yang belum dukung dvh. --}}
<div class="h-screen h-[100dvh] flex flex-col relative z-10 min-h-0" :class="{ 'mob-open': mobileOpen }">
    <div class="flex-1 flex relative overflow-hidden min-h-0">
        <div class="sidebar-overlay lg:hidden" @click="mobileOpen=false"></div>

    {{-- ============ SIDEBAR (disembunyikan di mode kiosk) ============ --}}
    @unless($kioskChrome)
    <aside class="sidebar flex flex-col flex-shrink-0 z-50 fixed inset-y-0 left-0 lg:relative -translate-x-full lg:translate-x-0"
           :class="mini ? 'w-[78px] is-mini' : 'w-[258px]'"
           :style="sidebarStyle">

        <div class="flex items-center gap-3 h-16 px-5 pt-2 flex-shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 shadow overflow-hidden" style="{{ $sekolahLogoUrl && $sekolahLogoExt === 'png' ? '' : 'background:linear-gradient(135deg,var(--cp),var(--cps))' }}">
                    @if($sekolahLogoUrl)
                        <img src="{{ $sekolahLogoUrl }}" class="w-full h-full {{ in_array($sekolahLogoExt, ['jpg', 'jpeg']) ? 'object-contain' : 'object-cover' }}" alt="Logo">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-white" stroke="currentColor" stroke-width="2.2"><path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @endif
                </div>
                <span x-show="!mini" class="font-extrabold text-[15px] leading-tight break-words" style="color:var(--stx)">{{ $namaSekolah ?? 'Edutive' }}</span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-2 pb-6 space-y-0.5">
            @php
                // $access/$isAdmin sudah dihitung di atas (dekat $kioskChrome), dipakai lagi di sini.
                // Grup menu: key => [label, ikon, items[]]; item = [route, [pattern...], ikon, label]
                // ModulAktif: on/off per sekolah dari Pengaturan → Fitur (default aktif).
                $modulOn = fn (string $kode) => \App\Support\ModulAktif::aktif($kode);
                $groups = [];

                // ── Absensi Saya (self-service: absen QR + presensi guru pribadi) ──
                if ($modulOn('absensi')) {
                    $absensiSayaItems = [];
                    if (auth()->user()?->siswa || auth()->user()?->guru) {
                        $absensiSayaItems[] = ['absen.qr', ['absen.qr'], 'qr-code', 'Absen QR'];
                    }
                    if (auth()->user()?->guru) {
                        $absensiSayaItems[] = ['presensi-guru.self', ['presensi-guru.self'], 'clock', 'Presensi Saya'];
                    }
                    if (!empty($absensiSayaItems)) {
                        $groups['absensi_saya'] = ['Absensi Saya', 'qr-code', $absensiSayaItems];
                    }
                }

                if ($isAdmin || auth()->user()?->canAccess('manage_users')) {
                    $masterItems = [];
                    if ($isAdmin || auth()->user()?->canAccess('manage_users')) {
                        $masterItems[] = ['guru.index',      ['guru.*'],            'users',          'Data Guru'];
                        $masterItems[] = ['siswa.index',     ['siswa.*'],           'graduation-cap', 'Data Siswa'];
                        if ($modulOn('alumni')) {
                            $masterItems[] = ['alumni.index',    ['alumni.*'],          'award',          'Data Alumni'];
                        }
                        $masterItems[] = ['kelas.index',     ['kelas.*'],           'door-open',      'Data Kelas'];
                        $masterItems[] = ['pelajaran.index', ['pelajaran.*'],       'book-open-text', 'Mata Pelajaran'];
                        if ($modulOn('kartu_pelajar')) {
                            $masterItems[] = ['kartu-pelajar.kelola', ['kartu-pelajar.kelola'], 'id-card', 'Kartu Pelajar'];
                        }
                    }
                    if (!empty($masterItems)) {
                        $groups['master'] = ['Data Master', 'database', $masterItems];
                    }
                }

                // ── Absensi & Presensi ──
                if ($modulOn('absensi')) {
                    $presensiItems = [];
                    if ($isAdmin || auth()->user()?->canAccess('manage_absensi')) {
                        $presensiItems[] = ['kalender.index', ['kalender.*'], 'calendar-days', 'Kalender Absensi'];
                        $presensiItems[] = ['absensi.index',       ['absensi.*'],       'clipboard-check', 'Absensi Siswa'];
                        $presensiItems[] = ['presensi-guru.index', ['presensi-guru.*'], 'user-check',      'Presensi Guru'];
                        $presensiItems[] = ['wajah.galeri',        ['wajah.*'],         'scan-face',       'Validasi Wajah'];
                        $presensiItems[] = ['qr.absensi',          ['qr.*'],            'qr-code',         'QR Absensi'];
                        // Pantau Lokasi: admin / kepala / kesiswaan (sekolah) — riwayat GPS absen, bukan live track.
                        if (\App\Support\PantauLokasi::aktif() && \App\Support\PantauLokasi::canViewSchoolWide(auth()->user())) {
                            $presensiItems[] = ['pantau-lokasi.index', ['pantau-lokasi.*'], 'map-pinned', 'Pantau Lokasi'];
                        }
                    }
                    // 7 KAIH: siswa isi sendiri tiap pagi; walikelas/admin lihat rekap; admin/kurikulum kelola soal.
                    if (auth()->user()?->siswa) {
                        $presensiItems[] = ['kaih.isi', ['kaih.isi'], 'heart-handshake', 'Isi 7 KAIH'];
                    }
                    if ($isAdmin || auth()->user()?->canAccess('manage_kaih')) {
                        $presensiItems[] = ['kaih.rekap', ['kaih.rekap', 'kaih.override.*'], 'list-checks', 'Rekap 7 KAIH'];
                    }
                    if ($isAdmin || auth()->user()?->canAccess('manage_kaih')) {
                        $presensiItems[] = ['kaih.soal', ['kaih.soal', 'kaih.opsi.*'], 'settings-2', 'Soal 7 KAIH'];
                    }
                    if (!empty($presensiItems)) {
                        $groups['presensi'] = ['Absensi & Presensi', 'clipboard-check', $presensiItems];
                    }
                }

                // ── Akademik ──
                $akademik = [];
                if ($modulOn('akademik')) {
                    if ($access !== 'orangtua') {
                        $akademik[] = ['classroom.index', ['classroom.*'], 'graduation-cap', 'Ruang Kelas'];
                    }

                    if ($modulOn('arena_belajar') && $access !== 'orangtua') {
                        $akademik[] = ['jagat-misi.index', ['jagat-misi.*'], 'gamepad-2', 'Arena Belajar'];
                    }

                    if ($isAdmin || auth()->user()?->canAccess('manage_jadwal')) {
                        $akademik[] = ['jadwal.index', ['jadwal.*'], 'calendar-clock', 'Jadwal Pelajaran'];
                    } elseif (auth()->user()?->guru) {
                        $akademik[] = ['jadwal.guru', ['jadwal.guru'], 'calendar-clock', 'Jadwal Mengajar'];
                    }

                    if ($isAdmin || auth()->user()?->canAccess('view_all_nilai')) {
                        $akademik[] = ['nilai.index', ['nilai.*'], 'pencil-line', 'Penilaian'];
                        $akademik[] = ['ekskul.index', ['ekskul.*'], 'volleyball', 'Ekstrakurikuler'];
                    } elseif (auth()->user()?->guru) {
                        $akademik[] = ['nilai.index', ['nilai.*'], 'pencil-line', 'Buku Guru'];
                        $akademik[] = ['ekskul.index', ['ekskul.*'], 'volleyball', 'Ekstrakurikuler'];
                    }

                    if ($isAdmin || auth()->user()?->canAccess('manage_rapor')) {
                        $akademik[] = ['rekap.nilai', ['rekap.*'], 'table-2', 'Rekap Nilai'];
                        $akademik[] = ['cetak.rapor.index', ['cetak.*'], 'printer', 'Cetak Rapor'];
                    }

                    if ($isAdmin || auth()->user()?->canAccess('manage_perangkat')) {
                        $akademik[] = ['perangkat.index', ['perangkat.index', 'perangkat.show'], 'folder-check', 'Perangkat Ajar'];
                    } elseif (auth()->user()?->guru) {
                        $akademik[] = ['perangkat.self', ['perangkat.self', 'perangkat.show'], 'folder-check', 'Perangkat Ajar Saya'];
                    }

                    if (auth()->user()?->siswa || $access === 'orangtua') {
                        $akademik[] = ['nilai.self', ['nilai.self'], 'chart-column', 'Nilai Saya'];
                    }
                }

                // Asisten Guru: guru mapel, wali kelas, Kepala Sekolah, semua Waka, admin — bukan siswa/orang tua
                if ($modulOn('asisten_guru') && \App\Support\UserRole::matches($access, 'guru', 'walikelas', 'kepala', 'kurikulum', 'kesiswaan', 'sarpras', 'sapras', 'admin')) {
                    $akademik[] = ['ai.teacher.index', ['ai.teacher.*'], 'sparkles', 'Asisten Guru'];
                }

                if (!empty($akademik)) {
                    $groups['akademik'] = ['Akademik', 'book-open-check', $akademik];
                }

                // ── Analisis AI (Fase 4) — narasi data untuk pimpinan/staf ──
                if ($modulOn('analisis_ai') && ($isAdmin || in_array($access, ['kepala', 'kurikulum', 'kesiswaan']))) {
                    $groups['analisis'] = ['Analisis AI', 'sparkles', [
                        ['ai.analyze.index', ['ai.analyze.*'], 'chart-line', 'Narasi Data AI'],
                        ['ai.rag.index',     ['ai.rag.*'],     'file-search', 'Dokumen AI'],
                    ]];
                }

                // ── Agenda ──
                if ($modulOn('agenda')) {
                    $agendaItems = [];
                    if (auth()->user()?->guru) {
                        $agendaItems[] = ['agenda.index', ['agenda.index','agenda.create','agenda.edit'], 'clipboard-pen-line', 'Agenda Guru'];
                    }
                    if ($isAdmin || auth()->user()?->canAccess('manage_agenda')) {
                        $agendaItems[] = ['agenda.rekap', ['agenda.rekap','agenda.validasi'], 'calendar-check-2', 'Rekap Agenda'];
                        $agendaItems[] = ['agenda.batas', ['agenda.batas'], 'book-open-text', 'Buku Batas'];
                    }
                    // Agenda Rapat: semua guru/staff boleh lihat; kelola penuh utk admin/manage_rapat/sekretaris rapat.
                    if (auth()->user()?->guru || $isAdmin || auth()->user()?->canAccess('manage_rapat') || in_array($access, ['kesiswaan','sarpras','kurikulum','kepala'])) {
                        $agendaItems[] = ['rapat.index', ['rapat.*'], 'users-round', 'Agenda Rapat'];
                    }
                    if (!empty($agendaItems)) {
                        $groups['agenda'] = ['Agenda', 'notebook-pen', $agendaItems];
                    }
                }

                // ── Kedisiplinan ──
                // $bolehKelolaDisiplin/$bolehLihatDisiplin/$disiplinItems didefinisikan DI LUAR gate
                // modulOn('disiplin') karena Pemanggilan Ortu/Siswa (di bawah) memakainya independen
                // dari modul disiplin — kalau di dalam gate, saat modul off variabelnya undefined
                // dan bikin seluruh layout (dipakai di semua halaman) crash.
                $jenisAturan = \App\Models\Setting::get('jenis_aturan', 'p3');
                $bolehKelolaDisiplin = $isAdmin || auth()->user()?->canAccess('manage_disiplin');
                $bolehAjukanDisiplin = auth()->user()?->guru || (auth()->user()?->siswa && \App\Models\Sekretaris::where('id_siswa', auth()->user()->siswa->uuid)->exists());
                $bolehLihatDisiplin = auth()->user()?->siswa || $access === 'orangtua';
                $disiplinItems = [];
                if ($modulOn('disiplin')) {
                    if ($jenisAturan === 'poin') {
                        if ($bolehKelolaDisiplin) {
                            $disiplinItems[] = ['poin.index', ['poin.index', 'poin.create', 'poin.edit'], 'list-checks', 'Master Aturan'];
                            $disiplinItems[] = ['poin.siswa.index', ['poin.siswa.*'], 'users', 'Poin Siswa'];
                            $disiplinItems[] = ['poin.dashboard', ['poin.dashboard'], 'trophy', 'Dashboard Kedisiplinan'];
                            $disiplinItems[] = ['poin.temp.index', ['poin.temp.*'], 'inbox', 'Pengajuan Poin'];
                        }
                        if ($bolehAjukanDisiplin) {
                            $disiplinItems[] = ['poin.guru.index', ['poin.guru.*'], 'square-plus', 'Ajukan Poin'];
                        }
                        if ($bolehLihatDisiplin) {
                            $disiplinItems[] = ['poin.self', ['poin.self'], 'user-round', 'Poin Saya'];
                        }
                    } else {
                        if ($bolehKelolaDisiplin) {
                            $disiplinItems[] = ['p3.index', ['p3.index', 'p3.create', 'p3.edit'], 'list-checks', 'Master Kategori'];
                            $disiplinItems[] = ['p3.siswa.index', ['p3.siswa.*'], 'users', 'P3 Siswa'];
                            $disiplinItems[] = ['p3.temp.index', ['p3.temp.*'], 'inbox', 'Pengajuan P3'];
                        }
                        if ($bolehAjukanDisiplin) {
                            $disiplinItems[] = ['p3.guru.index', ['p3.guru.*'], 'square-plus', 'Ajukan P3'];
                        }
                        if ($bolehLihatDisiplin) {
                            $disiplinItems[] = ['p3.self', ['p3.self'], 'user-round', 'P3 Saya'];
                        }
                    }
                    if (!empty($disiplinItems)) {
                        $groups['disiplin'] = [$jenisAturan === 'poin' ? 'Poin & Aturan' : 'P3 Kedisiplinan', 'shield-alert', $disiplinItems];
                    }
                }
                // Pemanggilan Ortu/Siswa: independen dari jenis_aturan, tampil di grup disiplin
                // yg sedang aktif. Admin/kesiswaan lihat semua; guru biasa lihat riwayat sendiri.
                if ($bolehKelolaDisiplin) {
                    $disiplinItems[] = ['pemanggilan.index', ['pemanggilan.index', 'pemanggilan.show', 'pemanggilan.edit', 'pemanggilan.create'], 'phone-call', 'Pemanggilan Ortu/Siswa'];
                } elseif (auth()->user()?->guru) {
                    $disiplinItems[] = ['pemanggilan.riwayat', ['pemanggilan.riwayat', 'pemanggilan.create', 'pemanggilan.show', 'pemanggilan.edit'], 'phone-call', 'Pemanggilan Ortu/Siswa'];
                }
                if ($bolehLihatDisiplin) {
                    $disiplinItems[] = ['pemanggilan.self', ['pemanggilan.self'], 'phone-call', 'Pemanggilan Saya'];
                }

                if (!empty($disiplinItems)) {
                    $groups['disiplin'] = [$jenisAturan === 'poin' ? 'Poin & Aturan' : 'P3 Kedisiplinan', 'shield-alert', $disiplinItems];
                }

                // ── Wali Kelas ──
                if (auth()->user()?->guru?->walikelas) {
                    $walikelasItems = [
                        ['walikelas.siswa.index', ['walikelas.siswa.*'], 'users-round', 'Data Siswa Kelas'],
                        ['walikelas.sekretaris.form', ['walikelas.sekretaris.*'], 'user-cog', 'Set Sekretaris'],
                    ];
                    if ($modulOn('absensi')) {
                        // Digabung 1 menu (Absensi + Rekap + Daftar Wajah sudah saling ditautkan
                        // lewat tombol di dalam halaman absensi.index), pola sama dgn menu admin
                        // "Absensi Siswa" yang juga pakai wildcard absensi.*.
                        $walikelasItems[] = ['absensi.index', ['absensi.*'], 'clipboard-check', 'Absensi Kelas Saya'];
                        $walikelasItems[] = ['kaih.rekap', ['kaih.rekap', 'kaih.override.*'], 'list-checks', 'Rekap 7 KAIH Kelas'];
                        if (\App\Support\PantauLokasi::aktif()) {
                            $walikelasItems[] = ['pantau-lokasi.index', ['pantau-lokasi.*'], 'map-pinned', 'Pantau Lokasi Kelas'];
                        }
                    }
                    if ($modulOn('disiplin')) {
                        $walikelasItems[] = $jenisAturan === 'poin'
                            ? ['poin.siswa.index', ['poin.siswa.*'], 'shield-alert', 'Poin Siswa Kelas']
                            : ['p3.siswa.index', ['p3.siswa.*'], 'shield-alert', 'P3 Siswa Kelas'];
                    }
                    if ($modulOn('akademik') && \App\Models\Setting::get('walikelas_lihat_nilai', '0') === '1') {
                        $walikelasItems[] = ['walikelas.nilai.index', ['walikelas.nilai.*'], 'graduation-cap', 'Nilai Kelas Saya'];
                    }

                    if ($modulOn('akademik') && !$isAdmin && !auth()->user()?->canAccess('manage_rapor')) {
                        $walikelasItems[] = ['rekap.nilai', ['rekap.*'], 'table-2', 'Rekap Nilai'];
                        $walikelasItems[] = ['cetak.rapor.index', ['cetak.*'], 'printer', 'Cetak Rapor'];
                    }

                    $groups['walikelas'] = ['Wali Kelas', 'presentation', $walikelasItems];
                }

                // ── Sarana & Prasarana ──
                if ($modulOn('sarpras')) {
                    $bolehKelolaSarpras = $isAdmin || auth()->user()?->canAccess('manage_sarpras');
                    if ($bolehKelolaSarpras) {
                        $groups['sarpras'] = ['Sarana & Prasarana', 'building-2', [
                            ['sarpras.dashboard',        ['sarpras.dashboard'],                          'layout-dashboard', 'Dashboard'],
                            ['sarpras.denah.index',      ['sarpras.denah.*','sarpras.ruangan.*'],        'map',              'Denah Sekolah'],
                            ['sarpras.kerusakan.index',  ['sarpras.kerusakan.*'],                        'triangle-alert',   'Lapor Kerusakan'],
                            ['sarpras.aset.index',       ['sarpras.aset.*','sarpras.kategori.*'],        'package',          'Inventaris Barang'],
                            ['sarpras.pengadaan.index',  ['sarpras.pengadaan.*'],                        'shopping-cart',    'Pengadaan'],
                            ['sarpras.peminjaman.index', ['sarpras.peminjaman.*'],                       'hand-helping',     'Peminjaman Barang'],
                            ['sarpras.booking.index',    ['sarpras.booking.*'],                          'calendar-clock',   'Booking Ruangan'],
                            ['sarpras.perbaikan.index',  ['sarpras.perbaikan.*','sarpras.teknisi.*','sarpras.jadwal.*'], 'wrench', 'Perbaikan & Teknisi'],
                            ['sarpras.mutasi.index',     ['sarpras.mutasi.*','sarpras.penghapusan.*'],   'trash-2',          'Mutasi & Hapus'],
                            ['sarpras.supplier.index',   ['sarpras.supplier.*'],                         'truck',            'Supplier'],
                            ['sarpras.laporan.index',    ['sarpras.laporan.*'],                          'file-bar-chart',   'Laporan'],
                        ]];
                    } elseif (auth()->user()?->guru || auth()->user()?->siswa || in_array($access, ['kepala','kurikulum','kesiswaan','sekretaris','walikelas','guru'], true)) {
                        // Menu staff: aksi harian dulu, denah sebagai pendukung.
                        $groups['sarpras'] = ['Sarana & Prasarana', 'building-2', [
                            ['sarpras.peminjaman.index', ['sarpras.peminjaman.*'],                       'hand-helping',     'Pinjam Barang'],
                            ['sarpras.booking.index',    ['sarpras.booking.*'],                          'calendar-clock',   'Booking Ruangan'],
                            ['sarpras.kerusakan.index',  ['sarpras.kerusakan.*'],                        'triangle-alert',   'Lapor Kerusakan'],
                            ['sarpras.denah.index',      ['sarpras.denah.*','sarpras.ruangan.*'],        'map',              'Denah Sekolah'],
                        ]];
                    }
                }

                // ── Keuangan ──
                if ($modulOn('keuangan') && ($isAdmin || auth()->user()?->canAccess('manage_keuangan'))) {
                    $groups['keuangan'] = ['Keuangan / SPP', 'wallet', [
                        ['keuangan.index',      ['keuangan.index','keuangan.kelas'], 'layout-dashboard', 'Pembayaran SPP'],
                        ['keuangan.verifikasi', ['keuangan.verifikasi'],             'badge-check',      'Verifikasi'],
                        ['keuangan.bank',       ['keuangan.bank'],                   'landmark',         'Bank & Metode'],
                    ]];
                }

                // ── Cetak Data (export Excel: siswa, guru, kelas, absensi guru, agenda, nilai) ──
                if ($modulOn('cetak') && $isAdmin) {
                    $groups['cetak'] = ['Cetak Data', 'printer', [
                        ['cetak.siswa.index', ['cetak.siswa.*'], 'users-round', 'Data Siswa'],
                        ['cetak.guru.index', ['cetak.guru.*'], 'user-round', 'Data Guru'],
                        ['cetak.kelas.index', ['cetak.kelas.*'], 'school', 'Data Kelas'],
                        ['cetak.absensiGuru.index', ['cetak.absensiGuru.*'], 'clipboard-check', 'Absensi Guru'],
                        ['cetak.absensiSiswa.index', ['cetak.absensiSiswa.*'], 'calendar-check-2', 'Absensi Siswa'],
                        ['cetak.agenda.index', ['cetak.agenda.*'], 'notebook-pen', 'Data Agenda'],
                        ['cetak.bukuBatas.index', ['cetak.bukuBatas.*'], 'book-open-text', 'Buku Batas'],
                        ['cetak.formatif.index', ['cetak.formatif.*'], 'pencil', 'Nilai Formatif'],
                        ['cetak.sumatif.index', ['cetak.sumatif.*'], 'clipboard-check', 'Nilai Sumatif'],
                        ['cetak.nilaiRapor.index', ['cetak.nilaiRapor.*'], 'file-text', 'Nilai Rapor'],
                        ['cetak.pas.index', ['cetak.pas.*'], 'file-check-2', 'Nilai PAS'],
                        ['cetak.penjabaran.index', ['cetak.penjabaran.*'], 'list-tree', 'Nilai Penjabaran'],
                    ]];
                }

                // ── Sistem ──
                if ($isAdmin || auth()->user()?->canAccess('manage_settings')) {
                    $groups['sistem'] = ['Sistem', 'sliders-horizontal', [
                        ['setting.index', ['setting.index', 'setting.kopRapor', 'setting.penjabaran', 'setting.tpRange'], 'settings-2', 'Pengaturan'],
                        ['setting.roles', ['setting.roles'], 'shield-check', 'Hak Akses (RBAC)'],
                        ['pembaruan.index', ['pembaruan.*'], 'sparkles', 'Info Pembaruan'],
                    ]];
                    // Langganan (lisensi) — hanya superadmin
                    if ($access === 'superadmin') {
                        $groups['sistem'][2][] = ['langganan.index', ['langganan.*'], 'badge-check', 'Langganan'];
                    }
                }
                // (Akun Saya dipindah ke dropdown profil di navbar)

                // Grup yang memuat halaman aktif → dibuka otomatis saat load. Beberapa route
                // (mis. poin.siswa.index) sengaja dipakai bareng di 2 grup berbeda (Poin & Aturan
                // milik kesiswaan + Wali Kelas milik walikelas, untuk user yang punya kedua peran) —
                // jadi di sini kumpulkan SEMUA grup yang cocok, bukan cuma yang pertama ketemu.
                // Pemilihan mana yang dibuka (kalau localStorage masih ingat grup mana yg sedang
                // dibuka user) ditentukan di JS (lihat openGroup), supaya klik dari dalam menu
                // Wali Kelas tidak "meloncat" membuka Poin & Aturan begitu saja.
                $activeGroups = [];
                foreach ($groups as $gk => $g) {
                    foreach ($g[2] as $it) {
                        if (request()->routeIs(...$it[1])) { $activeGroups[] = $gk; break; }
                    }
                }
                if (request()->routeIs('panduan.*', 'feedback.*')) {
                    $activeGroups[] = 'bantuan';
                }
            @endphp

            {{-- Menu utama (selalu tampil) --}}
            <p x-show="!mini" class="nav-section px-3 pt-2 pb-2 text-[11px] font-bold uppercase tracking-[.1em]">Navigasi</p>
            <a href="{{ route('dashboard') }}" data-tip="Dashboard" class="nav-link flex items-center px-3 py-2.5 {{ request()->routeIs('dashboard') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="layout-dashboard" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate">Dashboard</span>
            </a>
            @if($modulOn('kartu_pelajar') && auth()->user()?->siswa)
            <a href="{{ route('kartu-pelajar.self') }}" data-tip="Kartu Pelajar" class="nav-link flex items-center px-3 py-2.5 {{ request()->routeIs('kartu-pelajar.self') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="id-card" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate">Kartu Pelajar</span>
            </a>
            @endif

            @if($modulOn('forum'))
            @can('viewAny', App\Models\ForumTopic::class)
            <a href="{{ route('forum.index') }}" data-tip="Forum Diskusi" class="nav-link flex items-center px-3 py-2.5 {{ request()->routeIs('forum.*') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="messages-square" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate">Forum Diskusi</span>
            </a>
            @endcan
            @endif

            @if($modulOn('pengumuman'))
            <a href="{{ route('pengumuman.index') }}" data-tip="Pengumuman" class="nav-link relative flex items-center px-3 py-2.5 {{ request()->routeIs('pengumuman.*') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="megaphone" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate flex-1">Pengumuman</span>
                <span x-show="pengumumanUnread > 0 && !mini" x-cloak x-text="pengumumanUnread > 99 ? '99+' : pengumumanUnread"
                      class="ml-auto inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-black text-white shadow-sm shadow-rose-500/30"></span>
                <span x-show="pengumumanUnread > 0 && mini" x-cloak
                      class="absolute right-2 top-1.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900"></span>
            </a>
            @endif

            {{-- Unduh Aplikasi: tampil untuk semua pengguna bila diaktifkan admin & ada file --}}
            @php
                $appDownloadOn = \App\Models\Setting::get('app_download_aktif') === '1'
                    && (\App\Models\Setting::get('app_apk_path') || \App\Models\Setting::get('app_windows_path'));
            @endphp
            @if($appDownloadOn)
            <a href="{{ route('app.download') }}" data-tip="Unduh Aplikasi" class="nav-link flex items-center px-3 py-2.5 {{ request()->routeIs('app.download') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="download" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate">Unduh Aplikasi</span>
            </a>
            @endif


            {{-- Asisten Sekolah: untuk pengguna non-admin dipakai lewat floating ball
                 (lihat akhir layout). Admin tetap punya menu Inbox di sidebar. --}}
            @if($modulOn('chatbot') && $isAdmin)
            <a href="{{ route('chatbot.admin.inbox') }}" data-tip="Chat / Inbox" class="nav-link relative flex items-center px-3 py-2.5 {{ request()->routeIs('chatbot.admin.*') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="message-circle" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate flex-1">Chat / Inbox</span>
                <span x-show="adminChatUnread > 0 && !mini" x-cloak x-text="adminChatUnread > 99 ? '99+' : adminChatUnread"
                      class="ml-auto inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-black text-white shadow-sm shadow-rose-500/30"></span>
                <span x-show="adminChatUnread > 0 && mini" x-cloak
                      class="absolute right-2 top-1.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900"></span>
            </a>
            @endif

            {{-- Tagihan SPP: siswa & orang tua --}}
            @if($modulOn('keuangan') && (auth()->user()?->siswa || auth()->user()?->access === 'orangtua'))
            <a href="{{ route('keuangan.tagihan.index') }}" data-tip="Tagihan SPP" class="nav-link flex items-center px-3 py-2.5 {{ request()->routeIs('keuangan.tagihan.*') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="wallet" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate">Tagihan SPP</span>
            </a>
            @endif

            {{-- Pantau Lokasi: orang tua (anaknya) — riwayat titik absen QR --}}
            @if($modulOn('absensi') && $access === 'orangtua' && \App\Support\PantauLokasi::aktif() && \App\Support\PantauLokasi::canAccess(auth()->user()))
            <a href="{{ route('pantau-lokasi.index') }}" data-tip="Pantau Lokasi" class="nav-link flex items-center px-3 py-2.5 {{ request()->routeIs('pantau-lokasi.*') ? 'active' : '' }}" :class="mini ? 'justify-center' : 'gap-3'">
                <i data-lucide="map-pinned" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                <span x-show="!mini" class="text-sm truncate">Pantau Lokasi</span>
            </a>
            @endif

            {{-- Grup kategori: accordion saat lebar, ikon datar saat ringkas --}}
            @foreach($groups as $gk => $g)
            @php [$glabel, $gicon, $gitems] = $g; @endphp

            {{-- Mode lebar: tombol grup + submenu collapsible --}}
            <div x-show="!mini" class="pt-1">
                <button type="button" @click="toggleGroup('{{ $gk }}')"
                        class="nav-group w-full flex items-center gap-3 px-3 py-2.5 {{ in_array($gk, $activeGroups, true) ? 'has-active' : '' }}">
                    <i data-lucide="{{ $gicon }}" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                    <span class="text-sm font-semibold truncate flex-1 text-left">{{ $glabel }}</span>
                    <span class="flex-shrink-0 transition-transform duration-200 inline-block" :class="openGroup==='{{ $gk }}' ? 'rotate-180' : ''">
                        <i data-lucide="chevron-down" class="w-4 h-4 flex-shrink-0"></i>
                    </span>
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
            <div x-show="mini" x-cloak class="space-y-0.5 pt-1">
                @foreach($gitems as [$iroute, $ipatterns, $iicon, $ilabel])
                <a href="{{ route($iroute) }}" data-tip="{{ $ilabel }}" class="nav-link flex items-center justify-center px-3 py-2.5 {{ request()->routeIs(...$ipatterns) ? 'active' : '' }}">
                    <i data-lucide="{{ $iicon }}" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                </a>
                @endforeach
            </div>
            @endforeach

            {{-- Bantuan: panduan pemakaian dan kanal feedback pengguna --}}
            <div x-show="!mini" class="mt-2 pt-2 border-t border-black/10 dark:border-white/10">
                <button type="button" @click="toggleGroup('bantuan')"
                        class="nav-group w-full flex items-center gap-3 px-3 py-2.5 {{ in_array('bantuan', $activeGroups, true) ? 'has-active' : '' }}">
                    <i data-lucide="life-buoy" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                    <span class="text-sm font-semibold truncate flex-1 text-left">Bantuan</span>
                    <span class="flex-shrink-0 transition-transform duration-200 inline-block" :class="openGroup==='bantuan' ? 'rotate-180' : ''">
                        <i data-lucide="chevron-down" class="w-4 h-4 flex-shrink-0"></i>
                    </span>
                </button>
                <div x-show="openGroup==='bantuan'" x-collapse class="nav-submenu ml-[22px] pl-2.5 mt-0.5 space-y-0.5">
                    <a href="{{ route('pembaruan.riwayat') }}" class="nav-link nav-sublink flex items-center gap-2.5 px-3 py-2 {{ request()->routeIs('pembaruan.riwayat') ? 'active' : '' }}">
                        <i data-lucide="sparkles" class="nav-icon w-4 h-4 flex-shrink-0"></i>
                        <span class="text-[13px] truncate">Info Pembaruan</span>
                    </a>
                    <a href="{{ route('panduan.visual') }}" class="nav-link nav-sublink flex items-center gap-2.5 px-3 py-2 {{ request()->routeIs('panduan.*') ? 'active' : '' }}">
                        <i data-lucide="book-open-check" class="nav-icon w-4 h-4 flex-shrink-0"></i>
                        <span class="text-[13px] truncate">Panduan Visual</span>
                    </a>
                    <a href="{{ route('feedback.index') }}" class="nav-link nav-sublink relative flex items-center gap-2.5 px-3 py-2 {{ request()->routeIs('feedback.*') ? 'active' : '' }}">
                        <i data-lucide="message-square-heart" class="nav-icon w-4 h-4 flex-shrink-0"></i>
                        <span class="text-[13px] truncate flex-1">Saran & Masukan</span>
                        @if($canManageFeedback)
                        <span x-show="feedbackUnread > 0 && !mini" x-cloak x-text="feedbackUnread > 99 ? '99+' : feedbackUnread"
                              :aria-label="feedbackUnread + ' masukan baru'"
                              class="ml-auto inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-black text-white shadow-sm shadow-rose-500/30"></span>
                        <span x-show="feedbackUnread > 0 && mini" x-cloak
                              :aria-label="feedbackUnread + ' masukan baru'"
                              class="absolute right-2 top-1.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900"></span>
                        @endif
                    </a>
                </div>
            </div>
            <div x-show="mini" x-cloak class="mt-2 pt-2 border-t border-black/10 dark:border-white/10 space-y-0.5">
                <a href="{{ route('pembaruan.riwayat') }}" data-tip="Info Pembaruan" class="nav-link flex items-center justify-center px-3 py-2.5 {{ request()->routeIs('pembaruan.riwayat') ? 'active' : '' }}">
                    <i data-lucide="sparkles" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                </a>
                <a href="{{ route('panduan.visual') }}" data-tip="Panduan Visual" class="nav-link flex items-center justify-center px-3 py-2.5 {{ request()->routeIs('panduan.*') ? 'active' : '' }}">
                    <i data-lucide="book-open-check" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                </a>
                <a href="{{ route('feedback.index') }}" data-tip="Saran & Masukan" class="nav-link relative flex items-center justify-center px-3 py-2.5 {{ request()->routeIs('feedback.*') ? 'active' : '' }}">
                    <i data-lucide="message-square-heart" class="nav-icon w-[18px] h-[18px] flex-shrink-0"></i>
                    @if($canManageFeedback)
                    <span x-show="feedbackUnread > 0" x-cloak
                          :aria-label="feedbackUnread + ' masukan baru'"
                          class="absolute right-2 top-1.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900"></span>
                    @endif
                </a>
            </div>
        </nav>

        <button type="button"
                class="sidebar-resize-handle"
                x-show="!mini && !isMobile"
                @pointerdown.prevent="startSidebarResize($event)"
                @dblclick.prevent="toggleCollapse()"
                title="Seret untuk mengubah lebar menu. Klik dua kali untuk ciutkan."></button>

    </aside>
    @endunless

    {{-- ============ MAIN ============ --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden min-h-0">

        @unless($kioskChrome)
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
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-sm text-slate-700 dark:text-slate-100">Notifikasi</span>
                                <button type="button" @click="toggleSound()" :title="soundOn ? 'Suara notifikasi: aktif' : 'Suara notifikasi: nonaktif'" class="grid place-items-center w-6 h-6 rounded-lg hover:bg-black/5 text-slate-400">
                                    <i :data-lucide="soundOn ? 'volume-2' : 'volume-x'" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
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
                                         :style="{ background: notifColor(n) }">
                                        <i :data-lucide="notifIcon(n)" class="w-4 h-4"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <template x-if="n.data.judul || n.data.title">
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100 leading-normal" x-text="n.data.judul || n.data.title"></p>
                                        </template>
                                        <p class="text-xs text-slate-700 dark:text-slate-200 leading-normal" x-text="notifText(n)"></p>
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
                    $akunNama = auth()->user() ? auth()->user()->displayName() : 'User';
                    $akunDepan = \Illuminate\Support\Str::of($akunNama)->explode(' ')->first();
                    $akunInisial = auth()->user() ? auth()->user()->initial() : 'U';
                @endphp
                {{-- Dropdown profil / akun --}}
                <div class="relative" x-data="{ pOpen:false }" @keydown.escape.window="pOpen=false">
                    <button type="button" @click="pOpen=!pOpen" class="flex items-center gap-2 rounded-full pl-2.5 pr-1 py-1 hover:bg-black/5 transition">
                        <span class="hidden sm:block text-sm font-semibold text-slate-600 dark:text-slate-300 max-w-[120px] truncate">{{ $akunDepan }}</span>
                        <span class="w-9 h-9 rounded-full ring-2 ring-white shadow overflow-hidden grid place-items-center text-white text-sm font-bold flex-shrink-0" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
                            @if($myFace)<img src="{{ $myFace }}" class="w-full h-full object-cover" alt="profil">@else{{ $akunInisial }}@endif
                        </span>
                        <span class="hidden sm:block transition-transform duration-200" :class="pOpen ? 'rotate-180' : ''">
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                        </span>
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
        @endunless

        <main class="flex-1 overflow-y-auto px-5 md:px-7 py-4 flex flex-col">
            @include('partials.langganan-banner')
            <div class="anim-fade flex-1">@yield('content')</div>
            {{-- Footer — selalu menempel di bawah berkat mt-auto (konten flex-1 mendorongnya turun) --}}
            @unless(View::hasSection('hide_page_footer'))
            <footer class="mt-auto pt-4 border-t border-slate-200/70 dark:border-slate-700/60 text-center text-xs text-slate-400">
                &copy; {{ date('Y') }} <span class="font-semibold text-slate-500 dark:text-slate-400">{{ $namaSekolah ?? 'Edutive' }}</span>. Seluruh hak cipta dilindungi.
            </footer>
            @endunless
        </main>
    </div>
</div>

        {{-- SIMS-NET System Ticker Bar (Integrated for all roles, displaying real dashboard statistics) — disembunyikan di mode kiosk --}}
        @unless($kioskChrome)
        @php
            // Angka ticker diambil dari cache (App\Support\TickerStats) — menghindari
            // ~15 query agregat di setiap load halaman. Penyaringan per-role di bawah
            // murni operasi array (tanpa query tambahan).
            $role = auth()->user()->access ?? '';
            $tickerFlags = \App\Support\TickerStats::flags($role);
            $showManagementStats = $tickerFlags['management'];
            $showTeacherStats = $tickerFlags['teacher'];
            $showStudentStats = $tickerFlags['student'];
            $showOnlineStats = $tickerFlags['online'];

            $tickerData = \App\Support\TickerStats::raw();
            $tickerSiswa = $tickerData['siswa'];
            $tickerL = $tickerData['siswaL'];
            $tickerP = $tickerData['siswaP'];
            $tickerGuru = $tickerData['guru'];
            $tickerKelas = $tickerData['kelas'];
            $tickerMapel = $tickerData['mapel'];
            $tickerSemesterLabel = $tickerData['semesterLabel'];
            $tickerAset = $tickerData['aset'];
            $tickerKerusakan = $tickerData['kerusakan'];
            $tickerPeminjaman = $tickerData['peminjaman'];
            $tickerOnlineText = \App\Support\TickerStats::onlineText($tickerData);
        @endphp
        <div class="h-8 bg-[#090d16] flex-shrink-0 flex items-center text-[10px] sm:text-[11px] font-mono tracking-wider overflow-hidden border-t border-slate-900 text-slate-400 select-none z-30 shadow-2xl">
            <div class="flex items-center px-3 border-r border-slate-800/80 h-full flex-shrink-0 z-20">
                <span class="bg-amber-500/10 text-amber-500 border border-amber-500/20 font-bold px-2 py-0.5 rounded text-[9px] sm:text-[10px] tracking-wider">
                    SIMS-NET
                </span>
            </div>
            <div class="flex-1 overflow-hidden relative flex items-center">
                <div class="animate-ticker flex whitespace-nowrap gap-12 items-center pl-4 py-1">
                    <div class="flex items-center gap-12">
                        <span class="flex items-center gap-2">
                            <span>SEMESTER:</span>
                            <span class="text-emerald-400 font-bold ticker-val-semester">{{ $tickerSemesterLabel }}</span>
                        </span>
                        
                        @if($showStudentStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>TOTAL SISWA:</span>
                            <span class="text-emerald-400 font-bold ticker-val-siswa">{{ number_format($tickerSiswa) }} ({{ number_format($tickerL) }} L • {{ number_format($tickerP) }} P)</span>
                        </span>
                        @endif

                        @if($showTeacherStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>GURU AKTIF:</span>
                            <span class="text-emerald-400 font-bold ticker-val-guru">{{ number_format($tickerGuru) }} GURU</span>
                        </span>
                        @endif

                        @if($showTeacherStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>ROMBEL:</span>
                            <span class="text-emerald-400 font-bold ticker-val-kelas">{{ number_format($tickerKelas) }} KELAS</span>
                        </span>
                        @endif

                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>KURIKULUM:</span>
                            <span class="text-emerald-400 font-bold ticker-val-mapel">{{ number_format($tickerMapel) }} MAPEL</span>
                        </span>

                        @if($showManagementStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>TOTAL ASET:</span>
                            <span class="text-emerald-400 font-bold ticker-val-aset">{{ number_format($tickerAset) }} UNIT</span>
                        </span>
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>KERUSAKAN TERBUKA:</span>
                            <span class="text-emerald-400 font-bold ticker-val-kerusakan">{{ number_format($tickerKerusakan) }} LAPORAN</span>
                        </span>
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>PEMINJAMAN AKTIF:</span>
                            <span class="text-emerald-400 font-bold ticker-val-peminjaman">{{ number_format($tickerPeminjaman) }} TRANSAKSI</span>
                        </span>
                        @endif

                        @if($showOnlineStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>USER ONLINE:</span>
                            <span class="text-cyan-400 font-bold ticker-val-online">{{ $tickerOnlineText }}</span>
                        </span>
                        @endif
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2 text-amber-400 font-bold">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                            </span>
                            LIVE SYS
                        </span>
                    </div>
                    <!-- Duplicate for seamless loop scrolling -->
                    <div class="flex items-center gap-12" aria-hidden="true">
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>SEMESTER:</span>
                            <span class="text-emerald-400 font-bold ticker-val-semester">{{ $tickerSemesterLabel }}</span>
                        </span>
                        
                        @if($showStudentStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>TOTAL SISWA:</span>
                            <span class="text-emerald-400 font-bold ticker-val-siswa">{{ number_format($tickerSiswa) }} ({{ number_format($tickerL) }} L • {{ number_format($tickerP) }} P)</span>
                        </span>
                        @endif

                        @if($showTeacherStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>GURU AKTIF:</span>
                            <span class="text-emerald-400 font-bold ticker-val-guru">{{ number_format($tickerGuru) }} GURU</span>
                        </span>
                        @endif

                        @if($showTeacherStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>ROMBEL:</span>
                            <span class="text-emerald-400 font-bold ticker-val-kelas">{{ number_format($tickerKelas) }} KELAS</span>
                        </span>
                        @endif

                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>KURIKULUM:</span>
                            <span class="text-emerald-400 font-bold ticker-val-mapel">{{ number_format($tickerMapel) }} MAPEL</span>
                        </span>

                        @if($showManagementStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>TOTAL ASET:</span>
                            <span class="text-emerald-400 font-bold ticker-val-aset">{{ number_format($tickerAset) }} UNIT</span>
                        </span>
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>KERUSAKAN TERBUKA:</span>
                            <span class="text-emerald-400 font-bold ticker-val-kerusakan">{{ number_format($tickerKerusakan) }} LAPORAN</span>
                        </span>
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>PEMINJAMAN AKTIF:</span>
                            <span class="text-emerald-400 font-bold ticker-val-peminjaman">{{ number_format($tickerPeminjaman) }} TRANSAKSI</span>
                        </span>
                        @endif

                        @if($showOnlineStats)
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2">
                            <span>USER ONLINE:</span>
                            <span class="text-cyan-400 font-bold ticker-val-online">{{ $tickerOnlineText }}</span>
                        </span>
                        @endif
                        <span class="text-slate-700">•</span>
                        <span class="flex items-center gap-2 text-amber-400 font-bold">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                            </span>
                            LIVE SYS
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @endunless
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
@php
    // Satu sistem notifikasi: dukung dua konvensi key flash —
    // 'success'/'error' (umum SIMS) & 'sukses'/'gagal' (modul Sarpras).
    // Hanya tampil bila benar-benar ADA teks (cegah toast judul tanpa keterangan).
    $toastSukses = trim((string) (session('success') ?? session('sukses') ?? ''));
    $toastGagal  = trim((string) (session('error') ?? session('gagal') ?? ''));
@endphp
<div class="fixed bottom-14 right-6 z-[9999] space-y-2" id="toastWrap">
    @if($toastSukses !== '')
    <div class="toast-item card !rounded-2xl border-l-4 !border-l-emerald-500 px-4 py-3 flex items-start gap-3 min-w-[290px] max-w-md shadow-xl" style="animation:slideToast .35s both">
        <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900 grid place-items-center flex-shrink-0"><i data-lucide="check" class="w-4 h-4 text-emerald-600"></i></div>
        <div class="flex-1 min-w-0"><p class="font-bold text-sm text-slate-800 dark:text-slate-100">Berhasil</p><p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 break-words">{{ $toastSukses }}</p></div>
        <button onclick="this.closest('.toast-item').remove()" class="text-slate-300 hover:text-slate-500"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
    @endif
    @if($toastGagal !== '' || $errors->any())
    <div class="toast-item card !rounded-2xl border-l-4 !border-l-rose-500 px-4 py-3 flex items-start gap-3 min-w-[290px] max-w-md shadow-xl" style="animation:slideToast .35s both">
        <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-900 grid place-items-center flex-shrink-0"><i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i></div>
        <div class="flex-1 min-w-0"><p class="font-bold text-sm text-slate-800 dark:text-slate-100">Terjadi Kesalahan</p>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 space-y-0.5">@if($toastGagal !== '')<p>{{ $toastGagal }}</p>@endif @foreach($errors->all() as $err)<p>{{ $err }}</p>@endforeach</div>
        </div>
        <button onclick="this.closest('.toast-item').remove()" class="text-slate-300 hover:text-slate-500"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
    @endif
</div>

@if(\App\Support\ModulAktif::aktif('chatbot') && in_array($access, ['siswa', 'orangtua']) && !$kioskChrome)
{{-- ─── Floating Asisten Sekolah ─────────────────────────────────────────────
     Bola mengambang khusus SISWA & ORANG TUA untuk menghubungi admin manusia
     (handoff). Staf & admin memakai widget AsistenAI, bukan ini — agar tiap
     pengguna hanya melihat SATU bola sesuai kebutuhannya. Klik membuka panel
     chat yang meng-embed /chatbot via iframe; panel mengirim 'chatfab:close'
     lewat postMessage saat tombol tutup di dalam widget ditekan. --}}
<div x-data="chatFab()" x-cloak class="fixed bottom-11 right-4 z-[9990] flex flex-col items-end gap-3 print:hidden">
    {{-- Panel chat --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         class="fixed inset-0 z-[9990] w-screen h-screen h-[100dvh] origin-bottom-right overflow-hidden bg-white dark:bg-slate-900
                sm:static sm:inset-auto sm:w-[380px] sm:h-[600px] sm:max-h-[80vh] sm:rounded-2xl sm:shadow-2xl sm:ring-1 sm:ring-slate-200 sm:dark:ring-slate-700">
        <iframe x-ref="frame" :src="loaded ? src : 'about:blank'"
                class="w-full h-full border-0" title="Asisten Sekolah"></iframe>
    </div>

    {{-- Bola pemicu (disembunyikan di mobile saat panel terbuka karena widget tampil fullscreen) --}}
    <button type="button" @click="toggle()" :aria-expanded="open"
            :class="open ? 'hidden sm:grid' : 'grid'"
            class="relative h-12 w-12 rounded-full bg-gradient-to-br from-primary to-primary-700 text-white shadow-lg shadow-primary/30 place-items-center hover:scale-105 active:scale-95 transition focus:outline-none focus:ring-4 focus:ring-primary/40"
            title="Asisten Sekolah">
        {{-- Ikon chat (saat tertutup) — Lucide message-circle-more, seimbang & ter-center --}}
        <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>
            <path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/>
        </svg>
        {{-- Ikon tutup (saat terbuka) --}}
        <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        {{-- Badge jumlah pesan belum dibaca (hanya saat panel tertutup) --}}
        <span x-show="!open && unread > 0" x-cloak x-text="unread > 9 ? '9+' : unread"
              class="absolute -top-0.5 -right-0.5 min-w-[20px] h-5 px-1 rounded-full bg-rose-500 text-white text-[11px] font-bold grid place-items-center ring-2 ring-white shadow"></span>
    </button>
</div>
<script>
    function chatFab() {
        return {
            open: false,
            loaded: false,
            unread: 0,
            src: '{{ route('chatbot.show') }}',
            unreadUrl: '{{ route('chatbot.unread') }}',
            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.loaded = true;   // lazy-load iframe sekali saja
                    this.unread = 0;      // membuka panel = menandai sudah dilihat
                } else {
                    this.poll();          // segarkan badge saat ditutup
                }
            },
            async poll() {
                if (this.open) return;    // saat terbuka, widget mengurus state-nya sendiri
                try {
                    const r = await fetch(this.unreadUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (r.ok) { const d = await r.json(); this.unread = d.unread || 0; }
                } catch (_) { /* offline sesaat → biarkan badge apa adanya */ }
            },
            init() {
                // Widget (di dalam iframe) menekan tombol tutup → postMessage ke parent.
                window.addEventListener('message', (e) => {
                    if (e.data === 'chatfab:close') { this.open = false; this.poll(); }
                });
                this.poll();                                   // cek awal saat halaman dibuka
                setInterval(() => this.poll(), 20000);         // polling latar tiap 20 detik
            },
        }
    }
</script>
@endif

{{-- Widget AsistenAI (Fase 2) — STAF & ADMIN saja. Siswa & orang tua tidak
     mendapat AI generatif; mereka memakai chatbot handoff ke admin di atas. --}}
@unless(in_array($access, ['siswa', 'orangtua']))
@include('partials.ai-assistant')
@endunless

@include('partials.whats-new-modal')

<script>
    function appShell() {
        return {
            collapsed: localStorage.getItem('sb_collapsed') === '1',
            sidebarWidth: Math.min(360, Math.max(220, parseInt(localStorage.getItem('sb_width') || '258', 10) || 258)),
            mobileOpen: false,
            isMobile: window.matchMedia('(max-width: 1023px)').matches,
            // "mini" = sidebar ikon-only. Hanya berlaku di desktop; di mobile selalu full.
            get mini(){ return this.collapsed && !this.isMobile; },
            get sidebarStyle(){ return (!this.mini && !this.isMobile) ? 'width:' + this.sidebarWidth + 'px' : ''; },
            avatarZoom: false,
            // Kalau halaman aktif cocok di >1 grup (mis. poin.siswa.index dipakai bareng oleh
            // grup "Poin & Aturan" dan "Wali Kelas" utk user yg punya kedua peran), utamakan
            // grup yang terakhir dibuka manual (localStorage) SELAMA grup itu tetap salah satu
            // yang valid utk halaman ini — supaya klik link di dalam Wali Kelas tidak "meloncat"
            // ke Poin & Aturan begitu saja. Kalau tak ada localStorage yg cocok, pakai match pertama.
            openGroup: (() => {
                const matches = @json($activeGroups ?? []);
                const stored = localStorage.getItem('sb_group');
                if (stored && matches.includes(stored)) return stored;
                return matches[0] || '';
            })(),
            toggleGroup(g){ this.openGroup = (this.openGroup === g ? '' : g); localStorage.setItem('sb_group', this.openGroup); this.$nextTick(()=>lucide.createIcons()); },
            darkMode: (localStorage.getItem('theme_mode') ?? '{{ $pref->theme_mode ?? 'light' }}') === 'dark',
            uiStyle: '{{ $pref->ui_style ?? 'soft' }}',
            adminChatUnread: 0,
            adminChatBadgeTimer: null,
            pengumumanUnread: 0,
            feedbackUnread: {{ (int) $feedbackUnreadCount }},
            feedbackBadgeTimer: null,
            toggleCollapse(){ this.collapsed=!this.collapsed; localStorage.setItem('sb_collapsed', this.collapsed?'1':'0'); this.$nextTick(()=>lucide.createIcons()); },
            startSidebarResize(e){
                if (this.isMobile) return;
                this.collapsed = false;
                localStorage.setItem('sb_collapsed', '0');
                const startX = e.clientX;
                const startW = this.sidebarWidth;
                const move = (ev) => {
                    const next = Math.min(360, Math.max(220, startW + ev.clientX - startX));
                    this.sidebarWidth = next;
                };
                const up = () => {
                    localStorage.setItem('sb_width', String(this.sidebarWidth));
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', up);
                };
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up, { once:true });
            },
            toggleDark(){ this.darkMode=!this.darkMode; localStorage.setItem('theme_mode', this.darkMode?'dark':'light'); },
            toggleStyle(){
                this.uiStyle = this.uiStyle === 'soft' ? 'corporate' : 'soft';
                setStyle(this.uiStyle);
                fetch('{{ route('profile.style') }}', { method:'POST', headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content') }, body: JSON.stringify({ ui_style: this.uiStyle }) })
                    .then(()=> showToast('Gaya: ' + (this.uiStyle==='soft'?'Soft':'Analyzer')));
                this.$nextTick(()=>lucide.createIcons());
            },
            init(){
                const mq = window.matchMedia('(max-width: 1023px)');
                const sync = () => { this.isMobile = mq.matches; if (this.isMobile) this.mobileOpen = false; };
                mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync);
                this.initNavTips();
                this.initAdminChatBadge();
                this.initFeedbackBadge();
                // Badge menu Pengumuman disuplai dari poll dropdown notifikasi
                // (tanpa polling tambahan) via event 'notif-updated'.
                window.addEventListener('notif-updated', (e) => {
                    this.pengumumanUnread = Number(e.detail?.unreadPengumuman || 0);
                });
                this.$nextTick(()=>lucide.createIcons());
            },
            initAdminChatBadge(){
                @if($isAdmin)
                const fetchBadge = async () => {
                    try {
                        const response = await fetch('{{ route('chatbot.admin.queue') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        this.adminChatUnread = Math.max(Number(data.unread_count || 0), Number(data.waiting_count || 0));
                    } catch (_) {}
                };
                fetchBadge();
                if (!this.adminChatBadgeTimer) this.adminChatBadgeTimer = setInterval(fetchBadge, 20000);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') fetchBadge();
                });
                @endif
            },
            initFeedbackBadge(){
                @if($canManageFeedback)
                const fetchBadge = async () => {
                    try {
                        const response = await fetch('{{ route('feedback.badge') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        this.feedbackUnread = Number(data.new_count || 0);
                    } catch (_) {}
                };
                fetchBadge();
                if (!this.feedbackBadgeTimer) this.feedbackBadgeTimer = setInterval(fetchBadge, 20000);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') fetchBadge();
                });
                @endif
            },
            // Tooltip melayang utk ikon sidebar saat mode mini (anti-terpotong overflow).
            initNavTips(){
                let tip;
                const sel = '.sidebar a[data-tip], .sidebar button[data-tip]';
                const hide = () => { if (tip) tip.classList.remove('show'); };
                const show = (el) => {
                    const aside = el.closest('aside');
                    if (!aside || aside.offsetWidth > 120) { hide(); return; }   // hanya saat sidebar ikon
                    if (!tip) { tip = document.createElement('div'); tip.className = 'sb-tip'; document.body.appendChild(tip); }
                    tip.textContent = el.getAttribute('data-tip');
                    const r = el.getBoundingClientRect();
                    tip.style.top  = (r.top + r.height / 2) + 'px';
                    tip.style.left = (r.right + 12) + 'px';
                    tip.classList.add('show');
                };
                document.addEventListener('mouseover', (e) => { const el = e.target.closest(sel); if (el) show(el); });
                document.addEventListener('mouseout',  (e) => { if (e.target.closest(sel)) hide(); });
                document.addEventListener('click',     (e) => { if (e.target.closest(sel)) hide(); });
            }
        }
    }
    function notificationDropdown() {
        return {
            nOpen: false,
            notifications: [],
            unreadCount: 0,
            prevUnread: null,          // null = belum pernah fetch (hindari bunyi saat load awal)
            soundOn: true,
            audio: null,
            init() {
                // Preferensi suara per-perangkat (localStorage), default aktif.
                this.soundOn = localStorage.getItem('notifSound') !== 'off';
                try {
                    this.audio = new Audio('{{ asset('sounds/notif-sims.wav') }}');
                    this.audio.preload = 'auto';
                    this.audio.volume = 0.6;
                } catch (_) { this.audio = null; }
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
                        // Bunyikan ringtone bila jumlah belum-dibaca bertambah
                        // (bukan pada muat pertama, dan hanya bila suara aktif).
                        if (this.prevUnread !== null && data.unreadCount > this.prevUnread) {
                            this.playSound();
                        }
                        this.prevUnread = data.unreadCount;
                        this.unreadCount = data.unreadCount;
                        // Umpankan hitung pengumuman ke badge menu sidebar.
                        window.dispatchEvent(new CustomEvent('notif-updated', {
                            detail: { unreadPengumuman: Number(data.unreadPengumuman || 0) }
                        }));
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                    }
                } catch (e) {
                    console.error("Error fetching notifications:", e);
                }
            },
            playSound() {
                if (!this.soundOn || !this.audio) return;
                try {
                    this.audio.currentTime = 0;
                    // Autoplay bisa ditolak sebelum ada interaksi user → abaikan diam-diam.
                    this.audio.play().catch(() => {});
                } catch (_) { /* noop */ }
            },
            toggleSound() {
                this.soundOn = !this.soundOn;
                localStorage.setItem('notifSound', this.soundOn ? 'on' : 'off');
                if (this.soundOn) this.playSound();   // umpan balik + "buka kunci" autoplay
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
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
                if (n.data.type === 'pengumuman') {
                    window.location.href = `/pengumuman/${n.data.pengumuman_id}`;
                } else if (n.data.type === 'forum_reply') {
                    window.location.href = `/forum/${n.data.topic_slug}#c-${n.data.comment_id}`;
                } else if (n.data.type === 'classroom_comment') {
                    let url = `/ruang-kelas/${n.data.commentable_type}/${n.data.commentable_id}`;
                    if (n.data.classroom_id) {
                        url += `?class=${n.data.classroom_id}`;
                    }
                    window.location.href = `${url}#c-${n.data.comment_id}`;
                } else if (n.data.type === 'absensi_siswa') {
                    window.location.href = n.data.url || '/dashboard';
                } else if (n.data.type === 'chatbot_inbox') {
                    if (!{{ $isAdmin ? 'true' : 'false' }}) return;
                    window.location.href = n.data.url || '/chatbot/admin/inbox';
                } else if (n.data.type === 'chatbot_admin_reply') {
                    window.location.href = n.data.url || '/chatbot';
                } else if (n.data.url) {
                    window.location.href = n.data.url;   // notifikasi umum (mis. Sarpras)
                }
            },
            // Teks/ikon/warna toleran ke berbagai bentuk notifikasi
            notifText(n) {
                const d = n.data || {};
                return d.message || d.pesan || (d.judul ? '' : 'Notifikasi baru');
            },
            notifIcon(n) {
                const t = (n.data || {}).type;
                if (t === 'pengumuman') return 'megaphone';
                if (t === 'absensi_siswa') return 'clipboard-check';
                if (t === 'forum_reply') return 'messages-square';
                if (t === 'classroom_comment') return 'graduation-cap';
                if (t === 'chatbot_inbox' || t === 'chatbot_admin_reply') return 'message-circle';
                return (n.data && (n.data.url || n.data.laporan_id)) ? 'bell' : 'bell';
            },
            notifColor(n) {
                const t = (n.data || {}).type;
                if (t === 'pengumuman' || t === 'forum_reply') return 'var(--cp)';
                return 'var(--ca)';
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
    // Registrasi token FCM dari Android (WebView memanggil ini setelah login).
    // Pola fetch()+CSRF SAMA seperti simpan tata letak dashboard — tanpa mekanisme baru.
    window.registerFcmToken = function(token, deviceType) {
        if (!token) return;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta || !meta.content) return;
        if (window.__mwFcmRegisterInFlight) return;
        window.__mwFcmRegisterInFlight = true;
        fetch('{{ route('notifications.fcmToken.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': meta.content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ token: token, device_type: deviceType || 'android' }),
        }).then(function (r) {
            return r.text().then(function (text) {
                window.__mwFcmRegisterInFlight = false;
                var j = null;
                try { j = text ? JSON.parse(text) : null; } catch (eParse) {}
                if (r.ok && j && j.ok === true) {
                    if (window.AndroidFcm && typeof AndroidFcm.onTokenRegistered === 'function') {
                        AndroidFcm.onTokenRegistered();
                    }
                } else if (window.AndroidFcm && typeof AndroidFcm.onRegisterResult === 'function') {
                    AndroidFcm.onRegisterResult(0, r.status, (text || '').substring(0, 120));
                }
            });
        }).catch(function (e) {
            window.__mwFcmRegisterInFlight = false;
            console.error('registerFcmToken gagal:', e);
        });
    };
    // WebView Android: setelah login, minta token dari native lalu simpan ke user_fcm_tokens
    // untuk user yang sedang login (termasuk saat ganti akun).
    (function syncAndroidFcmToken() {
        function run() {
            if (!window.AndroidFcm || typeof AndroidFcm.getToken !== 'function') return;
            var token = '';
            try { token = AndroidFcm.getToken() || ''; } catch (e) { return; }
            if (token) window.registerFcmToken(token, 'android');
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
        setTimeout(run, 1500);
        setTimeout(run, 4000);
        // Saat logout, bersihkan flag di Android + hapus baris token user ini.
        document.querySelectorAll('form[action*="logout"]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var token = '';
                try {
                    if (window.AndroidFcm && typeof AndroidFcm.getToken === 'function') {
                        token = AndroidFcm.getToken() || '';
                    }
                } catch (e) {}
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (token && meta && meta.content) {
                    try {
                        fetch('{{ route('notifications.fcmToken.destroy') }}', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': meta.content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ token: token }),
                            keepalive: true,
                        });
                    } catch (e2) {}
                }
                try {
                    if (window.AndroidFcm && typeof AndroidFcm.onLoggedOut === 'function') {
                        AndroidFcm.onLoggedOut();
                    }
                } catch (e3) {}
            });
        });
    })();
    jconfirm.defaults = { theme:'material', animation:'scale', closeIcon:true, backgroundDismiss:true, useBootstrap:false, boxWidth:'420px' };
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

        @if(session()->has('reset_account'))
        @php $ra = session('reset_account'); @endphp
        $.confirm({
            title: '🔑 Password Berhasil Direset',
            content: `
                <div class="space-y-3.5 text-left text-slate-600 dark:text-slate-300">
                    <p class="text-sm">Berikut kredensial baru untuk <strong>{{ $ra['name'] ?? '' }}</strong> ({{ $ra['role'] ?? '' }}):</p>
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Username</span>
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-200 select-all">{{ $ra['username'] ?? '' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">Password Baru</span>
                            <span class="font-mono font-bold text-amber-600 dark:text-amber-400 select-all">{{ $ra['password'] ?? '' }}</span>
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
                        const text = "Username: {{ $ra['username'] ?? '' }}\nPassword: {{ $ra['password'] ?? '' }}";
                        navigator.clipboard.writeText(text).then(() => {
                            showToast('Kredensial berhasil disalin!');
                        });
                        return false;
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

    // Realtime update for footer ticker statistics from the database
    document.addEventListener('DOMContentLoaded', () => {
        const updateTickerStats = async () => {
            try {
                const response = await fetch('{{ route('dashboard.ticker-stats') }}');
                if (response.ok) {
                    const data = await response.json();
                    
                    // Update all ticker elements by class name (for both original and duplicated elements)
                    ['semester', 'siswa', 'guru', 'kelas', 'mapel', 'aset', 'kerusakan', 'peminjaman', 'online'].forEach(key => {
                        const val = data[key];
                        document.querySelectorAll(`.ticker-val-${key}`).forEach(el => {
                            el.innerHTML = val;
                        });
                    });
                }
            } catch (e) {
                console.error("Error updating ticker stats:", e);
            }
        };

        // Update stats periodically every 20 seconds
        setInterval(updateTickerStats, 20000);
    });
</script>

@stack('scripts')
</body>
</html>
