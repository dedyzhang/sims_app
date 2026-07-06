@php
    $kesJenisAturan = \App\Models\Setting::get('jenis_aturan', 'p3');
    $kesPending = $kesJenisAturan === 'poin'
        ? \App\Models\PoinTemp::where('status', 'belum')->count()
        : \App\Models\P3Temp::where('status', 'belum')->count();
@endphp
<a href="{{ route($kesJenisAturan === 'poin' ? 'poin.temp.index' : 'p3.temp.index') }}" class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Pengajuan Menunggu</p>
        <p class="text-2xl font-extrabold {{ $kesPending > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $kesPending }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">{{ $kesJenisAturan === 'poin' ? 'Poin' : 'P3' }} &bull; Lihat detail &rarr;</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="inbox" class="w-4 h-4"></i>
    </span>
</a>
