@extends('layouts.app')
@section('title', 'RKAS / BOSP')

@section('content')
@php
    $activeReferenceCount = $referenceSets->where('is_active', true)->count();
    $totalPlans = $plans->total();
    $draftCount = $plans->getCollection()->where('status', 'draft')->count();
    $statusLabels = [
        'draft' => 'Draft',
        'validated' => 'Tervalidasi',
        'ready_for_arkas_input' => 'Siap input ARKAS',
        'submitted_in_arkas' => 'Dicatat submitted',
        'approved' => 'Disetujui',
        'revision_required' => 'Perlu revisi',
        'archived' => 'Arsip',
    ];
@endphp

<style>
    .rkas-page .rkas-card {
        background: color-mix(in srgb, var(--cp) 1.5%, #fff);
        border: 1px solid color-mix(in srgb, var(--cp) 9%, #e2e8f0);
        border-radius: 20px;
        box-shadow: 0 8px 24px -20px rgba(15, 23, 42, .28);
    }
    .dark .rkas-page .rkas-card { background: #172033; border-color: rgba(148, 163, 184, .16); }
    .rkas-page .rkas-import-button {
        min-height: 44px;
        padding: .65rem 1rem;
        border-radius: 12px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        background: #4f6ff2;
        color: #fff;
        font-size: .875rem;
        font-weight: 700;
        line-height: 1.2;
        box-shadow: 0 7px 16px -8px rgba(79, 111, 242, .75);
        transition: background .18s ease, box-shadow .18s ease, transform .18s ease;
    }
    .rkas-page .rkas-import-button:hover { background: #3f5fe2; box-shadow: 0 10px 20px -8px rgba(79, 111, 242, .85); transform: translateY(-1px); }
    .rkas-page .rkas-import-button:active { transform: translateY(0); }
    .rkas-page .rkas-import-button:focus-visible { outline: 3px solid rgba(79, 111, 242, .28); outline-offset: 3px; }
    .rkas-page .rkas-import-button > span:first-child {
        width: 22px;
        height: 22px;
        display: grid;
        place-items: center;
        border-radius: 7px;
        background: rgba(255,255,255,.16);
    }
    .dark .rkas-page .rkas-import-button { background: #5c78ff; }
    .dark .rkas-page .rkas-import-button:hover { background: #6b85ff; }
    .rkas-page .rkas-hero {
        background:
            radial-gradient(circle at 85% 15%, rgba(255,255,255,.2), transparent 28%),
            linear-gradient(135deg, color-mix(in srgb, var(--cp) 93%, #172554), color-mix(in srgb, var(--cps) 86%, #0f172a));
    }
</style>

<div class="rkas-page max-w-[1700px] mx-auto space-y-5 pb-8">
    <section class="rkas-hero relative overflow-hidden rounded-[26px] px-6 py-6 lg:px-8 lg:py-7 text-white shadow-lg shadow-indigo-900/10">
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <div class="max-w-3xl">
                <div class="flex items-center gap-3">
                    <span class="grid place-items-center w-12 h-12 rounded-2xl bg-white/15 ring-1 ring-white/20">
                        <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
                    </span>
                    <div>
                        <p class="text-[11px] uppercase tracking-[.16em] font-bold text-white/70">Keuangan sekolah · BOSP</p>
                        <h1 class="text-2xl lg:text-[30px] font-extrabold tracking-tight">RKAS / BOSP</h1>
                    </div>
                </div>
                <p class="mt-4 text-sm lg:text-[15px] leading-6 text-white/80">Susun rencana, validasi aturan, lalu siapkan paket kerja untuk diinput ke ARKAS. Pengesahan resmi tetap dilakukan di ARKAS/MARKAS.</p>
                <div class="flex flex-wrap gap-2 mt-4 text-[11px] font-semibold text-white/80">
                    <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">BOSP saja</span>
                    <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">Referensi versioned</span>
                    <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">Input ARKAS manual</span>
                </div>
            </div>
            @if($canManage)
                <a href="{{ route('keuangan.rkas.create') }}" class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-bold text-indigo-700 shadow-md shadow-indigo-950/15 hover:bg-indigo-50 transition">
                    <i data-lucide="plus" class="w-4 h-4"></i> Buat RKAS
                </a>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="rkas-card p-4 flex items-center gap-3">
            <span class="grid place-items-center w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300"><i data-lucide="layers-3" class="w-5 h-5"></i></span>
            <div><p class="text-[11px] uppercase tracking-wide font-bold text-slate-400">Registry aktif</p><p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">{{ $activeReferenceCount }} <span class="text-xs font-medium text-slate-400">paket referensi</span></p></div>
        </div>
        <div class="rkas-card p-4 flex items-center gap-3">
            <span class="grid place-items-center w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300"><i data-lucide="clipboard-list" class="w-5 h-5"></i></span>
            <div><p class="text-[11px] uppercase tracking-wide font-bold text-slate-400">Rencana tersimpan</p><p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">{{ $totalPlans }} <span class="text-xs font-medium text-slate-400">RKAS</span></p></div>
        </div>
        <div class="rkas-card p-4 flex items-center gap-3">
            <span class="grid place-items-center w-10 h-10 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300"><i data-lucide="pencil-line" class="w-5 h-5"></i></span>
            <div><p class="text-[11px] uppercase tracking-wide font-bold text-slate-400">Perlu dilanjutkan</p><p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">{{ $draftCount }} <span class="text-xs font-medium text-slate-400">draft halaman ini</span></p></div>
        </div>
    </section>

    <section class="rkas-card px-4 py-3.5 flex items-start gap-3 border-l-4 !border-l-amber-400">
        <span class="grid place-items-center w-8 h-8 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300 shrink-0"><i data-lucide="shield-alert" class="w-4 h-4"></i></span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Batas integrasi resmi</p>
            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">SIMS tidak mengunggah ke MARKAS dan tidak menyimpan kredensial ARKAS. Status “submitted” atau “approved” hanya catatan audit setelah proses resmi diverifikasi di ARKAS.</p>
        </div>
    </section>

    @if($activeReferenceCount === 0)
        <section class="rkas-card overflow-hidden border-l-4 !border-l-indigo-500">
            <div class="p-5 lg:p-6 flex flex-col lg:flex-row lg:items-center gap-5">
                <div class="grid place-items-center w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300 shrink-0">
                    <i data-lucide="upload-cloud" class="w-7 h-7"></i>
                </div>
                <div class="flex-1">
                    <p class="text-[11px] uppercase tracking-[.14em] font-bold text-indigo-600 dark:text-indigo-300">Langkah pertama</p>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mt-1">Impor registry referensi sebelum membuat RKAS</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Gunakan paket referensi resmi sesuai tahun, jenjang, dan sumber dana. Setelah tersedia, bendahara dapat memilih kode kegiatan saat menyusun rencana.</p>
                </div>
                @if($canManageReferences)
                    <a href="#impor-referensi" class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition"><i data-lucide="arrow-down" class="w-4 h-4"></i> Buka impor referensi</a>
                @else
                    <span class="shrink-0 inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400"><i data-lucide="clock-3" class="w-4 h-4"></i> Menunggu admin</span>
                @endif
            </div>
        </section>
    @endif

    @if($canManageReferences)
    <details id="impor-referensi" class="rkas-card group overflow-hidden" @if($activeReferenceCount === 0) open @endif>
        <summary class="list-none cursor-pointer px-5 py-4 flex items-center justify-between gap-3 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
            <span class="flex items-center gap-3"><span class="grid place-items-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><i data-lucide="database-zap" class="w-4 h-4"></i></span><span><strong class="block text-sm text-slate-800 dark:text-slate-100">Kelola registry referensi</strong><small class="text-xs text-slate-500">Impor versi resmi baru dan nonaktifkan versi lama</small></span></span>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition group-open:rotate-180"></i>
        </summary>
        <div class="border-t border-slate-100 dark:border-slate-700/70 p-5">
            <form method="POST" action="{{ route('keuangan.rkas.reference.import') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @csrf
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Nama paket<input name="label" required class="form-input mt-1" placeholder="BOSP 2026 Dikdasmen"></label>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Versi ARKAS / Juknis<input name="versi" required class="form-input mt-1" placeholder="ARKAS 4.2.18 / Juknis 2026"></label>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Tahun anggaran<input name="tahun_anggaran" required type="number" min="2020" max="2100" class="form-input mt-1" placeholder="2026"></label>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Jenjang<input name="jenjang" required class="form-input mt-1" placeholder="Dikdasmen / PAUD"></label>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Sumber dana<input name="sumber_dana" required class="form-input mt-1" placeholder="BOSP Reguler"></label>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">URL sumber resmi<input name="source_url" type="url" class="form-input mt-1" placeholder="https://..."></label>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300 md:col-span-2 xl:col-span-3">Berkas referensi (.xlsx, .xls, .csv, .txt)<input name="file" required type="file" accept=".xlsx,.xls,.csv,.txt" class="form-input mt-1"></label>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300 md:col-span-2 xl:col-span-3">Aturan validasi JSON <textarea name="rules_json" rows="3" class="form-input mt-1 font-mono text-xs" placeholder='{"percentages":[{"label":"Buku","components":["Buku"],"min_bps":1000}]}'></textarea></label>
                <div class="md:col-span-2 xl:col-span-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">Kolom minimal: <code>kode_kegiatan</code> dan <code>uraian_kegiatan</code>. Opsional: <code>snp</code>, <code>komponen</code>, <code>kode_rekening_belanja</code>.</p>
                    <button type="submit" class="rkas-import-button" aria-label="Impor registry referensi ARKAS">
                        <span><i data-lucide="upload-cloud" class="w-4 h-4"></i></span>
                        <span>Impor registry</span>
                    </button>
                </div>
            </form>
        </div>
    </details>
    @endif

    <section class="rkas-card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/70 flex items-center justify-between gap-3">
            <div><h2 class="font-bold text-slate-800 dark:text-slate-100">Rencana RKAS</h2><p class="text-xs text-slate-500 mt-0.5">Daftar rencana BOSP yang sedang disusun atau diarsipkan.</p></div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $totalPlans }} rencana</span>
        </div>
        @if($plans->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/70 text-[11px] uppercase tracking-wide text-slate-500">
                        <tr><th class="text-left px-5 py-3">Sekolah / Tahun</th><th class="text-left px-5 py-3">Referensi</th><th class="text-left px-5 py-3">Status</th><th class="text-right px-5 py-3">Pagu</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/70">
                    @foreach($plans as $plan)
                        <tr class="hover:bg-indigo-50/30 dark:hover:bg-indigo-500/5 transition">
                            <td class="px-5 py-4"><a class="font-semibold text-indigo-700 dark:text-indigo-300 hover:underline" href="{{ route('keuangan.rkas.show', $plan) }}">{{ $plan->nama_sekolah }}</a><div class="text-xs text-slate-500 mt-0.5">{{ $plan->tahun_anggaran }} · {{ $plan->sumber_dana }} · {{ $plan->items_count }} item</div></td>
                            <td class="px-5 py-4 text-xs text-slate-600 dark:text-slate-300">{{ $plan->referenceSet?->versi ?? 'Tidak tersedia' }} @if(!$plan->referenceSet?->is_active)<span class="text-amber-600">(nonaktif)</span>@endif</td>
                            <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $plan->validation_errors_count ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' }}">{{ $statusLabels[$plan->status] ?? $plan->status }}</span>@if($plan->validation_errors_count)<div class="text-xs text-rose-600 mt-1">{{ $plan->validation_errors_count }} error</div>@endif</td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-700 dark:text-slate-200">Rp {{ number_format((int)$plan->pagu, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('keuangan.rkas.show', $plan) }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">Buka <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($plans->hasPages()) <div class="border-t border-slate-100 dark:border-slate-700/70 p-4">{{ $plans->links() }}</div> @endif
        @else
            <div class="px-5 py-10 lg:py-12 text-center">
                <span class="mx-auto grid place-items-center w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"><i data-lucide="file-plus-2" class="w-7 h-7"></i></span>
                <h3 class="mt-4 font-bold text-slate-800 dark:text-slate-100">Belum ada rencana RKAS</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-md mx-auto">Mulai dari registry referensi resmi, kemudian susun rencana BOSP pertama untuk sekolah.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    @if($canManage && $activeReferenceCount > 0)<a href="{{ route('keuangan.rkas.create') }}" class="btn-primary inline-flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Buat RKAS pertama</a>@endif
                    @if($canManageReferences && $activeReferenceCount === 0)<a href="#impor-referensi" class="btn-secondary inline-flex items-center gap-2"><i data-lucide="upload" class="w-4 h-4"></i> Impor referensi</a>@endif
                </div>
            </div>
        @endif
    </section>

    <section class="rkas-card p-5">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div><h2 class="font-bold text-slate-800 dark:text-slate-100">Registry referensi</h2><p class="text-xs text-slate-500 mt-0.5">Versi yang dipakai menjadi bagian dari metadata RKAS historis.</p></div>
            <span class="text-xs font-semibold text-slate-400">{{ $referenceSets->count() }} paket</span>
        </div>
        @if($referenceSets->count())
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach($referenceSets as $set)
                    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4 hover:border-indigo-300 dark:hover:border-indigo-500/50 transition">
                        <div class="flex items-start justify-between gap-3"><p class="font-bold text-sm text-slate-800 dark:text-slate-100">{{ $set->label }}</p><span class="shrink-0 text-[11px] font-bold {{ $set->is_active ? 'text-emerald-600' : 'text-slate-400' }}">{{ $set->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                        <p class="text-xs text-slate-500 mt-2">{{ $set->tahun_anggaran }} · {{ $set->jenjang }} · {{ $set->sumber_dana }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $set->versi }} · {{ $set->references()->count() }} kode kegiatan</p>
                        @if($canManageReferences && $set->is_active)<form method="POST" action="{{ route('keuangan.rkas.reference.deactivate', $set) }}" class="mt-3">@csrf<button class="text-xs font-semibold text-rose-600 hover:underline" onclick="return confirm('Nonaktifkan referensi ini?')">Nonaktifkan paket</button></form>@endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 px-5 py-6 text-center">
                <i data-lucide="database" class="w-7 h-7 mx-auto text-slate-300 dark:text-slate-600"></i>
                <p class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Registry belum tersedia</p>
                <p class="mt-1 text-xs text-slate-500">Admin perlu mengimpor paket resmi sebelum bendahara dapat memilih kode kegiatan.</p>
            </div>
        @endif
    </section>
</div>
@endsection
