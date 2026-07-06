@php
    $spKerusakan = \App\Sarpras\Models\LaporanKerusakan::whereIn('status', ['dilaporkan','diterima'])->count();
@endphp
<a href="{{ route('sarpras.kerusakan.index') }}" class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Kerusakan Terbuka</p>
        <p class="text-2xl font-extrabold tracking-tight text-rose-500">{{ number_format($spKerusakan) }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">Lihat detail &rarr;</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="wrench" class="w-4 h-4"></i>
    </span>
</a>
