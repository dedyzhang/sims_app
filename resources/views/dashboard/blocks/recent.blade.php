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
    @php
        $isMaitreyawira = ($pref->primary_color === '#2563eb' && $pref->accent_color === '#f59e0b');
    @endphp
    <div class="lg:col-span-3 card p-5">
        <div class="flex items-center justify-between mb-1">
            <h2 class="font-bold text-slate-700 dark:text-slate-200">Komposisi Siswa</h2>
            <span class="badge bg-primary-50 text-primary">{{ number_format($totalSiswa) }} total</span>
        </div>
        <p class="text-3xl font-extrabold text-slate-700 dark:text-slate-100 mt-2">{{ number_format($totalSiswa) }}</p>
        <p class="text-sm text-slate-400 mb-4">Distribusi jenis kelamin</p>

        @php $tot = max($totalSiswa,1); $pl = round($siswaL/$tot*100); $pp = 100-$pl; @endphp
        @if($isMaitreyawira)
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 mb-4">
            <div class="flex-1 w-full space-y-3">
                <div class="p-3 rounded-2xl border border-[#ece6df] dark:border-slate-700 bg-white dark:bg-slate-800">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-3 h-3 rounded-full" style="background:var(--cp)"></span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">Laki-laki</span>
                    </div>
                    <p class="text-xl font-extrabold text-slate-700 dark:text-slate-100">
                        {{ number_format($siswaL) }} 
                        <span class="text-sm font-medium text-slate-400">({{ $pl }}%)</span>
                    </p>
                </div>
                <div class="p-3 rounded-2xl border border-[#ece6df] dark:border-slate-700 bg-white dark:bg-slate-800">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-3 h-3 rounded-full bg-[#db7793]"></span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">Perempuan</span>
                    </div>
                    <p class="text-xl font-extrabold text-slate-700 dark:text-slate-100">
                        {{ number_format($siswaP) }} 
                        <span class="text-sm font-medium text-slate-400">({{ $pp }}%)</span>
                    </p>
                </div>
            </div>
            
            <div class="flex-shrink-0 flex items-center justify-center relative w-[130px] h-[130px] mx-auto sm:mx-0">
                <svg viewBox="0 0 36 36" class="w-full h-full transform -rotate-90">
                    <!-- Background Circle -->
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--sbg)" stroke-width="3" />
                    <!-- Laki-laki segment -->
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--cp)" stroke-width="3.5" 
                            stroke-dasharray="{{ $pl }} 100" stroke-dashoffset="0" stroke-linecap="round" />
                    <!-- Perempuan segment -->
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#db7793" stroke-width="3.5" 
                            stroke-dasharray="{{ $pp }} 100" stroke-dashoffset="-{{ $pl }}" stroke-linecap="round" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Total</span>
                    <span class="text-xl font-black text-slate-700 dark:text-slate-200 leading-none mt-0.5">{{ number_format($totalSiswa) }}</span>
                </div>
            </div>
        </div>
        @else
        <div class="flex h-4 rounded-full overflow-hidden mb-4 bg-slate-100 dark:bg-slate-700">
            <div style="width:{{ $pl }}%;background:linear-gradient(90deg,var(--cp),var(--cps))" class="h-full"></div>
            <div style="width:{{ $pp }}%;background:linear-gradient(90deg,#ec9aae,#db7793)" class="h-full"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="p-3 rounded-2xl bg-primary-50 dark:bg-slate-800/70">
                <div class="flex items-center gap-2 mb-1"><span class="w-3 h-3 rounded-full" style="background:var(--cp)"></span><span class="text-xs font-semibold text-slate-500 dark:text-slate-300">Laki-laki</span></div>
                <p class="text-xl font-extrabold text-slate-700 dark:text-slate-100">{{ number_format($siswaL) }} <span class="text-sm font-medium text-slate-400 dark:text-slate-400">({{ $pl }}%)</span></p>
            </div>
            <div class="p-3 rounded-2xl bg-[#fce7ec] dark:bg-slate-800/70">
                <div class="flex items-center gap-2 mb-1"><span class="w-3 h-3 rounded-full bg-[#db7793]"></span><span class="text-xs font-semibold text-slate-500 dark:text-slate-300">Perempuan</span></div>
                <p class="text-xl font-extrabold text-slate-700 dark:text-slate-100">{{ number_format($siswaP) }} <span class="text-sm font-medium text-slate-400 dark:text-slate-400">({{ $pp }}%)</span></p>
            </div>
        </div>
        @endif

        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-[#f4efe8] dark:border-slate-700">
            <a href="{{ route('siswa.create') }}" class="btn-primary flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Tambah Siswa</a>
            <a href="{{ route('guru.create') }}" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border border-[#ece6df] dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Tambah Guru</a>
            <a href="{{ route('kelas.setKelas') }}" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border border-[#ece6df] dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> Set Kelas</a>
        </div>
    </div>
</div>
