{{-- ===== Peminjaman Aktif Sarpras (data dari controller, tanpa query di view) ===== --}}
@if($sarpras)
<a href="{{ route('sarpras.peminjaman.index') }}" class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Peminjaman Aktif</p>
        <p class="text-2xl font-extrabold tracking-tight text-amber-500">{{ $sarpras['peminjamanAktif'] }} <span class="text-sm font-semibold text-slate-400">proses</span></p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words truncate">{{ $sarpras['peminjamanMenunggu'] }} menunggu approval &rarr;</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/40 text-amber-500 group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
    </span>
</a>
@endif
