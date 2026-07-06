@props(['items', 'compact' => false])
@php
    $order = [1, 0, 2]; // tampil: perak - emas - perunggu
    $medal = [
        0 => ['label' => '1', 'ring' => 'ring-amber-300', 'badge' => 'bg-amber-500', 'grad' => 'linear-gradient(135deg,#fde68a,#f59e0b)', 'h' => $compact ? 'h-20' : 'h-28', 'avatar' => $compact ? 'w-16 h-16 text-xl' : 'w-20 h-20 text-2xl', 'crown' => true, 'color' => '#d97706'],
        1 => ['label' => '2', 'ring' => 'ring-slate-300', 'badge' => 'bg-slate-400', 'grad' => 'linear-gradient(135deg,#e2e8f0,#94a3b8)', 'h' => $compact ? 'h-14' : 'h-20', 'avatar' => $compact ? 'w-12 h-12 text-base' : 'w-16 h-16 text-xl', 'crown' => false, 'color' => '#64748b'],
        2 => ['label' => '3', 'ring' => 'ring-orange-300', 'badge' => 'bg-orange-600', 'grad' => 'linear-gradient(135deg,#fdba74,#c2762f)', 'h' => $compact ? 'h-10' : 'h-14', 'avatar' => $compact ? 'w-12 h-12 text-base' : 'w-16 h-16 text-xl', 'crown' => false, 'color' => '#c2762f'],
    ];
@endphp
<div class="relative flex items-end justify-center gap-1 sm:gap-3 {{ $compact ? 'md:gap-5' : 'md:gap-8' }}">
    @foreach($order as $idx)
        @continue(!isset($items[$idx]))
        @php $r = $items[$idx]; $m = $medal[$idx]; @endphp
        <div class="flex flex-col items-center gap-1 sm:gap-2 {{ $compact ? 'w-20 sm:w-24 md:w-28' : 'w-24 sm:w-28 md:w-36' }}">
            <div class="h-6 flex items-center">
                @if($m['crown'])
                <i data-lucide="crown" class="w-6 h-6 text-amber-400"></i>
                @endif
            </div>
            <div class="relative">
                <div class="{{ $m['avatar'] }} rounded-full grid place-items-center text-white font-bold shadow-lg ring-4 {{ $m['ring'] }}" style="background:{{ $m['grad'] }}">
                    {{ strtoupper(mb_substr($r['siswa']->nama, 0, 1)) }}
                </div>
                <span class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full {{ $m['badge'] }} text-white grid place-items-center text-xs font-black border-2 border-white dark:border-slate-800 shadow">{{ $m['label'] }}</span>
            </div>
            <p class="font-bold {{ $compact ? 'text-xs' : 'text-sm' }} text-center leading-tight w-full truncate" title="{{ $r['siswa']->nama }}">{{ $r['siswa']->nama }}</p>
            <p class="text-xs text-slate-400">{{ $r['siswa']->kelas ? $r['siswa']->kelas->tingkat . $r['siswa']->kelas->kelas : '-' }}</p>
            <p class="{{ $compact ? 'text-base' : 'text-lg' }} font-extrabold" style="color:{{ $m['color'] }}">{{ $r['sisa'] }}</p>
            <div class="w-full {{ $m['h'] }} rounded-t-xl flex items-start justify-center pt-2" style="background:{{ $m['grad'] }}">
                <i data-lucide="medal" class="w-5 h-5 text-white/80"></i>
            </div>
        </div>
    @endforeach
</div>
