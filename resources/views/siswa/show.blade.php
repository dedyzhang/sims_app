@extends('layouts.app')
@section('title', 'Detail Siswa')

@section('content')
@php $breadcrumbs = [['label'=>'Data Siswa','url'=>route('siswa.index')], ['label'=>$siswa->nama,'url'=>'#']]; @endphp

<div class="max-w-4xl mx-auto space-y-5">

    {{-- Hero --}}
    @php $heroGrad = $siswa->jk==='L' ? 'var(--cp),var(--cps) 55%,var(--ca)' : '#ec4899,#db2777 60%,var(--ca)'; @endphp
    <div class="relative overflow-hidden rounded-2xl shadow-lg" style="background:linear-gradient(120deg,{{ $heroGrad }})">
        <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
        <div class="absolute right-24 -bottom-10 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="absolute top-4 right-4 flex gap-2 z-20">
            <a href="{{ route('siswa.edit', $siswa->uuid) }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 text-white text-xs font-semibold transition backdrop-blur">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
            </a>
            <a href="{{ route('siswa.index') }}" class="grid place-items-center w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 text-white transition backdrop-blur">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="relative z-10 px-6 py-7 flex items-center gap-4">
            <div class="w-20 h-20 rounded-2xl grid place-items-center text-3xl font-black flex-shrink-0 bg-white shadow-lg"
                 style="color:{{ $siswa->jk==='L' ? 'var(--cp)' : '#db2777' }}">
                {{ strtoupper(substr($siswa->nama, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white drop-shadow-sm">{{ $siswa->nama }}</h2>
                <div class="flex items-center gap-2 mt-2 flex-wrap">
                    <span class="badge bg-white/25 text-white backdrop-blur font-mono">NIS {{ $siswa->nis ?? '-' }}</span>
                    @if($siswa->kelas)
                    <span class="badge bg-white/25 text-white backdrop-blur">Kelas {{ $siswa->kelas->tingkat }}{{ $siswa->kelas->kelas }}</span>
                    @else
                    <span class="badge bg-white/25 text-white backdrop-blur">Belum ada kelas</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        {{-- Data Pribadi --}}
        <div class="card p-6">
            <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2"><i data-lucide="id-card" class="w-[18px] h-[18px] text-primary"></i> Data Pribadi</h3>
            <div class="space-y-2.5">
                @foreach([
                    ['NISN', $siswa->nisn ?? '-'],
                    ['Jenis Kelamin', $siswa->jk === 'L' ? 'Laki-laki' : 'Perempuan'],
                    ['Tempat Lahir', $siswa->tempat_lahir ?? '-'],
                    ['Tanggal Lahir', $siswa->tanggal_lahir ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->isoFormat('D MMMM Y') : '-'],
                    ['Agama', $siswa->agama ?? '-'],
                    ['No. HP', $siswa->no_handphone ?? '-'],
                    ['Alamat', $siswa->alamat ?? '-'],
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
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                        <div>
                            <p class="text-xs text-slate-400">Akun Siswa</p>
                            <p class="font-mono font-semibold text-slate-700 dark:text-slate-200 text-sm">{{ $siswa->user?->username ?? '-' }}</p>
                        </div>
                        <form method="POST" action="{{ route('siswa.reset', $siswa->uuid) }}" onsubmit="return confirmAction(this, 'Reset password siswa?')">
                            @csrf
                            <button class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 hover:bg-amber-100 transition">
                                <i data-lucide="key-round" class="w-3 h-3"></i> Reset
                            </button>
                        </form>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                        <div>
                            <p class="text-xs text-slate-400">Akun Orang Tua</p>
                            <p class="font-mono font-semibold text-slate-700 dark:text-slate-200 text-sm">{{ $siswa->orangtua?->user?->username ?? '-' }}</p>
                        </div>
                        <form method="POST" action="{{ route('siswa.resetOrtu', $siswa->uuid) }}" onsubmit="return confirmAction(this, 'Reset password orang tua?')">
                            @csrf
                            <button class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 hover:bg-amber-100 transition">
                                <i data-lucide="key-round" class="w-3 h-3"></i> Reset
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Orang Tua --}}
            <div class="card p-6">
                <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2"><i data-lucide="users-round" class="w-[18px] h-[18px] text-primary"></i> Orang Tua / Wali</h3>
                <div class="space-y-2">
                    @php $adaOrtu = false; @endphp
                    @foreach([
                        ['Ayah', $siswa->nama_ayah, $siswa->no_telp_ayah],
                        ['Ibu', $siswa->nama_ibu, $siswa->no_telp_ibu],
                        ['Wali', $siswa->nama_wali, $siswa->no_telp_wali],
                    ] as [$rel, $nama, $telp])
                    @if($nama)
                    @php $adaOrtu = true; @endphp
                    <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                        <span class="badge bg-primary-50 text-primary">{{ $rel }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-slate-700 dark:text-slate-200 text-sm truncate">{{ $nama }}</p>
                            @if($telp)<p class="text-slate-400 text-xs">{{ $telp }}</p>@endif
                        </div>
                    </div>
                    @endif
                    @endforeach
                    @if(!$adaOrtu)<p class="text-slate-400 text-sm text-center py-3">Data orang tua belum diisi</p>@endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
