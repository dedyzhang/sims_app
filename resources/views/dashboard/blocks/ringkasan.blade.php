{{-- ===== Quick Overview + Featured ===== --}}
@php
    $isMaitreyawira = ($pref->primary_color === '#2563eb' && $pref->accent_color === '#f59e0b');
@endphp
<div class="grid lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2">
        <h2 class="font-bold text-slate-700 dark:text-slate-200 mb-3 px-1">Ringkasan Cepat</h2>
        <div class="grid sm:grid-cols-3 gap-4 stagger">
            {{-- Card 1: Siswa (area) — warna primary --}}
            <a href="{{ route('siswa.index') }}" class="card card-hover overflow-hidden group"
               style="@if($isMaitreyawira) background: #ffffff; @else background:linear-gradient(160deg, color-mix(in srgb, var(--cp) 22%, white), color-mix(in srgb, var(--cp) 9%, white)) @endif">
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-2xl font-extrabold" style="color: @if($isMaitreyawira) var(--cp) @else color-mix(in srgb, var(--cp) 78%, black) @endif">{{ number_format($totalSiswa) }}</p>
                            <p class="text-sm font-medium" style="color: @if($isMaitreyawira) #64748b @else color-mix(in srgb, var(--cp) 62%, black) @endif">Siswa</p>
                        </div>
                        <span class="grid place-items-center w-7 h-7 rounded-lg @if($isMaitreyawira) bg-slate-50 dark:bg-slate-800 text-slate-400 group-hover:bg-primary group-hover:text-white @else bg-white/70 group-hover:bg-white @endif transition" style="color: @if(!$isMaitreyawira) color-mix(in srgb, var(--cp) 78%, black) @endif"><i data-lucide="arrow-up-right" class="w-4 h-4"></i></span>
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
               style="@if($isMaitreyawira) background: #ffffff; @else background:linear-gradient(160deg, color-mix(in srgb, var(--cps) 24%, white), color-mix(in srgb, var(--cps) 10%, white)) @endif">
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-2xl font-extrabold" style="color: @if($isMaitreyawira) var(--cps) @else color-mix(in srgb, var(--cps) 80%, black) @endif">{{ number_format($totalGuru) }}</p>
                            <p class="text-sm font-medium" style="color: @if($isMaitreyawira) #64748b @else color-mix(in srgb, var(--cps) 64%, black) @endif">Guru</p>
                        </div>
                        <span class="grid place-items-center w-7 h-7 rounded-lg @if($isMaitreyawira) bg-slate-50 dark:bg-slate-800 text-slate-400 group-hover:bg-primary group-hover:text-white @else bg-white/70 group-hover:bg-white @endif transition" style="color: @if(!$isMaitreyawira) color-mix(in srgb, var(--cps) 80%, black) @endif"><i data-lucide="arrow-up-right" class="w-4 h-4"></i></span>
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
               style="@if($isMaitreyawira) background: #ffffff; @else background:linear-gradient(160deg, color-mix(in srgb, var(--ca) 24%, white), color-mix(in srgb, var(--ca) 10%, white)) @endif">
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-2xl font-extrabold" style="color: @if($isMaitreyawira) var(--ca) @else color-mix(in srgb, var(--ca) 80%, black) @endif">{{ number_format($totalKelas) }}</p>
                            <p class="text-sm font-medium" style="color: @if($isMaitreyawira) #64748b @else color-mix(in srgb, var(--ca) 64%, black) @endif">Kelas</p>
                        </div>
                        <span class="grid place-items-center w-7 h-7 rounded-lg @if($isMaitreyawira) bg-slate-50 dark:bg-slate-800 text-slate-400 group-hover:bg-primary group-hover:text-white @else bg-white/70 group-hover:bg-white @endif transition" style="color: @if(!$isMaitreyawira) color-mix(in srgb, var(--ca) 80%, black) @endif"><i data-lucide="arrow-up-right" class="w-4 h-4"></i></span>
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
