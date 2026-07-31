<div class="card p-5 h-full flex flex-col">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <i data-lucide="user-x" class="w-4 h-4 text-rose-500"></i>
            Ketidakhadiran Guru & Tugas
        </h3>
        <a href="{{ route('piket.dashboard') }}" class="text-xs text-primary hover:underline" style="color:var(--cp)">Kelola Piket &rarr;</a>
    </div>

    <div class="flex-1 flex flex-col gap-3 overflow-y-auto pr-1">
        @if(isset($piketGuruTidakHadir) && $piketGuruTidakHadir->count() > 0)
            @foreach($piketGuruTidakHadir as $gth)
                <div class="rounded-xl border border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 p-3 flex flex-col gap-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $gth->guru?->nama }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <span class="capitalize font-semibold text-rose-600">{{ str_replace('_', ' ', $gth->alasan) }}</span>
                                @if($gth->keterangan) &mdash; {{ $gth->keterangan }} @endif
                            </p>
                        </div>
                    </div>
                    
                    @if($gth->penugasanPengganti && $gth->penugasanPengganti->count() > 0)
                        <div class="mt-1 space-y-2">
                            @foreach($gth->penugasanPengganti as $tugas)
                                <div class="bg-white dark:bg-slate-900 rounded-lg p-2.5 border border-slate-100 dark:border-slate-700 text-xs">
                                    <div class="flex items-center justify-between">
                                        <div class="font-semibold text-slate-700 dark:text-slate-200">
                                            {{ \Carbon\Carbon::parse($tugas->jadwal?->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($tugas->jadwal?->jam_selesai)->format('H:i') }} 
                                            (Kelas {{ $tugas->jadwal?->kelas?->nama_kelas ?? '-' }})
                                        </div>
                                        <div>
                                            @if($tugas->status === 'menunggu')
                                                <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold uppercase">Menunggu</span>
                                            @elseif($tugas->status === 'ditugaskan')
                                                <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-bold uppercase">Oleh: {{ $tugas->guru_pengisi ?? 'Guru' }}</span>
                                            @elseif($tugas->status === 'selesai')
                                                <span class="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold uppercase">Selesai</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-1.5 text-slate-500 flex flex-col gap-1">
                                        <span>Mapel: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $tugas->jadwal?->pelajaran?->nama ?? '-' }}</span></span>
                                        @if($tugas->tugasKelas)
                                            <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 mt-0.5 bg-emerald-50 dark:bg-emerald-900/20 p-1.5 rounded-md">
                                                <i data-lucide="file-check-2" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                                <span class="truncate">Tugas: {{ $tugas->tugasKelas->judul_tugas }}</span>
                                            </span>
                                        @else
                                            <span class="text-rose-500 dark:text-rose-400 flex items-center gap-1.5 mt-0.5 bg-rose-50 dark:bg-rose-900/20 p-1.5 rounded-md">
                                                <i data-lucide="alert-circle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                                Belum ada tugas
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Tidak ada jam kosong/mengajar hari ini.
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            <div class="flex-1 flex flex-col items-center justify-center text-center p-4 min-h-[150px]">
                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center mb-2">
                    <i data-lucide="check-square" class="w-6 h-6 text-emerald-500"></i>
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Semua Guru Hadir</p>
                <p class="text-xs text-slate-400 mt-1">Belum ada laporan ketidakhadiran untuk hari ini.</p>
            </div>
        @endif
    </div>
</div>
