{{-- Banner masa akses demo — hanya akun sandbox. --}}
@php
    $demoAccess = auth()->user()?->demoAccess;
@endphp
@if($demoAccess?->isCurrentlyActive())
    @php
        $sisaJam = max(0, now()->diffInHours($demoAccess->expires_at, false));
        $sisaHari = intdiv((int) $sisaJam, 24);
    @endphp
    <div class="mb-4 flex flex-wrap items-center gap-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm font-semibold text-amber-800 dark:text-amber-200" role="status">
        <i data-lucide="flask-conical" class="w-4 h-4 shrink-0"></i>
        <span>
            Anda memakai <b>data demo sintetis</b>
            ({{ $demoAccess->school_display_name }}).
            Masa akses berakhir
            <b>{{ $demoAccess->expires_at->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB</b>
            @if($sisaHari > 0)
                — sisa {{ $sisaHari }} hari.
            @else
                — sisa {{ (int) $sisaJam }} jam.
            @endif
        </span>
    </div>
@endif
