@extends('layouts.app')
@section('title', 'Dashboard')

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
    $motif = auth()->user()?->preference?->motif ?? 'botanical';
    $motifIcon = ['botanical'=>'flower-2','ocean'=>'waves','forest'=>'trees','sunset'=>'sunset','robot'=>'bot','space'=>'rocket','minimal'=>'circle'][$motif] ?? 'flower-2';
@endphp

@if(in_array($access, ['superadmin','admin']))
<div class="space-y-6">

    {{-- ===== Top stat strip ===== --}}
    <div class="card p-1.5">
        <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-[#f4efe8] dark:divide-slate-700">
            @foreach([
                ['Total Siswa', $totalSiswa, 'Terdaftar'],
                ['Total Guru', $totalGuru, 'Aktif mengajar'],
                ['Total Kelas', $totalKelas, 'Rombongan belajar'],
            ] as [$label, $val, $sub])
            <div class="px-5 py-4">
                <p class="text-xs text-slate-400 mb-1">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-slate-700 dark:text-slate-100">{{ number_format($val) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $sub }}</p>
            </div>
            @endforeach
            {{-- last cell with mini bar chart --}}
            <div class="px-5 py-4 flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-400 mb-1">Mata Pelajaran</p>
                    <p class="text-2xl font-extrabold text-slate-700 dark:text-slate-100">{{ number_format($totalMapel) }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Kurikulum</p>
                </div>
                <svg width="74" height="44" viewBox="0 0 74 44" class="flex-shrink-0">
                    @foreach([16,24,18,30,40,26,20] as $i => $h)
                    <rect x="{{ $i*10 }}" y="{{ 44-$h }}" width="6" height="{{ $h }}" rx="3"
                          fill="{{ $i==4 ? 'var(--cp)' : 'color-mix(in srgb, var(--cp) 22%, white)' }}"/>
                    @endforeach
                </svg>
            </div>
        </div>
    </div>

    {{-- ===== Quick Overview + Featured ===== --}}
    <div class="grid lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2">
            <h2 class="font-bold text-slate-700 dark:text-slate-200 mb-3 px-1">Ringkasan Cepat</h2>
            <div class="grid sm:grid-cols-3 gap-4 stagger">
                {{-- Card 1: Siswa (area) — warna primary --}}
                <a href="{{ route('siswa.index') }}" class="card card-hover overflow-hidden group"
                   style="background:linear-gradient(160deg, color-mix(in srgb, var(--cp) 22%, white), color-mix(in srgb, var(--cp) 9%, white))">
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-2xl font-extrabold" style="color:color-mix(in srgb, var(--cp) 78%, black)">{{ number_format($totalSiswa) }}</p>
                                <p class="text-sm font-medium" style="color:color-mix(in srgb, var(--cp) 62%, black)">Siswa</p>
                            </div>
                            <span class="grid place-items-center w-7 h-7 rounded-lg bg-white/70 group-hover:bg-white transition" style="color:color-mix(in srgb, var(--cp) 78%, black)"><i data-lucide="arrow-up-right" class="w-4 h-4"></i></span>
                        </div>
                    </div>
                    <svg viewBox="0 0 200 70" class="w-full" preserveAspectRatio="none" style="height:64px">
                        <path d="M0,55 C30,40 50,58 80,38 C110,20 140,46 170,30 C185,22 195,34 200,30 L200,70 L0,70 Z" fill="var(--cp)" opacity=".5"/>
                        <path d="M0,60 C40,48 60,62 95,46 C130,32 160,52 200,40 L200,70 L0,70 Z" fill="var(--cp)" opacity=".7"/>
                    </svg>
                </a>
                {{-- Card 2: Guru (line) — warna secondary --}}
                <a href="{{ route('guru.index') }}" class="card card-hover overflow-hidden group"
                   style="background:linear-gradient(160deg, color-mix(in srgb, var(--cps) 24%, white), color-mix(in srgb, var(--cps) 10%, white))">
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-2xl font-extrabold" style="color:color-mix(in srgb, var(--cps) 80%, black)">{{ number_format($totalGuru) }}</p>
                                <p class="text-sm font-medium" style="color:color-mix(in srgb, var(--cps) 64%, black)">Guru</p>
                            </div>
                            <span class="grid place-items-center w-7 h-7 rounded-lg bg-white/70 group-hover:bg-white transition" style="color:color-mix(in srgb, var(--cps) 80%, black)"><i data-lucide="arrow-up-right" class="w-4 h-4"></i></span>
                        </div>
                    </div>
                    <svg viewBox="0 0 200 70" class="w-full" preserveAspectRatio="none" style="height:64px">
                        <polyline points="10,50 50,30 90,42 130,18 170,34 195,22" fill="none" stroke="color-mix(in srgb, var(--cps) 75%, black)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="50" cy="30" r="4" fill="color-mix(in srgb, var(--cps) 75%, black)"/><circle cx="130" cy="18" r="4" fill="color-mix(in srgb, var(--cps) 75%, black)"/>
                    </svg>
                </a>
                {{-- Card 3: Kelas (bars) — warna accent --}}
                <a href="{{ route('kelas.index') }}" class="card card-hover overflow-hidden group"
                   style="background:linear-gradient(160deg, color-mix(in srgb, var(--ca) 24%, white), color-mix(in srgb, var(--ca) 10%, white))">
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-2xl font-extrabold" style="color:color-mix(in srgb, var(--ca) 80%, black)">{{ number_format($totalKelas) }}</p>
                                <p class="text-sm font-medium" style="color:color-mix(in srgb, var(--ca) 64%, black)">Kelas</p>
                            </div>
                            <span class="grid place-items-center w-7 h-7 rounded-lg bg-white/70 group-hover:bg-white transition" style="color:color-mix(in srgb, var(--ca) 80%, black)"><i data-lucide="arrow-up-right" class="w-4 h-4"></i></span>
                        </div>
                    </div>
                    <svg viewBox="0 0 200 70" class="w-full" preserveAspectRatio="none" style="height:64px">
                        @foreach([22,34,28,44,30,50,38,46,32,40] as $i => $h)
                        <rect x="{{ 8+$i*19 }}" y="{{ 70-$h }}" width="10" height="{{ $h }}" rx="3" fill="var(--ca)" opacity="{{ 0.5 + ($h/140) }}"/>
                        @endforeach
                    </svg>
                </a>
            </div>
        </div>

        {{-- Featured card (school / semester) --}}
        <div>
            <h2 class="font-bold text-slate-700 dark:text-slate-200 mb-3 px-1">Tahun Ajaran</h2>
            <div class="relative overflow-hidden rounded-[22px] p-5 text-white h-[calc(100%-2.25rem)] min-h-44 flex flex-col justify-between shadow-lg" style="background:linear-gradient(150deg, var(--cp), color-mix(in srgb, var(--cp) 55%, black))">
                <i data-lucide="{{ $motifIcon }}" class="absolute -right-7 -top-7 w-36 h-36 text-white opacity-20" style="stroke-width:1.2"></i>
                <i data-lucide="{{ $motifIcon }}" class="absolute right-8 bottom-2 w-16 h-16 text-white opacity-15" style="stroke-width:1.2"></i>
                <div class="relative z-10">
                    <div class="w-10 h-10 rounded-xl bg-white/25 grid place-items-center mb-3"><i data-lucide="calendar-days" class="w-5 h-5"></i></div>
                    <p class="text-white/70 text-xs">Semester Aktif</p>
                    <p class="text-2xl font-extrabold">{{ $semester ? 'Semester '.$semester->semester : '—' }}</p>
                    <p class="text-white/80 text-sm">{{ $semester->tahun ?? 'Belum diatur' }}</p>
                </div>
                <a href="{{ route('setting.index') }}" class="relative z-10 inline-flex items-center gap-1.5 text-xs font-semibold bg-white/20 hover:bg-white/30 transition rounded-lg px-3 py-2 w-fit">
                    <i data-lucide="settings-2" class="w-3.5 h-3.5"></i> Kelola
                </a>
            </div>
        </div>
    </div>

    {{-- ===== Recent + Activity ===== --}}
    <div class="grid lg:grid-cols-5 gap-5">
        {{-- Recent students --}}
        <div class="lg:col-span-2 card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-700 dark:text-slate-200">Siswa Terbaru</h2>
                <a href="{{ route('siswa.index') }}" class="text-xs font-semibold text-primary hover:underline">Lihat Semua</a>
            </div>
            <div class="space-y-2">
                @forelse($recent as $s)
                <a href="{{ route('siswa.show', $s->uuid) }}" class="flex items-center gap-3 p-2.5 rounded-2xl hover:bg-primary-50 transition group">
                    <div class="w-10 h-10 rounded-full grid place-items-center text-white font-bold flex-shrink-0" style="background:{{ $s->jk==='L' ? 'linear-gradient(135deg,var(--cp),var(--cps))' : 'linear-gradient(135deg,#ec9aae,#db7793)' }}">
                        {{ strtoupper(substr($s->nama,0,1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $s->nama }}</p>
                        <p class="text-xs text-slate-400">{{ $s->created_at?->diffForHumans() }}</p>
                    </div>
                    @if($s->kelas)<span class="badge bg-primary-50 text-primary">{{ $s->kelas->tingkat }}{{ $s->kelas->kelas }}</span>@endif
                </a>
                @empty
                <p class="text-sm text-slate-400 text-center py-8">Belum ada siswa</p>
                @endforelse
            </div>
        </div>

        {{-- Composition / activity --}}
        <div class="lg:col-span-3 card p-5">
            <div class="flex items-center justify-between mb-1">
                <h2 class="font-bold text-slate-700 dark:text-slate-200">Komposisi Siswa</h2>
                <span class="badge bg-primary-50 text-primary">{{ number_format($totalSiswa) }} total</span>
            </div>
            <p class="text-3xl font-extrabold text-slate-700 dark:text-slate-100 mt-2">{{ number_format($totalSiswa) }}</p>
            <p class="text-sm text-slate-400 mb-4">Distribusi jenis kelamin</p>

            @php $tot = max($totalSiswa,1); $pl = round($siswaL/$tot*100); $pp = 100-$pl; @endphp
            <div class="flex h-4 rounded-full overflow-hidden mb-4 bg-slate-100">
                <div style="width:{{ $pl }}%;background:linear-gradient(90deg,var(--cp),var(--cps))" class="h-full"></div>
                <div style="width:{{ $pp }}%;background:linear-gradient(90deg,#ec9aae,#db7793)" class="h-full"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 rounded-2xl bg-primary-50">
                    <div class="flex items-center gap-2 mb-1"><span class="w-3 h-3 rounded-full" style="background:var(--cp)"></span><span class="text-xs font-semibold text-slate-500">Laki-laki</span></div>
                    <p class="text-xl font-extrabold text-slate-700 dark:text-slate-200">{{ number_format($siswaL) }} <span class="text-sm font-medium text-slate-400">({{ $pl }}%)</span></p>
                </div>
                <div class="p-3 rounded-2xl" style="background:#fce7ec">
                    <div class="flex items-center gap-2 mb-1"><span class="w-3 h-3 rounded-full bg-[#db7793]"></span><span class="text-xs font-semibold text-slate-500">Perempuan</span></div>
                    <p class="text-xl font-extrabold text-slate-700 dark:text-slate-200">{{ number_format($siswaP) }} <span class="text-sm font-medium text-slate-400">({{ $pp }}%)</span></p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-[#f4efe8] dark:border-slate-700">
                <a href="{{ route('siswa.create') }}" class="btn-primary flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Tambah Siswa</a>
                <a href="{{ route('guru.create') }}" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border border-[#ece6df] dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Tambah Guru</a>
                <a href="{{ route('kelas.setKelas') }}" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border border-[#ece6df] dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> Set Kelas</a>
            </div>
        </div>
    </div>
</div>

@else
{{-- ===== Non-admin ===== --}}
<div class="max-w-lg mx-auto mt-10">
    <div class="card p-8 text-center relative overflow-hidden">
        <div class="absolute -right-4 -top-4 opacity-40">@include('partials.flower', ['s'=>90,'c1'=>'var(--cp)','c2'=>'var(--ca)','o'=>'.5'])</div>
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
