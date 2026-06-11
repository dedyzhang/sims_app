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
    $motif = auth()->user()?->preference?->motif ?? 'botanical';
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
                    {{-- Ilustrasi: Topi Wisuda --}}
                    <svg viewBox="0 0 200 86" class="ill w-full" style="height:84px" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="capBoard" x1="0" y1="0" x2=".25" y2="1">
                                <stop offset="0" stop-color="color-mix(in srgb, var(--cp) 50%, white)"/>
                                <stop offset="1" stop-color="var(--cp)"/>
                            </linearGradient>
                            <linearGradient id="capBase" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="var(--cp)"/>
                                <stop offset="1" stop-color="color-mix(in srgb, var(--cp) 58%, black)"/>
                            </linearGradient>
                            <radialGradient id="capGlow" cx=".5" cy=".5" r=".5">
                                <stop offset="0" stop-color="var(--ca)" stop-opacity=".45"/>
                                <stop offset="1" stop-color="var(--ca)" stop-opacity="0"/>
                            </radialGradient>
                        </defs>
                        <ellipse cx="100" cy="42" rx="50" ry="36" fill="url(#capGlow)"/>
                        <path class="ill-tw1" d="M46,22 l1.8,4 l4,1.8 l-4,1.8 l-1.8,4 l-1.8,-4 l-4,-1.8 l4,-1.8 z" fill="var(--ca)"/>
                        <circle class="ill-tw2" cx="156" cy="22" r="3" fill="var(--cps)" opacity=".7"/>
                        <circle class="ill-tw1" cx="150" cy="46" r="2.2" fill="var(--ca)" opacity=".7"/>
                        <ellipse cx="100" cy="70" rx="34" ry="5" fill="#0f172a" opacity=".08"/>
                        <g class="ill-bob"><g transform="translate(100,42)">
                            <path d="M-18,3 L18,3 L18,15 C18,23 -18,23 -18,15 Z" fill="url(#capBase)"/>
                            <polygon points="0,-16 42,0 0,16 -42,0" fill="url(#capBoard)"/>
                            <polygon points="0,-16 42,0 0,1 -42,0" fill="#fff" opacity=".22"/>
                            <circle r="3.3" fill="#fff"/>
                            <circle r="1.4" fill="var(--cp)"/>
                            <g>
                                <path d="M0,0 L30,3" stroke="var(--ca)" stroke-width="2.4" stroke-linecap="round" fill="none"/>
                                <line x1="30" y1="3" x2="30" y2="16" stroke="var(--ca)" stroke-width="2.4" stroke-linecap="round"/>
                                <g fill="var(--ca)"><circle cx="30" cy="17" r="2.6"/><path d="M26.5,18 L33.5,18 L31.5,28 L28.5,28 Z"/></g>
                                <animateTransform attributeName="transform" type="rotate" values="-8 0 0;9 0 0;-8 0 0" keyTimes="0;0.5;1" dur="2.8s" repeatCount="indefinite" calcMode="spline" keySplines="0.4 0 0.6 1;0.4 0 0.6 1"/>
                            </g>
                        </g></g>
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
                    {{-- Ilustrasi: Buku & Bohlam Ide --}}
                    <svg viewBox="0 0 200 86" class="ill w-full" style="height:84px" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="pageL" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="color-mix(in srgb, var(--cps) 42%, white)"/>
                                <stop offset="1" stop-color="var(--cps)"/>
                            </linearGradient>
                            <linearGradient id="pageR" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="var(--cps)"/>
                                <stop offset="1" stop-color="color-mix(in srgb, var(--cps) 62%, black)"/>
                            </linearGradient>
                            <linearGradient id="bulb" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="color-mix(in srgb, var(--ca) 45%, white)"/>
                                <stop offset="1" stop-color="var(--ca)"/>
                            </linearGradient>
                            <radialGradient id="bulbGlow" cx=".5" cy=".5" r=".5">
                                <stop offset="0" stop-color="var(--ca)" stop-opacity=".6"/>
                                <stop offset="1" stop-color="var(--ca)" stop-opacity="0"/>
                            </radialGradient>
                        </defs>
                        <circle class="ill-glow" cx="100" cy="26" r="24" fill="url(#bulbGlow)"/>
                        <ellipse cx="100" cy="68" rx="44" ry="5" fill="#0f172a" opacity=".08"/>
                        <g transform="translate(100,50)">
                            <path d="M0,2 C-12,-5 -33,-5 -43,1 L-43,19 C-33,14 -12,14 0,20 Z" fill="url(#pageL)"/>
                            <path d="M0,2 C12,-5 33,-5 43,1 L43,19 C33,14 12,14 0,20 Z" fill="url(#pageR)"/>
                            <g stroke="#fff" stroke-width="1" opacity=".4" stroke-linecap="round" fill="none">
                                <path d="M-9,5 C-19,2 -29,2 -35,5"/><path d="M-9,10 C-19,7 -29,7 -35,10"/>
                            </g>
                            <line x1="0" y1="2" x2="0" y2="20" stroke="#fff" stroke-width="1.6" opacity=".6"/>
                        </g>
                        <g class="ill-bob"><g transform="translate(100,24)">
                            <circle r="10" fill="url(#bulb)"/>
                            <path d="M-5.5,-2.5 a5.5,5.5 0 0,1 11,0" fill="none" stroke="#fff" stroke-width="1.5" opacity=".55"/>
                            <rect x="-4.8" y="8.5" width="9.6" height="4" rx="1.6" fill="color-mix(in srgb, var(--cps) 68%, black)"/>
                            <rect x="-3.6" y="12" width="7.2" height="2.6" rx="1.3" fill="color-mix(in srgb, var(--cps) 56%, black)"/>
                            <g class="ill-tw1" stroke="var(--ca)" stroke-width="1.9" stroke-linecap="round">
                                <line x1="0" y1="-15" x2="0" y2="-19.5"/>
                                <line x1="-13" y1="-7.5" x2="-16.5" y2="-10"/>
                                <line x1="13" y1="-7.5" x2="16.5" y2="-10"/>
                                <line x1="-10" y1="3" x2="-13" y2="4.5"/>
                                <line x1="10" y1="3" x2="13" y2="4.5"/>
                            </g>
                        </g></g>
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
                    {{-- Ilustrasi: Gedung Sekolah --}}
                    <svg viewBox="0 0 200 86" class="ill w-full" style="height:84px" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="wall" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="color-mix(in srgb, var(--ca) 42%, white)"/>
                                <stop offset="1" stop-color="var(--ca)"/>
                            </linearGradient>
                            <linearGradient id="roof" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="var(--ca)"/>
                                <stop offset="1" stop-color="color-mix(in srgb, var(--ca) 58%, black)"/>
                            </linearGradient>
                            <radialGradient id="win" cx=".5" cy=".25" r=".9">
                                <stop offset="0" stop-color="#ffffff"/>
                                <stop offset="1" stop-color="color-mix(in srgb, var(--cp) 28%, white)"/>
                            </radialGradient>
                        </defs>
                        <g fill="#ffffff" opacity=".55">
                            <ellipse class="ill-tw2" cx="42" cy="24" rx="13" ry="5"/>
                            <ellipse class="ill-tw1" cx="160" cy="18" rx="10" ry="4"/>
                        </g>
                        <ellipse cx="100" cy="70" rx="44" ry="5" fill="#0f172a" opacity=".08"/>
                        <g class="ill-bob"><g transform="translate(100,4)">
                            <rect x="-37" y="34" width="74" height="34" rx="3" fill="url(#wall)"/>
                            <path d="M-45,35 L0,14 L45,35 Z" fill="url(#roof)"/>
                            <path d="M-45,35 L0,14 L45,35" fill="none" stroke="#fff" stroke-width="1" opacity=".25"/>
                            <line x1="0" y1="14" x2="0" y2="4" stroke="color-mix(in srgb, var(--ca) 58%, black)" stroke-width="1.8"/>
                            <path class="ill-flag" d="M0,4 L12,7.5 L0,11 Z" fill="var(--cp)"/>
                            <path d="M-7,68 L-7,55 A7,7 0 0,1 7,55 L7,68 Z" fill="#ffffff" opacity=".92"/>
                            <circle cx="4" cy="60" r="1.1" fill="var(--ca)"/>
                            <rect class="ill-w1" x="-30" y="40" width="12" height="12" rx="2" fill="url(#win)"/>
                            <rect class="ill-w2" x="18"  y="40" width="12" height="12" rx="2" fill="url(#win)"/>
                            <g stroke="var(--ca)" stroke-width="1" opacity=".45">
                                <line x1="-24" y1="40" x2="-24" y2="52"/><line x1="-30" y1="46" x2="-18" y2="46"/>
                                <line x1="24" y1="40" x2="24" y2="52"/><line x1="18" y1="46" x2="30" y2="46"/>
                            </g>
                        </g></g>
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
