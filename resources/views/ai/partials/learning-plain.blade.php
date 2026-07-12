{{--
    Fallback: konten yang tidak berformat RPM (mis. guru mengedit bebas) dirender
    sebagai teks polos, dengan heading yang masih dikenali tetap ditebalkan.
--}}
@php
    $lines = preg_split('/\R/u', trim((string) $content)) ?: [];
    $centerUntilIdentity = true;
    $sectionHeadings = ['IDENTIFIKASI', 'DESAIN PEMBELAJARAN', 'PENGALAMAN BELAJAR', 'ASESMEN PEMBELAJARAN'];
    $subHeadings = ['AWAL', 'INTI', 'MEMAHAMI', 'MENGAPLIKASI', 'MEREFLEKSI', 'PENUTUP'];
@endphp
<div class="document">
@foreach($lines as $line)
    @php
        $trimmed = trim($line);
        if (str_starts_with($trimmed, 'SEKOLAH')) {
            $centerUntilIdentity = false;
        }
        $upper = mb_strtoupper($trimmed, 'UTF-8');
        $isSection = in_array($upper, $sectionHeadings, true) || str_starts_with($upper, 'LAMPIRAN ');
        $isSub = in_array($upper, $subHeadings, true) || str_starts_with($upper, 'ASESMEN PADA ');
        $isTitle = str_contains($upper, 'PERENCANAAN PEMBELAJARAN MENDALAM');
    @endphp
    @if($trimmed === '')
        <div class="blank"></div>
    @elseif($isSection)
        <div class="section">{{ $trimmed }}</div>
    @elseif($isTitle)
        <div class="title">{{ $trimmed }}</div>
    @elseif($isSub)
        <div class="subsection">{{ $trimmed }}</div>
    @elseif($centerUntilIdentity)
        <div class="line center">{{ $trimmed }}</div>
    @else
        <div class="line">{{ $line }}</div>
    @endif
@endforeach
</div>
