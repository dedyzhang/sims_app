@php
    $kurAgendaPending = \App\Models\Agenda::where('validasi', 'belum')->count();
@endphp
<a href="{{ route('agenda.rekap') }}" class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Agenda Menunggu Validasi</p>
        <p class="text-2xl font-extrabold {{ $kurAgendaPending > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $kurAgendaPending }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">Lihat detail &rarr;</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
    </span>
</a>
