@php
    // Reuse angka yg sudah dihitung di header dashboard.blade.php (propagasi lewat @include) —
    // dulu blok ini query ulang total/L/P (dobel dgn header). `??` tetap punya fallback sendiri.
    $totalSiswa = $totalSiswa ?? ($stats['total_siswa'] ?? \App\Models\Siswa::count());
    $siswaL = $siswaL ?? \App\Models\Siswa::where('jk','L')->count();
    $siswaP = $siswaP ?? \App\Models\Siswa::where('jk','P')->count();
    $tot = max($totalSiswa,1);
    $pl = round($siswaL/$tot*100);
    $pp = 100-$pl;
@endphp
<div class="card p-5 h-full shadow-sm flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between mb-1">
            <h2 class="font-bold text-slate-700 dark:text-slate-200">Komposisi Siswa</h2>
            <span class="badge bg-primary/10 text-primary">{{ number_format($totalSiswa) }} total</span>
        </div>
        <p class="text-3xl font-extrabold text-slate-700 dark:text-slate-100 mt-2">{{ number_format($totalSiswa) }}</p>
        <p class="text-sm text-slate-400 mb-4">Distribusi jenis kelamin</p>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 mb-4">
            <div class="flex-1 w-full space-y-3">
                <div class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/20 dark:bg-slate-900/20">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:var(--cp)"></span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">Laki-laki</span>
                    </div>
                    <p class="text-xl font-extrabold text-slate-750 dark:text-slate-100">
                        {{ number_format($siswaL) }} 
                        <span class="text-sm font-medium text-slate-400">({{ $pl }}%)</span>
                    </p>
                </div>
                <div class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/20 dark:bg-slate-900/20">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#db7793]"></span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">Perempuan</span>
                    </div>
                    <p class="text-xl font-extrabold text-slate-750 dark:text-slate-100">
                        {{ number_format($siswaP) }} 
                        <span class="text-sm font-medium text-slate-400">({{ $pp }}%)</span>
                    </p>
                </div>
            </div>
            
            <div class="flex-shrink-0 flex items-center justify-center relative w-[130px] h-[130px] mx-auto sm:mx-0">
                <svg viewBox="0 0 36 36" class="w-full h-full transform -rotate-90">
                    <!-- Background Circle -->
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" class="text-slate-100 dark:text-slate-800" stroke-width="2.5" />
                    <!-- Laki-laki segment -->
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--cp)" stroke-width="3.2" 
                            stroke-dasharray="{{ $pl }} 100" stroke-dashoffset="0" stroke-linecap="round" class="transition-all duration-500" />
                    <!-- Perempuan segment -->
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#db7793" stroke-width="3.2" 
                            stroke-dasharray="{{ $pp }} 100" stroke-dashoffset="-{{ $pl }}" stroke-linecap="round" class="transition-all duration-500" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Total</span>
                    <span class="text-xl font-black text-slate-700 dark:text-slate-200 leading-none mt-0.5">{{ number_format($totalSiswa) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
        <a href="{{ route('siswa.create') }}" class="btn-primary flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Tambah Siswa</a>
        <a href="{{ route('guru.create') }}" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 text-slate-650 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Tambah Guru</a>
        <a href="{{ route('kelas.setKelas') }}" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 text-slate-650 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition"><i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> Set Kelas</a>
    </div>
</div>
