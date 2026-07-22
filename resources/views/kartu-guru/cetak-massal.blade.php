<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @include('kartu-guru._card-style')
    <style>
        @page { margin: 7mm 10mm; }
        body { margin: 0; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet td.slot { width: 33.33%; padding: 1.2mm; vertical-align: top; }
    </style>
</head>
<body>
{{-- ===== SISI DEPAN — semua halaman dulu ===== --}}
@foreach($pages as $page)
<div style="page-break-after: always;">
    <table class="sheet">
        @foreach($page->chunk(3) as $row)
        @php $slots = array_pad($row->all(), 3, null); @endphp
        <tr>
            @foreach($slots as $card)
            <td class="slot">@if($card)@include('kartu-guru._card', ['card' => $card])@endif</td>
            @endforeach
        </tr>
        @endforeach
    </table>
</div>
@endforeach

{{-- ===== SISI BELAKANG — 1 halaman per halaman depan, urutan SELURUH 3-slot baris dibalik
       (kartu + slot kosong ikut terbalik) supaya posisi tiap kartu sejajar dgn sisi depannya
       setelah lembar dibalik. Asumsi duplex "flip on long edge" (default paling umum utk
       dokumen portrait) — kalau printer memakai "flip on short edge", balik manual per halaman
       (bukan per baris) sblm mencetak sisi kedua. ===== --}}
@foreach($pages as $page)
<div @if(! $loop->last) style="page-break-after: always;" @endif>
    <table class="sheet">
        @foreach($page->chunk(3) as $row)
        @php $slotsBack = array_reverse(array_pad($row->all(), 3, null)); @endphp
        <tr>
            @foreach($slotsBack as $card)
            <td class="slot">@if($card)@include('kartu-guru._card-back', ['card' => $card])@endif</td>
            @endforeach
        </tr>
        @endforeach
    </table>
</div>
@endforeach
</body>
</html>
