{{-- QR gabung Arena — tampilan guru (proyeksi) --}}
@if($soloJoinQrSvg || $liveJoinQrSvg)
<div class="arena-rx-manage-section">
    <p class="arena-rx-manage-label">QR gabung siswa</p>
    <p class="m-0 mb-3 text-xs font-semibold text-slate-500">Tayangkan di layar kelas — siswa pindai lewat tombol &quot;Pindai QR&quot; di HP mereka.</p>
    <div class="arena-rx-join-qr-grid">
        @if($soloJoinQrSvg)
        <div class="arena-rx-join-qr-card" x-init="$nextTick(() => window.arenaRenderJoinBarcodes && arenaRenderJoinBarcodes($el))">
            <p class="arena-rx-join-qr-label">Solo · token</p>
            <div class="arena-rx-join-qr-box">{!! $soloJoinQrSvg !!}</div>
            @include('arena-belajar.partials.join-barcode-display', ['payload' => $soloBarcodePayload ?? null])
            <p class="arena-rx-join-qr-hint font-mono tracking-widest">{{ $quiz->access_token }}</p>
            <p class="arena-rx-join-qr-hint">QR atau barcode — token otomatis setelah pindai</p>
        </div>
        @endif
        @if($liveJoinQrSvg)
        <div class="arena-rx-join-qr-card" x-init="$nextTick(() => window.arenaRenderJoinBarcodes && arenaRenderJoinBarcodes($el))">
            <p class="arena-rx-join-qr-label">Live Arena</p>
            <div class="arena-rx-join-qr-box">{!! $liveJoinQrSvg !!}</div>
            @include('arena-belajar.partials.join-barcode-display', ['payload' => $liveBarcodePayload ?? null])
            <p class="arena-rx-join-qr-hint font-mono tracking-widest">{{ $quiz->access_token }}</p>
            <p class="arena-rx-join-qr-hint">QR atau barcode — langsung ke lobi Live</p>
        </div>
        @endif
    </div>
</div>
@endif
