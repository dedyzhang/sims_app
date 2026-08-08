{{-- Panel ringkasan rekonsiliasi mutasi ↔ tagihan --}}
<div class="card p-4 border-l-4 border-sky-400">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="git-compare" class="w-4 h-4 text-sky-600"></i>
                Rekonsiliasi Mutasi Bank
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                <span class="font-semibold text-amber-600">{{ $rekonsiliasiRingkas['belum_cocok'] }}</span> tagihan terverifikasi menunggu validasi rekening koran
                @if($rekonsiliasiRingkas['sudah_lunas'] > 0)
                    · <span class="font-semibold text-emerald-600">{{ $rekonsiliasiRingkas['sudah_lunas'] }}</span> sudah lunas (T.A. {{ $ta }})
                @endif
            </p>
        </div>
        <a href="{{ route('keuangan.verifikasi', ['ta' => $ta, 'tab' => 'validasi']) }}#validasi"
           class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 whitespace-nowrap">
            <i data-lucide="upload" class="w-4 h-4"></i> Impor Mutasi
        </a>
    </div>
    @if($rekonsiliasiAntrian->isNotEmpty())
    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 space-y-2 max-h-48 overflow-y-auto">
        @foreach($rekonsiliasiAntrian->take(5) as $row)
            @php $p = $row['pembayaran']; @endphp
            <div class="flex items-center justify-between gap-2 text-xs">
                <span class="text-slate-600 dark:text-slate-300 truncate">{{ $p->siswa?->nama }} · {{ $p->label_bulan }}</span>
                <span class="badge bg-indigo-100 dark:bg-indigo-900 text-indigo-700 flex-shrink-0">Prio {{ $row['skor'] }}</span>
            </div>
        @endforeach
        @if($rekonsiliasiAntrian->count() > 5)
            <p class="text-[10px] text-slate-400">+ {{ $rekonsiliasiAntrian->count() - 5 }} tagihan lainnya</p>
        @endif
    </div>
    @endif
</div>
