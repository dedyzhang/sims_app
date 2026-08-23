<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Berita Acara Ujian</title>
    <style>
        body { font-family: sans-serif; font-size: 11pt; color: #333; line-height: 1.3; margin: 0; padding: 0; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop h1 { margin: 0; font-size: 14pt; text-transform: uppercase; }
        .kop h2 { margin: 3px 0 0; font-size: 12pt; font-weight: normal; }
        .kop p { margin: 3px 0 0; font-size: 9pt; }
        .judul-dokumen { text-align: center; font-size: 12pt; font-weight: bold; margin-bottom: 5px; }
        .sub-judul { text-align: center; font-size: 10pt; margin-bottom: 20px; }
        
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.data th, table.data td { border: 1px solid #000; padding: 6px; font-size: 9pt; vertical-align: top; }
        table.data th { background-color: #f0f0f0; text-align: center; font-weight: bold; }
        
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .italic { font-style: italic; color: #555; }
        .bg-adhoc { background-color: #fff9c4; font-size: 8pt; padding: 2px 4px; border-radius: 2px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $sekolah->nama ?? 'SEKOLAH KITA' }}</h1>
        <h2>REKAPITULASI AGENDA DAN BERITA ACARA UJIAN</h2>
        <p>{{ $sekolah->alamat ?? '' }}</p>
    </div>

    <div class="judul-dokumen">
        REKAPITULASI HARIAN AGENDA &amp; BERITA ACARA UJIAN
    </div>
    <div class="sub-judul">
        Tanggal: {{ \Carbon\Carbon::parse($tanggalString)->isoFormat('dddd, D MMMM Y') }}
    </div>

    @if(empty($rekap))
        <p class="text-center italic">Tidak ada jadwal atau rekaman ujian pada tanggal ini.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Ruangan / Paket</th>
                    <th width="30%">Agenda / Mapel</th>
                    <th width="15%">Waktu</th>
                    <th width="20%">Pengawas</th>
                    <th width="5%">BA</th>
                    <th width="10%">Hadir/Tot</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($rekap as $baris)
                    @php
                        $rowspan = count($baris['agendas']) > 0 ? count($baris['agendas']) : 1;
                    @endphp
                    
                    @if(count($baris['agendas']) === 0)
                        <tr>
                            <td class="text-center">{{ $no++ }}</td>
                            <td>
                                <div class="font-bold">{{ $baris['ruangan']->nama }}</div>
                                <div style="font-size:8pt">{{ $baris['ruangan']->paket->nama }}</div>
                            </td>
                            <td colspan="5" class="italic text-center">Tidak ada sesi di ruangan ini pada tanggal terpilih.</td>
                        </tr>
                    @else
                        @foreach($baris['agendas'] as $idx => $agenda)
                            @php 
                                $ba = $agenda['berita_acara']; 
                                $sesi = $agenda['sesi'];
                            @endphp
                            <tr>
                                @if($idx === 0)
                                    <td class="text-center" rowspan="{{ $rowspan }}">{{ $no++ }}</td>
                                    <td rowspan="{{ $rowspan }}">
                                        <div class="font-bold">{{ $baris['ruangan']->nama }}</div>
                                        <div style="font-size:8pt">{{ $baris['ruangan']->paket->nama }}</div>
                                    </td>
                                @endif
                                
                                <td>
                                    @if($agenda['tipe'] === 'adhoc')
                                        <span class="bg-adhoc">AD-HOC</span><br>
                                        {{ $ba ? $ba->ujianList->pluck('pelajaran.nama')->filter()->implode(', ') : 'Tanpa Jadwal' }}
                                    @else
                                        {{ $sesi->mapelNama() ?: 'Agenda tanpa mapel' }}
                                        <div style="font-size:8pt; color:#666">Sesi {{ $sesi->label }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($ba && $ba->jam_mulai_aktual)
                                        {{ substr($ba->jam_mulai_aktual, 0, 5) }} - {{ substr($ba->jam_selesai_aktual, 0, 5) }}
                                    @elseif($sesi)
                                        {{ substr($sesi->jam_mulai, 0, 5) }} - {{ substr($sesi->jam_selesai, 0, 5) }}<br>
                                        <span style="font-size:7pt">(Tjd)</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($ba && $ba->pengawas)
                                        {{ $ba->pengawas->nama }}
                                    @else
                                        <span class="italic">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($ba)
                                        &#10003; <!-- Checkmark -->
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($ba)
                                        {{ $ba->jumlah_hadir }} / {{ $ba->jumlah_hadir + $ba->jumlah_tidak_hadir }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
    
    <table width="100%" style="margin-top: 30px;">
        <tr>
            <td width="70%"></td>
            <td width="30%" class="text-center">
                Dicetak pada: {{ now()->isoFormat('D MMMM Y, HH:mm') }}<br>
                Oleh Admin Ujian
            </td>
        </tr>
    </table>
</body>
</html>
