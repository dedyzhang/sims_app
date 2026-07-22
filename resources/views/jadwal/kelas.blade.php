@extends('layouts.app')
@section('title', 'Jadwal per Kelas')

@push('styles')
<style>
    .wtable { border-collapse:separate; border-spacing:0; }
    .wtable th,.wtable td { border-bottom:1px solid #eef2f7; border-right:1px solid #eef2f7; }
    .dark .wtable th,.dark .wtable td { border-color:#293548; }
    .wtable thead th { background:#f8fafc; }
    .dark .wtable thead th { background:#0f172a; }
    .kcell { cursor:pointer; transition:filter .12s; }
    .kcell:hover { filter:brightness(.97); }
    /* popup nama guru */
    #jtip { position:fixed; z-index:9999; pointer-events:none; background:#0f172a; color:#fff; border-radius:10px; padding:7px 11px; font-size:12px; line-height:1.35; box-shadow:0 12px 30px -8px rgba(0,0,0,.45); opacity:0; transition:opacity .12s; transform:translate(-50%,-100%); white-space:nowrap; }
    #jtip.show { opacity:1; }
    #jtip .jt-pel { font-weight:700; }
    #jtip .jt-guru { color:#cbd5e1; font-size:11px; margin-top:1px; display:flex; align-items:center; gap:4px; }
    #jtip::after { content:''; position:absolute; bottom:-5px; left:50%; transform:translateX(-50%); border:5px solid transparent; border-top-color:#0f172a; border-bottom:0; }
</style>
@endpush

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('jadwal.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="page-title">Jadwal per Kelas</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tampilan mingguan satu kelas</p>
            </div>
        </div>
        <form method="GET" action="{{ route('jadwal.kelas') }}">
            <select name="kelas" onchange="this.form.submit()" class="form-select py-2.5 text-sm w-auto">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selectedKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($rows->isEmpty() || !$selectedKelas)
    <div class="card p-10 text-center text-slate-400">
        <i data-lucide="calendar-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada data jadwal.</p>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="wtable w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-bold uppercase text-slate-500 w-28">Jam</th>
                        @foreach(\App\Models\Jadwal::HARI as $no => $nama)
                        <th class="px-1 py-2.5 text-center text-xs font-bold text-slate-600 dark:text-slate-300 w-16 min-w-[64px]">{{ $nama }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{-- Catatan: jam khusus (istirahat/dll) yg di-scope ke kelas TERTENTU diasumsikan
                         sama cakupannya sepanjang minggu (baris ini gabungan semua hari) — kalau
                         sekolah butuh cakupan berbeda per hari utk jam nominal yg sama, gunakan
                         Editor Jadwal (per-hari) sbg acuan yg presisi. --}}
                    @foreach($rows as $jam)
                        @if($jam->isKhususUntukKelas($selectedKelas))
                        <tr>
                            <td class="px-3 py-1.5 text-xs font-bold text-amber-600">{{ $jam->nama_khusus }}</td>
                            <td colspan="6" class="px-3 py-1.5 text-center text-xs text-amber-600 font-semibold bg-amber-50/50 dark:bg-amber-900/10">
                                <i data-lucide="{{ $jam->ikon }}" class="w-3.5 h-3.5 inline"></i> {{ $jam->nama_khusus }} &bull; {{ $jam->rentang }}
                            </td>
                        </tr>
                        @else
                        <tr>
                            <td class="px-3 py-2 align-top">
                                <p class="font-bold text-slate-700 dark:text-slate-200">Jam {{ $jam->jam_ke ?? '-' }}</p>
                                <p class="text-[11px] text-slate-400 font-mono">{{ $jam->rentang }}</p>
                            </td>
                            @foreach(\App\Models\Jadwal::HARI as $no => $nama)
                            @php $j = $cells[\Carbon\Carbon::parse($jam->jam_mulai)->format('H:i').'|'.$no] ?? null; @endphp
                            <td class="p-1 align-middle text-center">
                                @if($j && ($j->pelajaran || $j->keterangan))
                                @php
                                    $pnama = $j->pelajaran?->nama ?? $j->keterangan;
                                    $short = $j->pelajaran?->kode ?: \Illuminate\Support\Str::limit($pnama, 7, '');
                                @endphp
                                <div class="kcell rounded-lg px-1 py-2.5 grid place-items-center" style="background:color-mix(in srgb,var(--cp) 11%,#fff)"
                                     data-pnama="{{ $pnama }}" data-gnama="{{ $j->guru?->nama ?? '' }}" title="{{ $pnama }}{{ $j->guru ? ' — '.$j->guru->nama : '' }}">
                                    <span class="font-bold text-[12.5px] leading-none text-slate-700 dark:text-slate-200">{{ $short }}</span>
                                </div>
                                @else
                                <div class="text-center text-slate-200 dark:text-slate-700 text-xs py-2">·</div>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- Popup nama guru (hover di desktop, tap di HP) --}}
<div id="jtip"></div>

@push('scripts')
<script>
(function(){
    const tip = document.getElementById('jtip');
    if(!tip) return;
    let active = null;
    const personSvg = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>';
    function show(c){
        const pn = c.dataset.pnama, gn = c.dataset.gnama;
        if(!pn) return;
        tip.innerHTML = `<div class="jt-pel">${pn}</div><div class="jt-guru">${personSvg}${gn || 'Guru belum diset'}</div>`;
        const r = c.getBoundingClientRect();
        tip.style.left = (r.left + r.width/2) + 'px';
        tip.style.top = (r.top - 8) + 'px';
        tip.classList.add('show'); active = c;
    }
    function hide(){ tip.classList.remove('show'); active = null; }
    document.querySelectorAll('.kcell').forEach(c => {
        c.addEventListener('mouseenter', () => show(c));
        c.addEventListener('mouseleave', () => { if(active === c) hide(); });
        c.addEventListener('click', (e) => { e.stopPropagation(); active === c ? hide() : show(c); });
    });
    document.addEventListener('click', hide);
    window.addEventListener('scroll', hide, true);
})();
</script>
@endpush
@endsection
