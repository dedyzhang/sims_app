{{-- Satu kartu ID guru 54 × 85,6 mm (portrait), ala desain acara: kartu terang, jabatan diulang
     bertumpuk sebagai background, foto cutout PNG besar di depannya, banner nama gelap di bawah.
     Variabel: $card (guru,jabatan,bgText,fotoUri,qrUri,nomor), $sekolah, $logoUri.
     CSS dompdf-safe: posisi absolut, tanpa flexbox. Class di-prefix "kg". --}}
@php
    $g = $card['guru'];
    $bgText = $card['bgText'];
    // Font baris jabatan: satu kata memenuhi lebar kartu (ala "JABATAN" di contoh); kata pendek dibatasi 38pt.
    $rowSize = min(38, max(14, (int) (160 / (0.75 * max(4, strlen($bgText))))));
    // Tinggi baris ditetapkan ABSOLUT dalam mm (dompdf tidak menghormati line-height unitless —
    // pitch aslinya ~1.6× font, membuat baris terakhir terpotong container dan meninggalkan
    // artefak "garis tipis" di dekat banner nama). Teks uppercase tanpa descender → aman di-clip.
    $rowH = round($rowSize * 0.3528 * 1.12, 2); // mm
    $rowCount = max(3, (int) floor(52 / $rowH));
    $namaTampil = \Illuminate\Support\Str::limit($g->nama, 30);
    $namaSize = strlen($namaTampil) > 22 ? '6.4pt' : '8pt';
@endphp
<div class="kg-card">
    {{-- Aksen kuning --}}
    <div class="kg-c1"></div>
    <div class="kg-c2"></div>

    {{-- Jabatan diulang bertumpuk (baris ganjil solid, genap pudar — efek outline) --}}
    <div class="kg-rows" style="font-size: {{ $rowSize }}pt;">
        @for($i = 0; $i < $rowCount; $i++)
            <div class="{{ $i % 2 === 0 ? 'kg-row-solid' : 'kg-row-ghost' }}" style="height: {{ $rowH }}mm; line-height: {{ $rowH }}mm; overflow: hidden;">{{ $bgText }}</div>
        @endfor
    </div>

    {{-- Foto cutout besar (PNG transparan) di depan tumpukan teks --}}
    {{-- Tinggi foto lewat CSS (bukan inline style): dompdf sempat "membocorkan" inline height
         ini ke gambar logo pada kartu lain dalam satu lembar massal (bug caching gambar). --}}
    <div class="kg-foto">
        @if($card['fotoUri'])
            <img src="{{ $card['fotoUri'] }}">
        @else
            <div class="kg-noface">{{ strtoupper(substr($g->nama, 0, 1)) }}</div>
        @endif
    </div>

    {{-- Pojok logo (kiri atas) + nama sekolah (kanan atas) --}}
    <div class="kg-corner">
        <div class="kg-logo">@if($logoUri)<img src="{{ $logoUri }}">@endif</div>
    </div>
    <div class="kg-sch">
        {{ \Illuminate\Support\Str::limit($sekolah['nama'], 40) }}
        <div class="kg-cap">KARTU IDENTITAS PEGAWAI</div>
    </div>

    {{-- Banner nama --}}
    <div class="kg-banner" style="font-size: {{ $namaSize }};">{{ $namaTampil }}</div>
    <div class="kg-underline"></div>

    {{-- Bawah: jabatan lengkap + nomor, dipusatkan (QR dipindah ke sisi belakang kartu — lihat
         _card-back.blade.php — supaya sisi depan fokus ke identitas & sisi belakang ke absensi). --}}
    <div class="kg-meta">
        <div class="kg-jb">{{ \Illuminate\Support\Str::limit($card['jabatan'], 36) }}</div>
        <div class="kg-no">{{ $card['nomor'] ?: ' ' }}</div>
    </div>
</div>
