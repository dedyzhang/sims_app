@extends('layouts.app')
@section('title', 'Validasi Wajah Terdaftar')

@section('content')
<div class="space-y-5" x-data="{ tab:'siswa', q:'', zoomSrc:null, zoomNama:'' }">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Validasi Wajah Terdaftar</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Lihat foto wajah yang terdaftar untuk memastikan tidak ada yang keliru atau ganda.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('wajah.ganda') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/30 transition">
                <i data-lucide="users" class="w-4 h-4"></i> Cek Wajah Ganda
            </a>
            <a href="{{ route('wajah.takTerbaca') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-amber-200 text-amber-600 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-900/30 transition">
                <i data-lucide="scan-face" class="w-4 h-4"></i> Cek Wajah Tak Terdeteksi
            </a>
            <a href="{{ route('absensi.wajah') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Registrasi Wajah
            </a>
        </div>
    </div>

    {{-- Filter & tabs --}}
    <div class="card p-4 space-y-3">
        <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 w-max">
            <button @click="tab='siswa'" :class="tab==='siswa' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Siswa ({{ $siswas->count() }})</button>
            <button @click="tab='guru'" :class="tab==='guru' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Guru ({{ $gurus->count() }})</button>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <form method="GET" action="{{ route('wajah.galeri') }}" class="flex-1 min-w-48" x-show="tab==='siswa'">
                <label class="form-label">Kelas</label>
                <select name="kelas" class="form-select" onchange="this.form.submit()">
                    @foreach($kelasList as $k)
                    <option value="{{ $k->uuid }}" @selected($selectedKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                    @endforeach
                </select>
            </form>
            <div class="flex-1 min-w-48">
                <label class="form-label">Cari nama</label>
                <input type="text" x-model="q" placeholder="Ketik nama..." class="form-input">
            </div>
        </div>
    </div>

    {{-- ===== Siswa ===== --}}
    <div x-show="tab==='siswa'">
        @if($siswas->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
            <p class="font-medium">Belum ada siswa terdaftar wajah di kelas ini.</p>
        </div>
        @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            @foreach($siswas as $s)
            <div class="card p-3 text-center" x-show="q==='' || @js(strtolower($s->nama)).includes(q.toLowerCase())">
                <div class="aspect-square rounded-xl overflow-hidden mb-2 grid place-items-center text-white text-2xl font-bold" style="background:{{ $s->jk==='L' ? 'var(--cp)' : '#ec4899' }}">
                    @if($s->face_photo)
                    <img src="{{ $s->face_photo_url }}" loading="lazy" class="w-full h-full object-cover cursor-zoom-in" @click="zoomSrc=@js($s->face_photo_url); zoomNama=@js($s->nama)" alt="wajah {{ $s->nama }}">
                    @else
                    <div class="flex flex-col items-center gap-1">
                        <span>{{ strtoupper(substr($s->nama,0,1)) }}</span>
                        <span class="text-[9px] font-normal opacity-80 px-1">foto blm ada</span>
                    </div>
                    @endif
                </div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $s->nama }}</p>
                <p class="text-[11px] text-slate-400">{{ $s->nis }}</p>
            </div>
            @endforeach
        </div>
        <p class="text-xs text-slate-400 mt-3 text-center">Siswa tanpa foto perlu <span class="font-semibold">daftar ulang wajah</span> agar fotonya tersimpan.</p>
        @endif
    </div>

    {{-- ===== Guru ===== --}}
    <div x-show="tab==='guru'" x-cloak>
        @if($gurus->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
            <p class="font-medium">Belum ada guru terdaftar wajah.</p>
        </div>
        @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            @foreach($gurus as $g)
            <div class="card p-3 text-center" x-show="q==='' || @js(strtolower($g->nama)).includes(q.toLowerCase())">
                <div class="aspect-square rounded-xl overflow-hidden mb-2 grid place-items-center text-white text-2xl font-bold" style="background:{{ $g->jk==='P' ? '#ec4899' : 'var(--cp)' }}">
                    @if($g->face_photo)
                    <img src="{{ $g->face_photo_url }}" loading="lazy" class="w-full h-full object-cover cursor-zoom-in" @click="zoomSrc=@js($g->face_photo_url); zoomNama=@js($g->nama)" alt="wajah {{ $g->nama }}">
                    @else
                    <div class="flex flex-col items-center gap-1">
                        <span>{{ strtoupper(substr($g->nama,0,1)) }}</span>
                        <span class="text-[9px] font-normal opacity-80 px-1">foto blm ada</span>
                    </div>
                    @endif
                </div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $g->nama }}</p>
                <p class="text-[11px] text-slate-400">{{ $g->nip ?: $g->nik }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Zoom --}}
    <div x-show="zoomSrc" class="modal-backdrop" style="display:none" @click="zoomSrc=null" x-transition>
        <div class="text-center" @click.stop>
            <img :src="zoomSrc" class="max-h-[72vh] max-w-[90vw] rounded-2xl shadow-2xl ring-4 ring-white/20">
            <p class="text-white mt-3 font-semibold text-lg" x-text="zoomNama"></p>
            <p class="text-white/60 text-xs mt-1">Klik di mana saja untuk menutup</p>
        </div>
    </div>
</div>
@endsection
