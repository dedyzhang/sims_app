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
    @if(!empty($doc['kop']))
        <div class="kop">
            @foreach($doc['kop'] as $i => $line)
                <div class="{{ $i === 0 ? 'nama' : ($i === 1 ? 'sub' : 'alamat') }}">{{ $line }}</div>
            @endforeach
        </div>
    @endif

    <div class="blueprint-header text-center">
        <div class="title">{{ $doc['title'] ?: 'KISI-KISI SOAL' }}</div>
        @if(!empty($doc['subject']))
            <div class="subtitle">{{ $doc['subject'] }}</div>
        @endif
        @if(!empty($doc['academic_year']))
            <div class="subsubtitle">{{ $doc['academic_year'] }}</div>
        @endif
    </div>

    @if($doc['identity'] !== [])
        <table class="identity-clean">
            <colgroup>
                <col style="width: 170px;">
                <col style="width: auto;">
            </colgroup>
            @foreach($doc['identity'] as $item)
                <tr>
                    <td class="id-label">{{ $item['label'] }}</td>
                    <td class="id-val">: {{ $item['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($doc['competencies']) && ($doc['table_type'] ?? '') !== '10col')
        <div class="competencies-box">
            <div class="competencies-title">Tujuan Pembelajaran / Capaian Pembelajaran:</div>
            <ol class="competencies-list">
                @foreach($doc['competencies'] as $comp)
                    <li>{{ $comp }}</li>
                @endforeach
            </ol>
        </div>
    @endif

    @if($doc['rows'] !== [])
        @php
            $is10Col = ($doc['table_type'] ?? '') === '10col'
                || isset($doc['rows'][0]['cp'])
                || isset($doc['rows'][0]['tp']);
        @endphp
        <table class="blueprint">
            @if($is10Col)
                <colgroup>
                    <col style="width: 3.5%;">
                    <col style="width: 11%;">
                    <col style="width: 10%;">
                    <col style="width: 9%;">
                    <col style="width: 12%;">
                    <col style="width: 5%;">
                    <col style="width: 27.5%;">
                    <col style="width: 8%;">
                    <col style="width: 6%;">
                    <col style="width: 8%;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="c-no">NO</th>
                        <th class="c-cp">Capaian Pembelajaran</th>
                        <th class="c-material">Lingkup Materi</th>
                        <th class="c-element">Elemen</th>
                        <th class="c-tp">Tujuan Pembelajaran</th>
                        <th class="c-class">Kelas</th>
                        <th class="c-indicator">Indikator Soal</th>
                        <th class="c-level">Level Kognitif</th>
                        <th class="c-qno">Nomor Soal</th>
                        <th class="c-shape">Bentuk Soal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($doc['rows'] as $row)
                        <tr>
                            <td class="c-no">{{ $row['no'] }}</td>
                            <td class="c-cp">{{ $row['cp'] ?? '-' }}</td>
                            <td class="c-material">{!! nl2br(e($row['material'] ?? '-')) !!}</td>
                            <td class="c-element">{{ $row['element'] ?? '-' }}</td>
                            <td class="c-tp">{{ $row['tp'] ?? '-' }}</td>
                            <td class="c-class">{{ $row['kelas'] ?? '-' }}</td>
                            <td class="c-indicator">{{ $row['indicator'] ?? '-' }}</td>
                            <td class="c-level">{{ $row['level'] ?? '-' }}</td>
                            <td class="c-qno">{{ $row['question_no'] ?? $row['no'] }}</td>
                            <td class="c-shape">{{ $row['shape'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @else
                <colgroup>
                    <col style="width: 4%;">
                    <col style="width: 15%;">
                    <col style="width: 19%;">
                    <col style="width: 33%;">
                    <col style="width: 10%;">
                    <col style="width: 11%;">
                    <col style="width: 8%;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="c-no">No.</th>
                        <th class="c-element">Elemen / Capaian Pembelajaran</th>
                        <th class="c-material">Materi Pokok</th>
                        <th class="c-indicator">Indikator Soal</th>
                        <th class="c-level">Level Kognitif</th>
                        <th class="c-shape">Bentuk Soal</th>
                        <th class="c-qno">No. Soal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($doc['rows'] as $row)
                        <tr>
                            <td class="c-no">{{ $row['no'] }}</td>
                            <td class="c-element">{{ $row['element'] ?? '-' }}</td>
                            <td class="c-material">{!! nl2br(e($row['material'] ?? '-')) !!}</td>
                            <td class="c-indicator">{{ $row['indicator'] ?? '-' }}</td>
                            <td class="c-level">{{ $row['level'] ?? '-' }}</td>
                            <td class="c-shape">{{ $row['shape'] ?? '-' }}</td>
                            <td class="c-qno">{{ $row['question_no'] ?? $row['no'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
    @endif

    @if(!empty($doc['legend']))
        <div class="legend"><strong>Legenda Level Kognitif:</strong> {{ implode('    ', $doc['legend']) }}</div>
    @endif
    @if(!empty($doc['note']))
        <div class="note"><strong>Catatan:</strong> {{ $doc['note'] }}</div>
    @endif

    <table class="sign">
        <tr>
            <td style="width: 50%; text-align: center;">
                Mengetahui,<br>Kepala Sekolah<br><br><br><br>
                <strong>({{ $doc['kepala_sekolah'] ?? '_______________________' }})</strong>
            </td>
            <td style="width: 50%; text-align: center;">
                {{ $tanggalTandaTangan }}<br>Guru Mata Pelajaran<br><br><br><br>
                <strong>({{ $doc['guru_mapel'] ?? '_______________________' }})</strong>
            </td>
        </tr>
    </table>

    @if(!empty($doc['answer_sections']) || !empty($doc['recap']))
        <div class="answer-page">
            <div class="answer-top">
                {{ $doc['answer_title'] }}
                @if($doc['answer_subtitle'] !== '')
                    <br><span style="font-size: 0.85em; font-weight: normal;">{{ $doc['answer_subtitle'] }}</span>
                @endif
            </div>

            @foreach($doc['answer_sections'] as $section)
                <div class="section-title {{ str_contains(Str::upper($section['heading']), 'BENAR') ? 'section-green' : 'section-blue' }}">{{ $section['heading'] }}</div>
                @php
                    $headers = $section['headers'] ?: ['No.', 'Kunci', 'Jawaban'];
                    $headerCount = count($headers);
                @endphp
                <table class="answer-table">
                    <colgroup>
                        @if($headerCount === 2)
                            <col style="width: 12%;">
                            <col style="width: 88%;">
                        @elseif($headerCount === 3)
                            @if(str_contains(Str::upper($headers[1] ?? ''), 'ISTILAH') || str_contains(Str::upper($headers[0] ?? ''), 'SOAL'))
                                <col style="width: 10%;">
                                <col style="width: 45%;">
                                <col style="width: 45%;">
                            @else
                                <col style="width: 8%;">
                                <col style="width: 18%;">
                                <col style="width: 74%;">
                            @endif
                        @elseif($headerCount === 4)
                            <col style="width: 8%;">
                            <col style="width: 18%;">
                            <col style="width: 37%;">
                            <col style="width: 37%;">
                        @else
                            @for($c = 0; $c < $headerCount; $c++)
                                <col style="width: {{ round(100 / max(1, $headerCount), 1) }}%;">
                            @endfor
                        @endif
                    </colgroup>
                    <thead>
                        <tr>
                            @foreach($headers as $idx => $head)
                                <th class="{{ $idx === 0 || ($idx === 1 && $headerCount === 3 && !str_contains(Str::upper($headers[1] ?? ''), 'ISTILAH')) ? 'text-center' : 'text-left' }}">{{ $head }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            @if(!empty($row))
                                <tr>
                                    @foreach($row as $i => $cell)
                                        <td class="{{ $i === 0 ? 'num' : ($i === 1 && $headerCount === 3 && !str_contains(Str::upper($headers[1] ?? ''), 'ISTILAH') ? 'key' : 'wide') }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endforeach

            @if(!empty($doc['recap']))
                <div class="section-title section-blue">REKAP PENILAIAN</div>
                <table class="recap">
                    <colgroup>
                        <col style="width: 12%;">
                        <col style="width: 34%;">
                        <col style="width: 18%;">
                        <col style="width: 18%;">
                        <col style="width: 18%;">
                    </colgroup>
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
                            @if(!empty($row))
                                <tr>
                                    @foreach($row as $cIdx => $cell)
                                        <td class="{{ $cIdx === 1 ? 'text-left' : 'text-center' }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($doc['recap_note'] !== '')
                <div class="note"><strong>Keterangan:</strong> {{ $doc['recap_note'] }}</div>
            @endif
        </div>
    @endif
@else
    <div class="fallback">{{ $content }}</div>
@endif
