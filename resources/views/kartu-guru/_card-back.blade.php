{{-- Sisi BELAKANG kartu ID guru 54 × 85,6 mm — QR besar di tengah utk absensi (dipindah dari
     sisi depan). Dicetak sbg halaman kedua stlh sisi depan (lihat pdf.blade.php/cetak-massal.blade.php)
     supaya bisa dicetak bolak-balik pada kartu/lembar yang sama. Variabel sama dgn _card.blade.php:
     $card (guru,jabatan,bgText,fotoUri,qrUri,nomor), $sekolah, $logoUri. --}}
@php $g = $card['guru']; @endphp
<div class="kg-card">
    <div class="kg-c1"></div>
    <div class="kg-c2"></div>

    <div class="kg-back-header">
        <div class="kg-back-logo">@if($logoUri)<img src="{{ $logoUri }}">@endif</div>
        <div class="kg-back-sch">{{ \Illuminate\Support\Str::limit($sekolah['nama'], 40) }}</div>
        <div class="kg-cap">KARTU IDENTITAS PEGAWAI</div>
    </div>

    <div class="kg-back-qr-wrap">
        <div class="kg-back-qr"><img src="{{ $card['qrUri'] }}"></div>
    </div>

    <div class="kg-back-name">{{ \Illuminate\Support\Str::limit($g->nama, 30) }}</div>
    <div class="kg-back-jb">{{ \Illuminate\Support\Str::limit($card['jabatan'], 36) }}</div>
    <div class="kg-back-no">{{ $card['nomor'] ?: ' ' }}</div>

    <div class="kg-underline"></div>
    <div class="kg-back-note">Tunjukkan QR ini ke kamera kiosk absensi<br>untuk scan masuk / pulang.</div>
</div>
