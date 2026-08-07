@php
    $bulanIndonesia = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $tanggalTandaTangan = 'Tanjungpinang, '.now()->format('j').' '.$bulanIndonesia[(int) now()->format('n')].' '.now()->format('Y');
@endphp

@if($doc['parsed'])
    <div class="topbar">
        {{ $doc['title'] }}
        @if($doc['subject'] !== '')
            <span class="subject">{{ $doc['subject'] }}</span>
        @endif
    </div>

    @if($doc['identity'] !== [])
        <table class="identity">
            @foreach(array_chunk($doc['identity'], 2) as $pair)
                <tr>
                    @foreach($pair as $item)
                        <td class="label">{{ $item['label'] }}</td>
                        <td class="value">: {{ $item['value'] }}</td>
                    @endforeach
                    @if(count($pair) === 1)
                        <td class="label">&nbsp;</td><td class="value">&nbsp;</td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    @if($doc['rows'] !== [])
        <table class="blueprint">
            <thead>
                <tr>
                    <th class="c-no">No.</th>
                    <th class="c-element">Elemen / Capaian<br>Pembelajaran</th>
                    <th class="c-material">Materi Pokok</th>
                    <th class="c-indicator">Indikator Soal</th>
                    <th class="c-level">Level Kognitif<br>(Taksonomi Bloom)</th>
                    <th class="c-shape">Bentuk Soal</th>
                    <th class="c-qno">No. Soal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($doc['rows'] as $row)
                    <tr>
                        <td class="c-no">{{ $row['no'] }}</td>
                        <td class="c-element">{{ $row['element'] }}</td>
                        <td class="c-material">{!! nl2br(e($row['material'])) !!}</td>
                        <td class="c-indicator">{{ $row['indicator'] }}</td>
                        <td class="c-level">{{ $row['level'] }}</td>
                        <td class="c-shape">{{ $row['shape'] }}</td>
                        <td class="c-qno">{{ $row['question_no'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($doc['legend'] !== [])
        <div class="legend">{{ implode('    ', $doc['legend']) }}</div>
    @endif
    @if($doc['note'] !== '')
        <div class="note">Catatan: {{ $doc['note'] }}</div>
    @endif
    <table class="sign">
        <tr>
            <td>Mengetahui,<br>Kepala Sekolah<br><br><br>(_______________________)</td>
            <td>{{ $tanggalTandaTangan }}<br>Guru Mata Pelajaran<br><br><br>(_______________________)</td>
        </tr>
    </table>

    @if($doc['answer_sections'] !== [] || $doc['recap'] !== [])
        <div class="answer-page">
            <div class="answer-top">
                {{ $doc['answer_title'] }}
                @if($doc['answer_subtitle'] !== '')
                    <br>{{ $doc['answer_subtitle'] }}
                @endif
            </div>

            @foreach($doc['answer_sections'] as $section)
                <div class="section-title {{ str_contains(Str::upper($section['heading']), 'BENAR') ? 'section-green' : 'section-blue' }}">{{ $section['heading'] }}</div>
                <table class="answer-table">
                    <thead>
                        <tr>
                            @foreach(($section['headers'] ?: ['No.', 'Kunci', 'Jawaban']) as $head)
                                <th>{{ $head }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            <tr>
                                @foreach($row as $i => $cell)
                                    <td class="{{ $i === 0 ? 'num' : ($i === 1 ? 'key' : 'wide') }}">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach

            @if($doc['recap'] !== [])
                <div class="section-title section-blue">REKAP PENILAIAN</div>
                <table class="recap">
                    <thead>
                        <tr>
                            <th>Bagian</th>
                            <th>Bentuk Soal</th>
                            <th>Jumlah Soal</th>
                            <th>Skor per Soal</th>
                            <th>Total Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($doc['recap'] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($doc['recap_note'] !== '')
                <div class="note">Keterangan: {{ $doc['recap_note'] }}</div>
            @endif
        </div>
    @endif
@else
    <div class="fallback">{{ $content }}</div>
@endif
