@extends('layouts.app')
@section('title', 'Detail Guru')

@section('content')
@php $breadcrumbs = [['label'=>'Data Guru','url'=>route('guru.index')], ['label'=>$guru->nama,'url'=>'#']]; @endphp

<div class="max-w-4xl mx-auto space-y-5">

    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-2xl shadow-lg" style="background:linear-gradient(120deg,var(--cp),var(--cps) 55%,var(--ca))" x-data="{ fz:false }">
        <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
        <div class="absolute right-24 -bottom-10 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="absolute top-4 right-4 flex gap-2 z-20">
            <a href="{{ route('guru.edit', $guru->uuid) }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 text-white text-xs font-semibold transition backdrop-blur">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
            </a>
            <a href="{{ route('guru.index') }}" class="grid place-items-center w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 text-white transition backdrop-blur">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="relative z-10 px-6 py-7 flex items-center gap-4">
            <div class="w-20 h-20 rounded-2xl grid place-items-center text-3xl font-black flex-shrink-0 bg-white shadow-lg overflow-hidden {{ $guru->face_photo ? 'cursor-zoom-in' : '' }}" style="color:var(--cp)" @if($guru->face_photo) @click="fz=true" title="Lihat foto" @endif>
                @if($guru->face_photo)<img src="{{ $guru->face_photo_url }}" class="w-full h-full object-cover" alt="Foto {{ $guru->nama }}">@else{{ strtoupper(substr($guru->nama, 0, 1)) }}@endif
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white drop-shadow-sm">{{ $guru->nama }}</h2>
                <div class="flex items-center gap-2 mt-2 flex-wrap">
                    <span class="badge bg-white/30 text-white backdrop-blur font-semibold flex items-center gap-1"><i data-lucide="shield" class="w-3 h-3"></i> {{ \App\Http\Controllers\GuruController::ROLES[$guru->user?->access] ?? \Illuminate\Support\Str::headline($guru->user?->access ?? 'Guru') }}</span>
                    <span class="badge bg-white/25 text-white backdrop-blur font-mono">{{ $guru->nik ?? $guru->nip ?? 'No NIK/NIP' }}</span>
                    @if($guru->walikelas)
                    <span class="badge bg-white/25 text-white backdrop-blur">Wali Kelas {{ $guru->walikelas->kelas?->tingkat }}{{ $guru->walikelas->kelas?->kelas }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if($guru->face_photo)
        {{-- Lightbox foto wajah --}}
        <div x-show="fz" x-cloak @click="fz=false" @keydown.escape.window="fz=false" class="fixed inset-0 z-[10000] flex items-center justify-center p-6" style="display:none; background:rgba(15,12,10,.78); backdrop-filter:blur(6px)">
            <div class="text-center" @click.stop>
                <img src="{{ $guru->face_photo_url }}" class="max-h-[78vh] max-w-[92vw] rounded-3xl shadow-2xl ring-4 ring-white/15" alt="Foto {{ $guru->nama }}">
                <p class="text-white/80 mt-3 font-semibold">{{ $guru->nama }}</p>
                <p class="text-white/50 text-xs">Klik di mana saja untuk menutup</p>
            </div>
        </div>
        @endif
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        {{-- Data Diri --}}
        <div class="card p-6">
            <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2"><i data-lucide="id-card" class="w-[18px] h-[18px] text-primary"></i> Data Diri</h3>
            <div class="space-y-2.5">
                @foreach([
                    ['Jenis Kelamin', $guru->jk === 'L' ? 'Laki-laki' : 'Perempuan'],
                    ['Tempat Lahir', $guru->tempat_lahir ?? '-'],
                    ['Tanggal Lahir', $guru->tanggal_lahir ? \Carbon\Carbon::parse($guru->tanggal_lahir)->isoFormat('D MMMM Y') : '-'],
                    ['Agama', $guru->agama ?? '-'],
                    ['No. Telepon', $guru->no_telp ?? '-'],
                    ['Alamat', $guru->alamat ?? '-'],
                ] as [$label, $val])
                <div class="flex gap-3 text-sm py-1.5 border-b border-slate-50 dark:border-slate-700/50 last:border-0">
                    <span class="text-slate-400 w-32 flex-shrink-0">{{ $label }}</span>
                    <span class="text-slate-700 dark:text-slate-200 font-medium">{{ $val }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-5">
            {{-- Akun --}}
            <div class="card p-6">
                <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2"><i data-lucide="shield" class="w-[18px] h-[18px] text-primary"></i> Akun Login</h3>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 mb-3">
                    <div>
                        <p class="text-xs text-slate-400">Username</p>
                        <p class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $guru->user?->username ?? '-' }}</p>
                    </div>
                    <form method="POST" action="{{ route('guru.reset', $guru->uuid) }}" onsubmit="return confirmAction(this, 'Reset password guru ini?')">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 hover:bg-amber-100 transition">
                            <i data-lucide="key-round" class="w-3.5 h-3.5"></i> Reset
                        </button>
                    </form>
                </div>
            </div>

            {{-- Pelajaran --}}
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="book-open-text" class="w-[18px] h-[18px] text-primary"></i> Pelajaran Diajar</h3>
                    <a href="{{ route('guru.pelajaran', $guru->uuid) }}" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">Kelola <i data-lucide="arrow-right" class="w-3 h-3"></i></a>
                </div>
                <div class="space-y-1.5">
                    @forelse($guru->ngajars as $ngajar)
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/50 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $ngajar->pelajaran?->nama }}</span>
                        <span class="badge bg-primary-50 text-primary">{{ $ngajar->kelas ? $ngajar->kelas->tingkat.$ngajar->kelas->kelas : 'Semua' }}</span>
                    </div>
                    @empty
                    <p class="text-slate-400 text-sm text-center py-3">Belum ada pelajaran yang diajar</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
