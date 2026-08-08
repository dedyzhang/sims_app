{{--
    Satu grup verifikasi (batch) dengan skor prioritas opsional.
    Variabel: $group (Collection<SppPembayaran>), $mode, $priorityScore?, $priorityAlasan?, $anomaliMap?
--}}
@php
    $topScore = $priorityScore ?? null;
    $alasan = $priorityAlasan ?? [];
    $anomaliFlags = $anomaliFlags ?? (isset($anomaliMap)
        ? $group->flatMap(fn ($p) => ($anomaliMap[$p->uuid]['flags'] ?? []))->unique('kode')->values()->all()
        : []);
@endphp
<div class="relative">
    @if($topScore)
    <div class="absolute -left-1 top-4 z-10" title="Skor prioritas: {{ $topScore }}">
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
            <i data-lucide="zap" class="w-3 h-3"></i> {{ $topScore }}
        </span>
    </div>
    @endif
    @if(!empty($alasan))
    <div class="px-3 pb-1 flex flex-wrap gap-1 text-[10px] text-slate-500">
        @foreach($alasan as $k => $v)
            <span class="badge bg-slate-100 dark:bg-slate-800" title="{{ $k }}">{{ $v }}</span>
        @endforeach
    </div>
    @endif
    @include('keuangan.partials.verif-card', [
        'group' => $group,
        'mode' => $mode,
        'anomaliFlags' => $anomaliFlags,
    ])
</div>
