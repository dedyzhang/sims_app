{{--
    Struktur dokumen perangkat ajar (RPM/LKPD/Modul Ajar) hasil LearningDocument::parse().
    SATU sumber markup untuk export PDF dan pratinjau layar — kelas CSS-nya sama, hanya
    stylesheet pembungkusnya yang berbeda (cetak vs web). Bila $doc['parsed'] false,
    pemanggil merender teks polos, bukan partial ini.
--}}
@php
    $renderCellLines = function (array $lines) {
        $html = '';
        foreach ($lines as $line) {
            $isBold = (bool) preg_match('/^.{1,60}:$/u', $line);
            $html .= '<div class="cell-line'.($isBold ? ' b' : '').'">'.e($line).'</div>';
        }

        return $html;
    };
@endphp

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

{{-- IDENTIFIKASI --}}
@if($doc['identifikasi'])
    <table class="tbl">
        @foreach($doc['identifikasi'] as $i => $row)
            <tr>
                <td class="sec">{{ $i === 0 ? 'IDENTIFIKASI' : '' }}</td>
                <td class="sub">{{ $row['label'] }}</td>
                <td>
                    @if(str_starts_with($row['label'], 'Dimensi Profil Lulusan') && $row['dpl'])
                        {!! $renderCellLines($row['intro'] ?: $row['lines']) !!}
                        @php
                            $half = (int) ceil(count($row['dpl']) / 2);
                            $left = array_slice($row['dpl'], 0, $half);
                            $right = array_slice($row['dpl'], $half);
                        @endphp
                        <table class="dpl">
                            @for($j = 0; $j < $half; $j++)
                                <tr>
                                    <td style="width:50%">@if(isset($left[$j])){{ $left[$j]['checked'] ? '☑' : '☐' }} {{ $left[$j]['label'] }}@endif</td>
                                    <td style="width:50%">@if(isset($right[$j])){{ $right[$j]['checked'] ? '☑' : '☐' }} {{ $right[$j]['label'] }}@endif</td>
                                </tr>
                            @endfor
                        </table>
                    @else
                        {!! $renderCellLines($row['lines']) !!}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif

{{-- DESAIN PEMBELAJARAN --}}
@if($doc['desain'])
    <table class="tbl">
        @foreach($doc['desain'] as $i => $row)
            <tr>
                <td class="sec">{{ $i === 0 ? 'DESAIN PEMBELAJARAN' : '' }}</td>
                <td class="sub">{{ $row['label'] }}</td>
                <td>{!! $renderCellLines($row['lines']) !!}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- PENGALAMAN BELAJAR --}}
@if($doc['pengalaman'])
    <table class="tbl">
        @foreach($doc['pengalaman'] as $i => $stage)
            <tr>
                <td class="sec">{{ $i === 0 ? 'PENGALAMAN BELAJAR' : '' }}</td>
                <td class="stagehead" colspan="2">
                    {{ $stage['heading'] }}
                    @if($stage['subtitle'] !== '')
                        <br><span class="pilar">{{ $stage['subtitle'] }}</span>
                    @endif
                </td>
            </tr>
            @if($stage['items'])
                <tr>
                    <td class="sec"></td>
                    <td class="sub"></td>
                    <td>
                        @foreach($stage['items'] as $item)
                            @if($item['type'] === 'check')
                                <div class="check">✓ {{ $item['text'] }}</div>
                            @elseif($item['type'] === 'quote')
                                <div class="quote">{{ $item['text'] }}</div>
                            @else
                                <div class="cell-line">{{ $item['text'] }}</div>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @endif
        @endforeach
    </table>
@endif

{{-- ASESMEN PEMBELAJARAN --}}
@if($doc['asesmen'])
    <table class="tbl">
        @foreach($doc['asesmen'] as $i => $row)
            <tr>
                <td class="sec">{{ $i === 0 ? 'ASESMEN PEMBELAJARAN' : '' }}</td>
                <td class="sub">{{ $row['label'] }}</td>
                <td>{!! $renderCellLines($row['lines']) !!}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Tanda tangan --}}
@if($doc['signature']['date'] !== '' || $doc['signature']['rows'])
    @if($doc['signature']['date'] !== '')
        <div class="ttd-date">{{ $doc['signature']['date'] }}</div>
    @endif
    @php
        $rows = $doc['signature']['rows'];
        $firstNameIdx = null;
        foreach ($rows as $k => $r) {
            if (preg_match('/^(NIK|NIP)\b/u', trim($r[0].$r[1]))) {
                $firstNameIdx = max(0, $k - 1);
                break;
            }
        }
    @endphp
    <table class="ttd">
        @foreach($rows as $k => $r)
            @if($firstNameIdx !== null && $k === $firstNameIdx)
                <tr><td class="ttd-spacer" colspan="2"></td></tr>
                <tr>
                    <td class="kiri b">{{ $r[0] }}</td>
                    <td class="kanan b">{{ $r[1] }}</td>
                </tr>
            @else
                <tr>
                    <td class="kiri">{{ $r[0] }}</td>
                    <td class="kanan">{{ $r[1] }}</td>
                </tr>
            @endif
        @endforeach
    </table>
@endif

{{-- Lampiran --}}
@foreach($doc['lampiran'] as $lampiran)
    <div class="lampiran">
        <div class="lampiran-heading">{{ $lampiran['heading'] }}</div>
        @foreach($lampiran['blocks'] as $block)
            @if($block['type'] === 'table')
                <table class="rubrik">
                    @foreach($block['rows'] as $ri => $cells)
                        <tr class="{{ $ri === 0 ? 'head' : '' }}">
                            @foreach($cells as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @else
                @foreach($block['lines'] as $line)
                    @php
                        $isOpsi = (bool) preg_match('/^[a-e][\.\)]\s/u', $line);
                        $isNomor = (bool) preg_match('/^\d{1,2}[\.\)]\s/u', $line);
                        $isBold = (bool) preg_match('/^[A-Z][\.\)]\s/u', $line)
                            || (bool) preg_match('/^.{1,60}:$/u', $line);
                        $bulletMeta = str_starts_with($line, '•') ? explode(':', $line, 2) : null;
                    @endphp
                    @if($bulletMeta !== null && count($bulletMeta) === 2)
                        <div class="cell-line"><span class="b">{{ rtrim($bulletMeta[0]) }}</span> : {{ trim($bulletMeta[1]) }}</div>
                    @else
                        <div class="cell-line {{ $isOpsi ? 'opsi' : ($isNomor ? 'nomor' : '') }} {{ $isBold ? 'b' : '' }}">{{ $line }}</div>
                    @endif
                @endforeach
            @endif
        @endforeach
    </div>
@endforeach
