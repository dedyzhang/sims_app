{{--
    Layout modul Sarpras — terintegrasi ke shell SIMS.
    Memakai layout utama SIMS (sidebar, topbar, tema, font, Tailwind CDN).
    View modul mengisi @section('title') dan @section('sarpras_body');
    keduanya dirender di dalam slot konten SIMS oleh layout ini.
--}}
@extends('layouts.app')

@push('styles')
<style>
/* ============================================================
   Dark-mode modul Sarpras.
   View modul ini memakai util Tailwind terang (bg-white, text-gray-*,
   text-slate-800) tanpa varian dark:. Daripada menambah dark: di
   puluhan view, override terscope di .sarpras-scope agar SEMUA halaman
   Sarpras (sekarang & mendatang) otomatis terbaca di mode gelap.
   ============================================================ */
.dark .sarpras-scope { color:#cbd5e1; }
/* Kartu/panel putih → permukaan gelap */
.dark .sarpras-scope .bg-white { background-color:#1e293b !important; }
/* Teks gelap (judul, angka) → terang */
.dark .sarpras-scope .text-gray-900,
.dark .sarpras-scope .text-gray-800,
.dark .sarpras-scope .text-gray-700,
.dark .sarpras-scope .text-slate-900,
.dark .sarpras-scope .text-slate-800,
.dark .sarpras-scope .text-slate-700 { color:#e2e8f0 !important; }
/* Teks abu sedang → abu terang (jangan sentuh slate-600/500 = warna ikon chip) */
.dark .sarpras-scope .text-gray-600,
.dark .sarpras-scope .text-gray-500 { color:#94a3b8 !important; }
.dark .sarpras-scope .text-gray-400,
.dark .sarpras-scope .text-gray-300 { color:#64748b !important; }
/* Permukaan/track abu muda → gelap */
.dark .sarpras-scope .bg-gray-50,
.dark .sarpras-scope .bg-gray-100 { background-color:#334155 !important; }
/* Border default abu → gelap */
.dark .sarpras-scope .border,
.dark .sarpras-scope .border-t,
.dark .sarpras-scope .border-b,
.dark .sarpras-scope .border-l,
.dark .sarpras-scope .border-r,
.dark .sarpras-scope .border-gray-100,
.dark .sarpras-scope .border-gray-200,
.dark .sarpras-scope .border-gray-300,
.dark .sarpras-scope .divide-y > :not([hidden]) ~ :not([hidden]) { border-color:#334155 !important; }
/* Input/select/textarea */
.dark .sarpras-scope input:not([type=color]):not([type=file]),
.dark .sarpras-scope select,
.dark .sarpras-scope textarea { background-color:#0f172a !important; color:#e2e8f0 !important; border-color:#334155 !important; }
.dark .sarpras-scope input::placeholder,
.dark .sarpras-scope textarea::placeholder { color:#64748b !important; }
/* Hover baris tabel */
.dark .sarpras-scope .hover\:bg-gray-50:hover,
.dark .sarpras-scope .hover\:bg-gray-100:hover { background-color:#334155 !important; }
</style>
@endpush

@section('content')
    <div class="sarpras-scope">
    {{-- Header halaman Sarpras --}}
    <div class="flex items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary/10 text-primary">
                <i data-lucide="building-2" class="w-[18px] h-[18px]"></i>
            </span>
            <div>
                <h1 class="text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100 leading-tight">
                    @yield('title', 'Sarpras')
                </h1>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 font-medium">Sarana &amp; Prasarana</p>
            </div>
        </div>
        @hasSection('sarpras_actions')
            <div class="flex items-center gap-2">@yield('sarpras_actions')</div>
        @endif
    </div>

    {{--
        Flash & error: ditangani terpusat oleh toast di layout utama SIMS
        (resources/views/layouts/app.blade.php) yang sudah mendukung key
        'sukses'/'gagal' modul Sarpras. Banner inline dihapus agar tidak dobel.
    --}}

    {{-- Konten halaman modul --}}
    @yield('sarpras_body')
    </div>{{-- /.sarpras-scope --}}
@endsection
