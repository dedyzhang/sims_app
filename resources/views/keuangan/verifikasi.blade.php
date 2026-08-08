@extends('layouts.app')
@section('title', 'Verifikasi Pembayaran SPP')

@section('content')
<div x-data="{
    tab: @js($activeTab),
    init() {
        if (location.hash === '#validasi') this.tab = 'validasi';
        if (location.hash === '#audit') this.tab = 'audit';
    }
}" class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('keuangan.index', ['ta'=>$ta]) }}" class="hover:underline">Keuangan</a> / Verifikasi</nav>
            <h1 class="page-title">Verifikasi Pembayaran</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Tahun Ajaran {{ $ta }}
                @if($prioritas)<span class="badge bg-indigo-100 dark:bg-indigo-900 text-indigo-700 ml-1">Antrian prioritas</span>@endif
                @if($filterAnomali)<span class="badge bg-amber-100 text-amber-700 ml-1">Filter anomali</span>@endif
            </p>
        </div>
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            @if($prioritas)<input type="hidden" name="prioritas" value="1">@endif
            @if($filterAnomali)<input type="hidden" name="filter" value="anomali">@endif
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama / kelas / NIS…" class="form-input text-sm !pl-9 w-56">
                @if($q)
                <a href="{{ route('keuangan.verifikasi', array_filter(['ta'=>$ta, 'prioritas'=>$prioritas?1:null, 'filter'=>$filterAnomali?'anomali':null])) }}" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500"><i data-lucide="x" class="w-4 h-4"></i></a>
                @endif
            </div>
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Cari</button>
        </form>
    </div>

    @if(session('info'))
    <div class="card p-3 border-l-4 border-indigo-400 text-sm text-indigo-700 dark:text-indigo-300">{{ session('info') }}</div>
    @endif

    {{-- Toolbar prioritas & anomali --}}
    <div class="flex flex-wrap items-center gap-2">
        @if($prioritas)
            <a href="{{ route('keuangan.verifikasi', array_filter(['ta'=>$ta, 'q'=>$q ?: null, 'filter'=>$filterAnomali?'anomali':null])) }}"
               class="text-xs font-semibold text-slate-500 hover:text-primary">← Urutan waktu upload</a>
        @else
            <a href="{{ route('keuangan.verifikasi', array_filter(['ta'=>$ta, 'prioritas'=>1, 'q'=>$q ?: null, 'filter'=>$filterAnomali?'anomali':null])) }}"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-indigo-200 dark:border-indigo-700 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                <i data-lucide="zap" class="w-3.5 h-3.5"></i> Antrian prioritas
            </a>
        @endif
        @if($anomaliCount > 0)
            @if($filterAnomali)
                <a href="{{ route('keuangan.verifikasi', array_filter(['ta'=>$ta, 'prioritas'=>$prioritas?1:null, 'q'=>$q ?: null])) }}"
                   class="text-xs font-semibold text-amber-600 hover:underline">Tampilkan semua ({{ $anomaliCount }} anomali)</a>
            @else
                <a href="{{ route('keuangan.verifikasi', array_filter(['ta'=>$ta, 'prioritas'=>1, 'filter'=>'anomali', 'q'=>$q ?: null])) }}"
                   class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-200 text-amber-700 hover:bg-amber-50">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> {{ $anomaliCount }} anomali
                </a>
            @endif
        @endif
    </div>

    @if($q)
    <p class="text-xs text-slate-500 dark:text-slate-400 -mt-1">Hasil pencarian untuk "<span class="font-semibold text-slate-700 dark:text-slate-200">{{ $q }}</span>"</p>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl">
        <button @click="tab='cek'" type="button"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-sm font-semibold transition"
                :class="tab==='cek' ? 'bg-white dark:bg-slate-700 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'">
            <span class="w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 grid place-items-center text-[11px] font-extrabold">1</span>
            <span class="hidden sm:inline">Verifikasi Bukti</span><span class="sm:hidden">Cek</span>
            @if($menungguCount)<span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">{{ $menungguCount }}</span>@endif
        </button>
        <button @click="tab='validasi'" type="button" id="validasi"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-sm font-semibold transition"
                :class="tab==='validasi' ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'">
            <span class="w-5 h-5 rounded-full bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300 grid place-items-center text-[11px] font-extrabold">2</span>
            <span class="hidden sm:inline">Validasi Rekening Koran</span><span class="sm:hidden">Validasi</span>
            @if($terverifikasiCount)<span class="badge bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300">{{ $terverifikasiCount }}</span>@endif
        </button>
        <button @click="tab='audit'" type="button" id="audit"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-sm font-semibold transition"
                :class="tab==='audit' ? 'bg-white dark:bg-slate-700 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'">
            <i data-lucide="history" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Jejak Audit</span><span class="sm:hidden">Audit</span>
        </button>
    </div>

    {{-- TAB 1: Menunggu cek bukti --}}
    <div x-show="tab==='cek'" x-transition.opacity class="space-y-3">
        <p class="text-xs text-slate-500 dark:text-slate-400 px-1">Periksa bukti transfer yang dikirim orang tua. Setujui untuk menandai <span class="font-semibold text-sky-600 dark:text-sky-400">terverifikasi</span> (lanjut ke validasi rekening koran).</p>
        @forelse($menungguGroups as $item)
            @include('keuangan.partials.verif-group', [
                'group' => $item['group'],
                'mode' => 'verify',
                'priorityScore' => $item['priorityScore'],
                'priorityAlasan' => $item['priorityAlasan'],
                'anomaliMap' => $anomaliMap,
            ])
        @empty
            <div class="card p-10 text-center text-slate-400">
                <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                <p class="text-sm font-medium">Tidak ada bukti baru yang menunggu dicek.</p>
            </div>
        @endforelse
    </div>

    {{-- TAB 2: Menunggu validasi rekening koran --}}
    <div x-show="tab==='validasi'" x-transition.opacity x-cloak class="space-y-3">
        <p class="text-xs text-slate-500 dark:text-slate-400 px-1">Cocokkan dana masuk dengan <span class="font-semibold">rekening koran resmi bank</span>, lalu tandai <span class="font-semibold text-emerald-600 dark:text-emerald-400">lunas</span>.</p>

        @include('keuangan.partials.rekonsiliasi-summary', [
            'rekonsiliasiRingkas' => $rekonsiliasiRingkas,
            'rekonsiliasiAntrian' => $rekonsiliasiAntrian,
            'ta' => $ta,
        ])

        {{-- Import otomatis dari laporan transaksi VA (.txt) bank --}}
        <div class="card p-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-xl bg-sky-100 dark:bg-sky-900 text-sky-600 dark:text-sky-300 grid place-items-center flex-shrink-0"><i data-lucide="file-up" class="w-4 h-4"></i></span>
                <div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Upload Laporan Transaksi VA (.txt)</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Cocok otomatis via 6 digit belakang VA siswa &amp; nominal — tampil pratinjau dulu, bisa diubah manual sebelum diterapkan.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('keuangan.import-rekening-koran.preview') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto">
                @csrf
                <input type="file" name="file" accept=".txt" required
                       class="text-xs w-full sm:w-auto file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:text-xs file:font-semibold hover:file:bg-primary/20">
                <button type="submit" class="btn-primary w-full sm:w-auto px-4 py-2 rounded-xl text-sm font-semibold">Lihat Pratinjau</button>
            </form>
        </div>

        @forelse($terverifikasiGroups as $item)
            @include('keuangan.partials.verif-group', [
                'group' => $item['group'],
                'mode' => 'validate',
                'priorityScore' => $item['priorityScore'],
                'priorityAlasan' => $item['priorityAlasan'],
                'anomaliMap' => $anomaliMap,
            ])
        @empty
            <div class="card p-10 text-center text-slate-400">
                <i data-lucide="landmark" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                <p class="text-sm font-medium">Tidak ada pembayaran yang menunggu validasi rekening koran.</p>
            </div>
        @endforelse
    </div>

    {{-- TAB 3: Jejak audit --}}
    <div x-show="tab==='audit'" x-transition.opacity x-cloak class="space-y-3">
        @include('keuangan.partials.audit-log', ['auditLogs' => $auditLogs])
    </div>
</div>
@endsection
