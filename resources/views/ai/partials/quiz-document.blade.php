{{--
    Struktur dokumen soal evaluasi hasil QuizDocument::parse(). Markup ini mengikuti
    tata letak file Word yang diexport (QuizDocxBuilder), jadi yang dilihat guru di
    layar = yang tercetak. Bila $doc['parsed'] false, pemanggil merender teks polos.
--}}

{{-- Kop sekolah --}}
@if($doc['kop'])
    <div class="kop">
        @foreach($doc['kop'] as $i => $line)
            @php $isCaps = mb_strtoupper($line, 'UTF-8') === $line && ! preg_match('/\d{5}|@|http/u', $line); @endphp
            <div class="{{ $isCaps ? ($i < 2 ? 'nama' : 'sub') : 'alamat' }}">{{ $line }}</div>
        @endforeach
    </div>
@endif

{{-- Judul --}}
<div class="judul">{{ $doc['title'] }}</div>
@if($doc['subtitle'] !== '')
    <div class="subjudul">{{ $doc['subtitle'] }}</div>
@endif

{{-- Identitas --}}
@if($doc['identity'])
    <table class="identitas">
        @foreach($doc['identity'] as $row)
            <tr>
                <td class="lbl">{{ $row['label'] }}</td>
                <td class="sep">:</td>
                <td>{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Petunjuk pengerjaan --}}
@if($doc['petunjuk']['heading'] !== '' || $doc['petunjuk']['lines'])
    <div class="bagian">{{ $doc['petunjuk']['heading'] !== '' ? $doc['petunjuk']['heading'] : 'Petunjuk Pengerjaan' }}</div>
    <ol class="petunjuk">
        @foreach($doc['petunjuk']['lines'] as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ol>
@endif

{{-- Bagian soal --}}
@foreach($doc['sections'] as $section)
    <div class="bagian">{{ $section['heading'] }}</div>

    @foreach($section['intro'] as $line)
        <div class="intro">{{ $line }}</div>
    @endforeach

    @foreach($section['questions'] as $question)
        <div class="soal">{{ $question['number'] }}. {{ $question['text'] }}</div>
        @foreach(($question['images'] ?? []) as $image)
            @php
                $abs = \App\Support\QuizImageEnricher::absolutePath($image['path'] ?? '');
                $imgSrc = null;
                if ($abs) {
                    $mime = mime_content_type($abs) ?: 'image/png';
                    $imgSrc = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($abs));
                } elseif (! empty($image['url'])) {
                    $imgSrc = $image['url'];
                }
            @endphp
            @if($imgSrc)
                <div class="soal-gambar">
                    <img src="{{ $imgSrc }}" alt="{{ $image['caption'] ?? 'Gambar soal' }}">
                    @if(!empty($image['caption']))
                        <div class="soal-gambar-caption">{{ $image['caption'] }}</div>
                    @endif
                </div>
            @endif
        @endforeach
        @foreach($question['options'] as $option)
            <div class="opsi">{{ $option['label'] }}. {{ $option['text'] }}</div>
        @endforeach
        @if($question['answer_space'])
            <div class="garis-jawab">_______________________________________________________________________</div>
        @endif
    @endforeach
@endforeach

{{-- Kunci jawaban & pedoman penilaian --}}
@if($doc['kunci']['heading'] !== '' || $doc['kunci']['pg'] || $doc['kunci']['lainnya'] || $doc['kunci']['esai'])
    <div class="kunci">
        <div class="bagian">{{ $doc['kunci']['heading'] !== '' ? $doc['kunci']['heading'] : 'Kunci Jawaban & Pedoman Penilaian' }}</div>
        @if($doc['kunci']['subtitle'] !== '')
            <div class="intro">{{ $doc['kunci']['subtitle'] }}</div>
        @endif

        @if($doc['kunci']['pg'])
            <div class="subbagian">Pilihan Ganda</div>
            {{-- Urutan DOM 1..n (bukan kolom kiri/kanan) agar stack mobile tetap berurutan --}}
            <table class="kunci-pg">
                <tbody>
                    @foreach($doc['kunci']['pg'] as $item)
                        <tr>
                            <td>{{ $item['number'] }}. {{ $item['answer'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @foreach($doc['kunci']['lainnya'] as $kunciLainnya)
            <div class="subbagian">{{ $kunciLainnya['heading'] }}</div>
            <ul class="poin">
                @foreach($kunciLainnya['lines'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endforeach

        @if($doc['kunci']['esai'])
            <div class="subbagian">Esai — Poin Jawaban Ideal</div>
            @foreach($doc['kunci']['esai'] as $esai)
                <div class="esai-head">{{ $esai['heading'] }}</div>
                <ul class="poin">
                    @foreach($esai['lines'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @endforeach
        @endif

        @if($doc['kunci']['rubrik']['heading'] !== '' || $doc['kunci']['rubrik']['lines'])
            <div class="subbagian">{{ $doc['kunci']['rubrik']['heading'] !== '' ? $doc['kunci']['rubrik']['heading'] : 'Rubrik Penilaian' }}</div>
            <ul class="poin">
                @foreach($doc['kunci']['rubrik']['lines'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
