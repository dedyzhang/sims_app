{{-- Banner sisa masa langganan — HANYA superadmin (titik integrasi 2, lihat PRD §10). --}}
@if(auth()->user()?->access === 'superadmin')
    @php
        $lgn = \App\Models\Langganan::current();
        $lgnTingkat = $lgn?->tingkatPeringatan();
    @endphp
    @if($lgn && $lgnTingkat)
        @php
            $lgnSisa = $lgn->sisaHari();
            [$lgnKelas, $lgnIkon] = match ($lgnTingkat) {
                'kadaluarsa' => ['bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300', 'circle-x'],
                'merah'      => ['bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300', 'alarm-clock'],
                'kuning'     => ['bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300', 'clock-alert'],
                default      => ['bg-sky-500/10 border-sky-500/30 text-sky-700 dark:text-sky-300', 'info'],
            };
        @endphp
        <div class="mb-4 flex flex-wrap items-center gap-2.5 rounded-xl border px-4 py-3 text-sm font-semibold {{ $lgnKelas }}" role="alert">
            <i data-lucide="{{ $lgnIkon }}" class="w-4 h-4 shrink-0"></i>
            <span>
                @if($lgnTingkat === 'kadaluarsa')
                    Langganan SIMS ({{ $lgn->durasi_bulan }} bulan)
                    <b>kadaluarsa {{ $lgnSisa === 0 ? 'hari ini' : abs($lgnSisa).' hari lalu' }}</b>
                    — seluruh pengguna lain kini terkunci.
                @else
                    Langganan SIMS ({{ $lgn->durasi_bulan }} bulan / {{ $lgn->totalHari() }} hari) akan berakhir —
                    <b>sisa {{ $lgnSisa }} hari</b>
                    ({{ $lgn->berakhir_pada->translatedFormat('d F Y') }}).
                @endif
            </span>
            <a href="{{ route('langganan.index') }}" class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-white/70 dark:bg-slate-800/70 px-3 py-1.5 text-xs font-bold hover:opacity-80">
                <i data-lucide="calendar-plus" class="w-3.5 h-3.5"></i>Perpanjang
            </a>
        </div>
    @endif
@endif
