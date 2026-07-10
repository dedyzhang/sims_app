{{-- Hero dashboard utama: Google education theme + tata letak widget --}}
<div class="card card-batik edu-hero relative overflow-hidden p-5 sm:p-6 mb-6">
    <div class="google-accent-bar" aria-hidden="true"></div>
    <div class="edu-hero-grid" aria-hidden="true"></div>
    <div class="relative z-10 grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 pt-1">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="google-chip">
                    <span class="google-dot blue"></span>
                    Dashboard SIMS
                </span>
                <span class="google-chip">
                    <span class="google-dot red"></span>
                    {{ auth()->user()?->roleLabel() ?? ucfirst((string) $access) }}
                </span>
                @if($semester)
                <span class="google-chip">
                    <span class="google-dot yellow"></span>
                    Semester {{ $semester->semester }} / {{ $semester->tahun }}
                </span>
                @endif
            </div>

            <div class="flex items-start gap-3">
                <div class="js-salam-icon edu-salam-icon flex-shrink-0" data-icon-class="w-5 h-5">
                    <i data-lucide="{{ $salamIcon }}" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <p class="google-title text-xs font-black uppercase tracking-wide">
                        <span>S</span><span>I</span><span>M</span><span>S</span><span>k</span><span>u</span>
                    </p>
                    <h1 class="mt-1 text-2xl font-extrabold leading-tight text-slate-900 dark:text-white sm:text-3xl">
                        <span class="js-salam-text">{{ $salam }}</span>, {{ $nama }}
                    </h1>
                    <div class="google-quote-card">
                        <p class="google-quote-heading font-bold" aria-label="Kutipan Hari Ini">
                            <span>Kutipan</span>
                            <span>Hari Ini</span>
                        </p>
                        <p class="google-quote-body font-semibold text-slate-700 dark:text-slate-200">{{ $kataTeks }}</p>
                        @if($kataPenulis)
                            <p class="mt-1 text-xs font-medium text-slate-400 dark:text-slate-500">{{ $kataPenulis }}</p>
                        @endif
                    </div>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300 sm:text-[15px]">
                        Pantau aktivitas akademik, presensi, dan layanan sekolah dari satu dashboard yang bisa disusun sesuai kebutuhan peran.
                    </p>
                </div>
            </div>

            @if($access === 'orangtua' && $siswaWidget)
            <div class="mt-4 inline-flex max-w-full items-center gap-2 rounded-2xl bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 ring-1 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-200 dark:ring-blue-400/20">
                <i data-lucide="user-round" class="w-4 h-4 flex-shrink-0"></i>
                <span class="truncate">Memantau {{ $siswaWidget['siswa']->nama }} - Kelas {{ $siswaWidget['siswa']->kelas ? $siswaWidget['siswa']->kelas->tingkat . $siswaWidget['siswa']->kelas->kelas : '-' }}</span>
            </div>
            @endif
        </div>

        <div class="google-side-panel">
            <div class="grid gap-3">
                <div class="google-info-card">
                    <span class="google-icon-box" style="background:#4285F4"><i data-lucide="calendar-days" class="w-4 h-4"></i></span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase text-slate-400">Tanggal</p>
                        <p class="js-dash-date truncate text-xs font-extrabold capitalize text-slate-700 dark:text-slate-100">{{ $tanggalHari }}</p>
                    </div>
                </div>
                <div class="google-info-card">
                    <span class="google-icon-box" style="background:#34A853"><i data-lucide="clock-3" class="w-4 h-4"></i></span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase text-slate-400">Waktu WIB</p>
                        <p class="text-xs font-extrabold text-slate-700 dark:text-slate-100"><span class="js-dash-clock tabular-nums">--:--:--</span></p>
                    </div>
                </div>
                <div class="google-info-card">
                    <span class="google-icon-box" style="background:#FBBC05"><i data-lucide="layers-3" class="w-4 h-4"></i></span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase text-slate-400">Data Inti</p>
                        <p class="truncate text-xs font-extrabold text-slate-700 dark:text-slate-100">{{ number_format($totalSiswa) }} siswa, {{ number_format($totalGuru) }} guru</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-2xl bg-white px-4 py-3 text-slate-800 shadow-sm ring-1 ring-slate-200 dark:bg-slate-950/70 dark:text-white dark:ring-slate-700">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Kontrol Dashboard</p>
                        <p class="text-sm font-extrabold">Atur Widget</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" x-show="editing" x-cloak @click="reset()"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700 transition hover:bg-slate-200 dark:bg-white/10 dark:text-white dark:hover:bg-white/20" title="Reset tata letak">
                            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        </button>
                        <button type="button" @click="toggle()"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-xs font-extrabold text-white transition hover:bg-blue-700">
                            <i data-lucide="layout-dashboard" class="w-4 h-4" x-show="!editing"></i>
                            <i data-lucide="check" class="w-4 h-4" x-show="editing" x-cloak></i>
                            <span x-text="editing ? 'Selesai' : 'Tata Letak'"></span>
                        </button>
                    </div>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400" x-show="editing" x-cloak>Seret, ciutkan, atau sembunyikan kartu sesuai alur kerja harian.</p>
            </div>

            <svg class="google-school-art" viewBox="0 0 360 98" fill="none" aria-hidden="true">
                <path d="M24 80H336" stroke="currentColor" stroke-width="2" opacity=".18"/>
                <path d="M52 78V44L104 22L156 44V78" stroke="#4285F4" stroke-width="3" stroke-linejoin="round"/>
                <path d="M104 22V11M104 11L130 18L104 25" stroke="#EA4335" stroke-width="3" stroke-linejoin="round"/>
                <path d="M82 78V58H126V78" stroke="#34A853" stroke-width="3"/>
                <path d="M206 78V40H292V78" stroke="#34A853" stroke-width="3" stroke-linejoin="round"/>
                <path d="M196 40H302L249 16L196 40Z" stroke="#FBBC05" stroke-width="3" stroke-linejoin="round"/>
                <path d="M224 54H242M258 54H276M224 66H242M258 66H276" stroke="#4285F4" stroke-width="3" stroke-linecap="round"/>
                <circle cx="41" cy="65" r="9" stroke="#34A853" stroke-width="3" opacity=".78"/>
                <path d="M41 74V82" stroke="#34A853" stroke-width="3" opacity=".78"/>
                <circle cx="319" cy="64" r="8" stroke="#EA4335" stroke-width="3" opacity=".78"/>
                <path d="M319 72V82" stroke="#EA4335" stroke-width="3" opacity=".78"/>
            </svg>
        </div>
    </div>
</div>
