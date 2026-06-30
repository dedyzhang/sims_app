@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    /* Ilustrasi kartu — animatif & interaktif */
    .ill { transition: transform .4s cubic-bezier(.2,.8,.2,1); transform-origin: center bottom; will-change: transform; }
    .group:hover .ill { transform: scale(1.07); }
    .ill-bob { animation: illBob 3.2s ease-in-out infinite; }
    .group:hover .ill-bob { animation-duration: 1.6s; }
    @keyframes illBob { 0%,100%{ transform: translateY(0); } 50%{ transform: translateY(-4px); } }
    .ill-glow { transform-box: fill-box; transform-origin: center; animation: illGlow 2s ease-in-out infinite; }
    @keyframes illGlow { 0%,100%{ opacity:.85; transform: scale(1); } 50%{ opacity:1; transform: scale(1.15); } }
    .ill-tw1 { animation: illTw 2.2s ease-in-out infinite; }
    .ill-tw2 { animation: illTw 2.6s ease-in-out infinite .4s; }
    @keyframes illTw { 0%,100%{ opacity:.35; } 50%{ opacity:1; } }
    .ill-flag { transform-box: fill-box; transform-origin: left center; animation: illFlag 1.6s ease-in-out infinite; }
    @keyframes illFlag { 0%,100%{ transform: scaleX(1); } 50%{ transform: scaleX(.74); } }
    .ill-w1,.ill-w2,.ill-w3,.ill-w4 { animation: illWin 2.4s ease-in-out infinite; }
    .ill-w2 { animation-delay:.3s; } .ill-w3 { animation-delay:.6s; } .ill-w4 { animation-delay:.9s; }
    @keyframes illWin { 0%,100%{ opacity:.45; } 50%{ opacity:1; } }
    @media (prefers-reduced-motion: reduce) {
        .ill-bob,.ill-glow,.ill-tw1,.ill-tw2,.ill-flag,.ill-w1,.ill-w2,.ill-w3,.ill-w4 { animation: none; }
    }

    /* ===== Mode atur tata letak (drag & drop) ===== */
    .dash-block { position: relative; }
    .dash-handle { display: none; }
    .dash-editing .dash-block {
        border: 2px dashed color-mix(in srgb, var(--cp) 55%, transparent);
        border-radius: 22px; padding: .55rem; cursor: grab;
        background: color-mix(in srgb, var(--cp) 5%, transparent);
        transition: border-color .2s, background .2s;
    }
    .dash-editing .dash-block:hover { border-color: var(--cp); }
    .dash-editing .dash-handle {
        display: inline-flex; align-items: center; gap: .35rem;
        position: absolute; top: -.7rem; left: 1rem; z-index: 20;
        padding: .15rem .55rem; border-radius: 9999px;
        background: var(--cp); color: #fff; font-size: 11px; font-weight: 700;
        box-shadow: 0 4px 12px rgba(15,12,10,.18); user-select: none;
    }
    .dash-block.sortable-ghost { opacity: .4; }
    .dash-block.sortable-chosen { cursor: grabbing; }
    .dash-block.sortable-drag { box-shadow: 0 18px 40px rgba(15,12,10,.22); }
    /* matikan link saat sedang menyusun supaya tidak salah klik */
    .dash-editing .dash-block a { pointer-events: none; }
</style>
@endpush

@section('content')
@php
    $access = auth()->user()?->access;
    $nama = auth()->user()?->guru?->nama ?? auth()->user()?->siswa?->nama ?? auth()->user()?->username;
    $totalSiswa = $stats['total_siswa'] ?? \App\Models\Siswa::count();
    $totalGuru  = $stats['total_guru'] ?? \App\Models\Guru::count();
    $totalKelas = $stats['total_kelas'] ?? \App\Models\Kelas::count();
    $totalMapel = \App\Models\Pelajaran::count();
    $siswaL = \App\Models\Siswa::where('jk','L')->count();
    $siswaP = \App\Models\Siswa::where('jk','P')->count();
    $recent = \App\Models\Siswa::with('kelas')->latest()->take(4)->get();
    $pref = auth()->user()?->preference()->firstOrCreate(
        ['user_uuid' => auth()->id()],
        \App\Models\UserPreference::defaults()
    );
    $motif = $pref->motif ?? 'botanical';
    $motifIcon = ['botanical'=>'flower-2','ocean'=>'waves','forest'=>'trees','sunset'=>'sunset','robot'=>'bot','space'=>'rocket','minimal'=>'circle'][$motif] ?? 'flower-2';
@endphp

@if(auth()->user()->must_change_password)
<div class="mb-6 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-amber-800 dark:text-amber-200 text-sm">
    <div class="flex gap-3 items-start">
        <i data-lucide="shield-alert" class="w-5 h-5 flex-shrink-0 text-amber-600 dark:text-amber-400 mt-0.5"></i>
        <div>
            <p class="font-bold">Keamanan Akun: Harap Ganti Password Default Anda</p>
            <p class="text-xs text-amber-700/90 dark:text-amber-300/90 mt-0.5 leading-relaxed">
                Akun Anda saat ini menggunakan password default atau baru saja direset. Silakan ganti password lama demi keamanan data Anda.
            </p>
        </div>
    </div>
    <a href="{{ route('ganti.password') }}" class="flex-shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm w-fit">
        <i data-lucide="key-round" class="w-3.5 h-3.5"></i> Ganti Password Sekarang
    </a>
</div>
@endif

@if(in_array($access, ['superadmin','admin']))
@php
    // Urutan blok: pakai preferensi tersimpan dulu, lalu blok baru yang belum tercatat.
    $allBlocks   = \App\Models\UserPreference::DASHBOARD_BLOCKS;
    $savedLayout = is_array($pref->dashboard_layout) ? $pref->dashboard_layout : [];
    $blockOrder  = array_values(array_unique(array_merge(
        array_values(array_intersect($savedLayout, $allBlocks)),
        $allBlocks
    )));
    $blockLabel = [
        'stats'     => 'Statistik',
        'ringkasan' => 'Ringkasan & Tahun Ajaran',
        'sarpras'   => 'Sarana & Prasarana',
        'recent'    => 'Siswa Terbaru & Komposisi',
    ];
@endphp

<div x-data="dashLayout()" :class="{ 'dash-editing': editing }">

    {{-- Toolbar: judul + tombol Tata Letak --}}
    <div class="flex items-center justify-between gap-3 mb-5">
        <h1 class="text-lg font-extrabold text-slate-700 dark:text-slate-100">Dashboard</h1>
        <div class="flex items-center gap-2">
            <span x-show="editing" x-cloak class="hidden sm:inline text-xs text-slate-400">Seret kartu untuk menyusun ulang</span>
            <button type="button" x-show="editing" x-cloak @click="reset()"
                    class="btn-accent inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset
            </button>
            <button type="button" @click="toggle()"
                    class="btn-accent inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                <i data-lucide="layout-dashboard" class="w-3.5 h-3.5" x-show="!editing"></i>
                <i data-lucide="check" class="w-3.5 h-3.5" x-show="editing" x-cloak></i>
                <span x-text="editing ? 'Selesai' : 'Tata Letak'"></span>
            </button>
        </div>
    </div>

    {{-- Grid blok yang bisa di-drag --}}
    <div id="dashGrid" class="space-y-6" x-ref="grid">
        @foreach($blockOrder as $block)
            @if($block === 'sarpras' && ! auth()->user()->can('sarpras.dashboard.lihat'))
                @continue
            @endif
            <div class="dash-block" data-block="{{ $block }}">
                <span class="dash-handle"><i data-lucide="grip-vertical" class="w-3.5 h-3.5"></i> {{ $blockLabel[$block] ?? $block }}</span>
                @includeIf('dashboard.blocks.'.$block)
            </div>
        @endforeach
    </div>
</div>

@else
{{-- ===== Non-admin ===== --}}
<div class="max-w-lg mx-auto mt-10">
    <div class="card p-8 text-center relative overflow-hidden">
        <div class="absolute -right-6 -top-6 opacity-30 pointer-events-none">
            @php
                $motif = $pref->motif ?? 'botanical';
                $cp = strtolower($pref->primary_color ?? '');
            @endphp
            @if($motif === 'botanical')
                @include('partials.flower', ['s'=>110,'c1'=>'var(--cp)','c2'=>'var(--ca)','o'=>'.5'])
            @elseif($motif === 'forest')
                @include('partials.leaf', ['s'=>100,'c'=>'var(--cp)','o'=>'.5'])
            @elseif($motif === 'ocean')
                <svg width="100" height="100" viewBox="0 0 120 70" style="transform: rotate(-15deg);"><g fill="var(--cp)"><path d="M15,35 C45,8 85,8 100,35 C85,62 45,62 15,35 Z"/><path d="M100,35 L120,18 L115,35 L120,52 Z"/></g></svg>
            @elseif($motif === 'nightocean')
                <svg width="90" height="90" viewBox="0 0 120 140" fill="none" stroke="var(--cp)" stroke-width="6" stroke-linecap="round"><circle cx="60" cy="22" r="12"/><line x1="60" y1="34" x2="60" y2="110"/><path d="M24,110 C24,84 60,84 60,110 C60,84 96,84 96,110"/><line x1="42" y1="50" x2="78" y2="50"/></svg>
            @elseif($motif === 'robot')
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="var(--cp)" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            @elseif($motif === 'space')
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="var(--cp)" stroke-width="2"><path d="M4.5 16.5c-1.5 1.25-2.5 3.5-2.5 3.5s2.25-1 3.5-2.5L18.5 4.5 19.5 5.5l-13 13M12 5l2 2M9 8l2 2M6 11l2 2M19 3l2 2"/></svg>
            @elseif($motif === 'sunset')
                <svg width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="30" fill="var(--cp)" opacity=".8"/><path d="M10,70 L90,70 M20,80 L80,80 M35,90 L65,90" stroke="var(--cp)" stroke-width="4"/></svg>
            @elseif($motif === 'minimal' && $cp === '#0f5132')
                {{-- Zamrud Pro (Emerald Diamond) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="var(--cp)" stroke-width="2" stroke-linejoin="round"><polygon points="50,10 90,50 50,90 10,50" opacity=".6" /><polygon points="50,25 75,50 50,75 25,50" opacity=".4" /><polygon points="50,38 62,50 50,62 38,50" fill="var(--ca)" opacity=".8" stroke="none" /><line x1="50" y1="10" x2="50" y2="25" opacity=".5"/><line x1="90" y1="50" x2="75" y2="50" opacity=".5"/><line x1="50" y1="90" x2="50" y2="75" opacity=".5"/><line x1="10" y1="50" x2="25" y2="50" opacity=".5"/></svg>
            @elseif($motif === 'minimal' && $cp === '#3d2314')
                {{-- Kopi Karamel (Coffee Ripples) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="var(--cp)" stroke-dasharray="2 4" stroke-width="2.5"><circle cx="50" cy="50" r="40" opacity=".5" /><circle cx="50" cy="50" r="30" stroke-dasharray="none" opacity=".4" /><circle cx="50" cy="50" r="20" opacity=".3" /><circle cx="50" cy="50" r="10" fill="var(--ca)" opacity=".8" stroke="none" /><line x1="15" y1="15" x2="85" y2="85" stroke-width="1.5" opacity=".3"/></svg>
            @elseif($motif === 'minimal' && $cp === '#212529')
                {{-- Arang Pro (Charcoal Isometric Cube) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" stroke="var(--cp)" stroke-width="2" fill="none" stroke-linejoin="round"><g transform="translate(45, 45)"><polygon points="0,-25 22,-12 22,12 0,25 -22,12 -22,-12" opacity=".6"/><line x1="0" y1="25" x2="0" y2="0" opacity=".6"/><line x1="-22" y1="-12" x2="0" y2="0" opacity=".6"/><line x1="22" y1="-12" x2="0" y2="0" opacity=".6"/></g><g transform="translate(68, 62) scale(0.6)"><polygon points="0,-25 22,-12 22,12 0,25 -22,12 -22,-12" fill="var(--ca)" opacity=".8" stroke="none"/></g><g fill="var(--cps)" opacity=".4"><circle cx="20" cy="20" r="1.5"/><circle cx="40" cy="20" r="1.5"/><circle cx="60" cy="20" r="1.5"/><circle cx="80" cy="20" r="1.5"/><circle cx="20" cy="80" r="1.5"/><circle cx="40" cy="80" r="1.5"/><circle cx="60" cy="80" r="1.5"/></g></svg>
            @else
                {{-- Minimalis Default (#5b7a99) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="var(--cp)" stroke-width="2"><rect x="15" y="15" width="70" height="70" rx="16" opacity=".5" /><circle cx="50" cy="50" r="24" fill="var(--ca)" stroke="none" opacity=".8" /><line x1="15" y1="50" x2="85" y2="50" opacity=".3" /></svg>
            @endif
        </div>
        <div class="w-16 h-16 rounded-2xl mx-auto mb-4 grid place-items-center text-white shadow-lg" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
            <i data-lucide="layout-dashboard" class="w-8 h-8"></i>
        </div>
        <h2 class="text-xl font-extrabold text-slate-700 dark:text-slate-100">Halo, {{ $nama }} 👋</h2>
        <p class="text-sm text-slate-500 mt-1 capitalize">{{ $access }} @if($semester) • Semester {{ $semester->semester }} / {{ $semester->tahun }} @endif</p>
        <p class="text-sm text-slate-400 mt-4">Gunakan menu di sidebar untuk mengakses fitur yang tersedia.</p>
    </div>
</div>
@endif
@endsection

@if(in_array($access, ['superadmin','admin']))
@push('scripts')
<script>
function dashLayout() {
    return {
        editing: false,
        sortable: null,
        saveUrl: '{{ route('dashboard.layout') }}',
        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),

        toggle() {
            this.editing = !this.editing;
            if (this.editing) {
                this.enableSort();
            } else {
                this.save();
            }
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },

        enableSort() {
            if (this.sortable || typeof Sortable === 'undefined') return;
            this.sortable = Sortable.create(this.$refs.grid, {
                animation: 180,
                handle: '.dash-block',
                draggable: '.dash-block',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
            });
        },

        currentOrder() {
            return Array.from(this.$refs.grid.querySelectorAll('.dash-block'))
                        .map(el => el.dataset.block);
        },

        save() {
            const layout = this.currentOrder();
            fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ layout }),
            })
            .then(r => r.json())
            .then(() => window.showToast && window.showToast('Tata letak dashboard tersimpan', 'success'))
            .catch(() => window.showToast && window.showToast('Gagal menyimpan tata letak', 'error'));
        },

        reset() {
            const def = @json($allBlocks);
            const grid = this.$refs.grid;
            def.forEach(key => {
                const el = grid.querySelector('.dash-block[data-block="' + key + '"]');
                if (el) grid.appendChild(el); // urutkan ulang sesuai default
            });
            this.save();
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },
    };
}
</script>
@endpush
@endif
