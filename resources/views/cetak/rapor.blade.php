<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapor {{ $kelas->tingkat }}{{ $kelas->kelas }} &mdash; {{ $sekolah['nama'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Georgia, serif; color: #000; margin: 0; }
        .fs-9 { font-size: 9px; } .fs-10 { font-size: 10px; } .fs-11 { font-size: 11px; } .fs-12 { font-size: 14px; } .fs-13 { font-size: 14.5px; }

        /* ── Halaman: kotak ukuran F4 (8.5in x 13in), footer di bawah ── */
        .page {
            position: relative;
            width: 215.9mm; height: 329mm;
            padding: 9mm 11mm 8mm;
            display: flex; flex-direction: column;
            background: #fff; overflow: hidden;
            page-break-after: always;
        }
        .page-body { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
        .fill { flex: 1 1 auto; }
        .fill-half { flex: 0.1 1 auto; }

        /* ── Kop surat ── */
        .kop { display: flex; align-items: center; gap: 12px; border-bottom: 5px double #000; padding-bottom: 6px; }
        .kop .logo { width: 72px; height: 72px; object-fit: contain; flex: 0 0 auto; }
        .kop .ident { flex: 1; text-align: center; }
        .kop .ident .nm { font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin: 0; }
        .kop .ident .ad { font-size: 13px; margin: 1px 0 0; }
        .kop .ident p { margin: 1px 0; }   /* rapikan paragraf dari editor teks kop */
        .kop .ident ul, .kop .ident ol { margin: 2px 0; padding-left: 22px; text-align: left; display: inline-block; }
        .judul { text-align: center; font-weight: 700; font-size: 17px; letter-spacing: .5px; margin: 8px 0 6px; }
        .judul small { display: block; font-size: 14px; }

        /* ── Identitas ── */
        .ident-grid { width: 100%; border-collapse: collapse; font-size: 13.5px; margin-bottom: 4px; }
        .ident-grid td { padding: 1px 0; vertical-align: top; }
        .ident-grid .l { width: 95px; } .ident-grid .s { width: 10px; } .ident-grid .v { font-weight: 600; }
        .ident-grid .l2 { width: 120px; }

        /* ── Tabel nilai ── */
        table.tbl { width: 100%; border-collapse: collapse; border: 1px solid #3f3f3f; table-layout: fixed; }
        .nilai-fill { height: 100%; }
        table.tbl td, table.tbl th { border: 1px solid #3f3f3f; padding: 2.5px 5px; vertical-align: middle; }
        table.tbl thead td { background: #95b1ff !important; text-align: center; font-weight: 700; font-size: 12.5px; height: 32px; }
        .t-no { width: 7%; text-align: center; font-size: 12px; }
        .t-mapel { width: 26%; font-size: 12.5px; font-weight: 600; }
        .t-nilai { width: 9%; text-align: center; font-size: 16px; font-weight: 700; }
        .t-cap { width: 58%; font-size: 11.5px; line-height: 1.3; padding-top: 1.5px !important; padding-bottom: 1.5px !important; height: 35px; }

        .sub-h { font-weight: 700; font-size: 16px; margin: 12px 0 4px; }

        /* ── Ekskul / absensi ── */
        table.mini td { border: 1px solid #3f3f3f; padding: 5px 8px; font-size: 13px; vertical-align: middle; }
        table.mini { width: 100%; border-collapse: collapse; }

        /* ── Penjabaran ── */
        table.penj { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 13.5px; }
        table.penj td { border: 1px solid #3f3f3f; padding: 3px 7px; }
        table.penj .hd td { background: #95b1ff !important; font-weight: 700; }
        table.penj .sum td { background: #cfe1ff !important; }

        /* ── Tanda tangan ── */
        .ttd { display: flex; justify-content: space-between; margin-top: 16px; font-size: 15px; }
        .ttd .b { text-align: center; width: 46%; }
        .ttd .b .nm { font-weight: 700; margin-top: 48px; margin-bottom: 0; }
        .ttd-kepala { text-align: center; font-size: 15px; margin-top: 14px; }
        .ttd-kepala .nm { font-weight: 700; margin-top: 48px; margin-bottom: 0; }

        /* ── Penjabaran Page Compact Overrides ── */
        .page-penjabaran .kop { padding-bottom: 4px; }
        .page-penjabaran .judul { margin: 6px 0 4px; font-size: 17px; }
        .page-penjabaran .judul small { font-size: 13.5px; }
        .page-penjabaran .ident-grid { font-size: 13.5px; margin-bottom: 4px; }
        .page-penjabaran table.penj { margin-bottom: 6px; font-size: 12px; }
        .page-penjabaran table.penj td { padding: 2px 5px; }
        .page-penjabaran .ttd { margin-top: 10px; font-size: 13.5px; }
        .page-penjabaran .ttd .b .nm { margin-top: 36px; }

        .keputusan { border: 1px solid #3f3f3f; padding: 8px 12px; font-size: 14px; width: 62%; margin-top: 12px; }
        .keputusan .ln { border-bottom: 1px dotted #000; display: inline-block; min-width: 220px; }

        /* ── Footer tiap halaman ── */
        .page-foot { flex: 0 0 auto; border-top: 1px dotted #000; padding-top: 3px; margin-top: 6px; font-size: 13px; line-height: 1.55; }
        .page-foot .sw { display: inline-block; width: 22px; height: 11px; vertical-align: middle; margin-right: 8px; }
        .page-foot .sw1 { background: #95b1ff; } .page-foot .sw2 { background: #cfe1ff; }

        /* ── Toolbar (layar saja) ── */
        .toolbar { position: sticky; top: 0; z-index: 50; background: #0f172a; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; font-family: system-ui, sans-serif; }
        .toolbar .muted { color: #94a3b8; font-size: 12px; }

        @page { size: 215.9mm 330.2mm; margin: 0; }
        @media screen {
            body { background: #e9edf3; }
            .page { margin: 16px auto; box-shadow: 0 6px 24px rgba(0,0,0,.16); }
        }
        @media print {
            .toolbar { display: none !important; }
            .page { margin: 0; box-shadow: none; }
            .page:last-child { page-break-after: auto; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            table.tbl thead td, table.penj .hd td { background: #95b1ff !important; }
            table.penj .sum td, .page-foot .sw2 { background: #cfe1ff !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <b>Cetak Rapor</b>
            <span class="muted">&nbsp;Kelas {{ $kelas->tingkat }}{{ $kelas->kelas }} &middot; Semester {{ $sem?->semester }} &middot; {{ $sem?->tahun }} &middot; {{ $siswas->count() }} siswa</span>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('cetak.rapor.index', ['kelas' => $kelas->uuid]) }}" class="btn btn-outline-light btn-sm">&larr; Kembali</a>
            <button class="btn btn-light btn-sm fw-semibold" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
        </div>
    </div>

    @php
        $wm = $kopBackdrop;
        $tutwuri = $kopLogoKiri;
        $logoSek = $kopLogoKanan;
        $tahun = $sem?->tahun;
        $kota = $sekolah['kota'] ?: 'Tanjungpinang';

        $wmStyle = fn ($pos) => $wm
            ? "background-color:#fff;background-image:linear-gradient(rgba(255,255,255,.86),rgba(255,255,255,.86)),url('{$wm}');background-repeat:no-repeat;background-position:center,{$pos};background-size:cover,68% auto;"
            : 'background-color:#fff;';

        $kop = function () use ($sekolah, $tutwuri, $logoSek, $kopTeks) {
            ob_start(); ?>
            <div class="kop">
                <?php if ($tutwuri): ?><img src="<?= $tutwuri ?>" class="logo" alt=""><?php else: ?><span class="logo"></span><?php endif; ?>
                <div class="ident">
                    <?php if (trim((string) $kopTeks) !== ''): ?>
                        <?= $kopTeks /* HTML kustom dari admin */ ?>
                    <?php else: ?>
                        <p class="nm"><?= e($sekolah['nama']) ?></p>
                        <?php if ($sekolah['alamat']): ?><p class="ad"><?= e($sekolah['alamat']) ?></p><?php endif; ?>
                        <p class="ad"><?= e(trim(implode(', ', array_filter([$sekolah['kota'], $sekolah['provinsi']])))) ?><?php if ($sekolah['telp']): ?> &middot; Telp. <?= e($sekolah['telp']) ?><?php endif; ?><?php if ($sekolah['npsn']): ?> &middot; NPSN <?= e($sekolah['npsn']) ?><?php endif; ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($logoSek): ?><img src="<?= $logoSek ?>" class="logo" alt=""><?php else: ?><span class="logo"></span><?php endif; ?>
            </div>
            <?php return ob_get_clean();
        };

        $foot = function ($siswa) use ($sekolah, $kota, $kelas, $sem, $tahun) {
            ob_start(); ?>
            <div class="page-foot">
                <div><span class="sw sw1"></span> Rapor <?= e($sekolah['nama']) ?> <?= e($kota) ?></div>
                <div><span class="sw sw2"></span> <?= e($siswa->nis) ?> | <?= e($siswa->nama) ?> | Kelas <?= e($kelas->tingkat.$kelas->kelas) ?> | Semester <?= e($sem?->semester) ?> | <?= e($tahun) ?></div>
            </div>
            <?php return ob_get_clean();
        };

        $identitas = function ($siswa, $withFase = true) use ($kelas, $sekolah, $sem, $tahun, $fase) {
            ob_start(); ?>
            <table class="ident-grid">
                <tr>
                    <td class="l">Nama</td><td class="s">:</td><td class="v"><?= e($siswa->nama) ?></td>
                    <td class="l2">Kelas</td><td class="s">:</td><td class="v"><?= e($kelas->tingkat.$kelas->kelas) ?></td>
                </tr>
                <tr>
                    <td class="l">No Induk</td><td class="s">:</td><td class="v"><?= e($siswa->nis) ?></td>
                    <td class="l2"><?= $withFase ? 'Fase' : 'Semester' ?></td><td class="s">:</td><td class="v"><?= $withFase ? e($fase) : e($sem?->semester).' ( '.($sem?->semester == 1 ? 'Ganjil' : 'Genap').' )' ?></td>
                </tr>
                <tr>
                    <td class="l">Sekolah</td><td class="s">:</td><td class="v"><?= e($sekolah['nama']) ?></td>
                    <td class="l2"><?= $withFase ? 'Semester' : 'Tahun Pelajaran' ?></td><td class="s">:</td><td class="v"><?= $withFase ? e($sem?->semester).' ( '.($sem?->semester == 1 ? 'Ganjil' : 'Genap').' )' : e($tahun) ?></td>
                </tr>
                <?php if ($withFase): ?>
                <tr>
                    <td class="l">Wali Kelas</td><td class="s">:</td><td class="v"><?= e(optional($GLOBALS['__wk'])->nama ?? '-') ?></td>
                    <td class="l2">Tahun Pelajaran</td><td class="s">:</td><td class="v"><?= e($tahun) ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <?php return ob_get_clean();
        };

        // Blok Ekstrakurikuler + Ketidakhadiran + Keputusan + Tanda tangan.
        $blokEkskulTtd = function ($siswa) use ($ekskulRows, $absensi, $sem, $kota, $tanggal, $walikelas, $kepala, $nikKepala, $sekolah) {
            $eksS = $ekskulRows[$siswa->uuid] ?? [];
            $absS = $absensi[$siswa->uuid] ?? [];
            ob_start(); ?>
            <div class="sub-h">Ekstrakurikuler</div>
            <table class="mini"><tbody>
                <?php foreach ($eksS as $e): ?>
                <tr style="height:28px"><td style="width:24%"><?= e($e['nama']) ?></td><td><?= e($e['desk']) ?></td></tr>
                <?php endforeach; for ($i = count($eksS); $i < 3; $i++): ?>
                <tr style="height:28px"><td style="width:24%">&nbsp;</td><td></td></tr>
                <?php endfor; ?>
            </tbody></table>

            <div class="sub-h">Ketidakhadiran</div>
            <table class="mini" style="width:48%"><tbody>
                <tr><td style="width:60%">Sakit</td><td class="text-center"><?= (int) ($absS['sakit'] ?? 0) ?></td></tr>
                <tr><td>Izin</td><td class="text-center"><?= (int) ($absS['izin'] ?? 0) ?></td></tr>
                <tr><td>Tanpa Keterangan (Alpha)</td><td class="text-center"><?= (int) (($absS['alpa'] ?? 0) + ($absS['alpha'] ?? 0)) ?></td></tr>
            </tbody></table>

            <?php if ($sem?->semester == 2): ?>
            <div class="keputusan">
                <p class="m-0"><b>Keputusan :</b></p>
                <p class="m-0">Berdasarkan pencapaian kompetensi pada semester ke-1 dan ke-2, peserta didik ditetapkan *):</p>
                <p class="m-0 mt-1">Naik / Tinggal di kelas <span class="ln"></span></p>
                <p class="m-0 mt-1" style="font-style:italic">*) Coret yang tidak perlu</p>
            </div>
            <?php endif; ?>

            <div class="fill"></div>

            <div class="ttd">
                <div class="b">
                    <p class="m-0">Mengetahui,</p>
                    <p class="m-0">Orang Tua / Wali Murid</p>
                    <p class="nm">(......................................)</p>
                </div>
                <div class="b">
                    <p class="m-0"><?= e($kota) ?>, <?= e($tanggal) ?></p>
                    <p class="m-0">Wali Kelas</p>
                    <p class="nm"><?= e($walikelas?->nama ?? '......................................') ?></p>
                    <p class="m-0">NIK. <?= e($walikelas?->nik ?: '-') ?></p>
                </div>
            </div>
            <div class="ttd-kepala">
                <p class="m-0">Mengetahui,</p>
                <p class="m-0">Kepala <?= e($sekolah['nama']) ?></p>
                <p class="nm"><?= e($kepala ?: '......................................') ?></p>
                <p class="m-0">NIK. <?= e($nikKepala ?: '-') ?></p>
            </div>
            <?php return ob_get_clean();
        };

        $GLOBALS['__wk'] = $walikelas;
    @endphp

    @php $hdrNilai = '<thead><tr><td class="t-no">No</td><td class="t-mapel" style="text-align:center">Mata Pelajaran</td><td class="t-nilai" style="text-align:center">Nilai</td><td class="t-cap" style="text-align:center">Capaian Kompetensi</td></tr></thead>'; @endphp

    @foreach($siswas as $siswa)
    @php
        $chunks = $ngajarMapel->values()->chunk(8)->values();   // 8 mapel / halaman
        $lastIdx = $chunks->count() - 1;
        $lastFull = $chunks->isNotEmpty() && $chunks->last()->count() === 8;
        $adaPenj = collect($penjabaran)->contains(function ($p) use ($siswa) {
            foreach ($p['komponen'] as $k) { if (($p['nilai'][$siswa->uuid][$k->uuid] ?? null) !== null) return true; }
            return false;
        });
    @endphp

    {{-- ══════════ HALAMAN NILAI (adaptif: 8 mapel/halaman) ══════════ --}}
    @forelse($chunks as $ci => $chunk)
        @php $appendEkskul = ($ci === $lastIdx) && !$lastFull; @endphp
        <div class="page" style="{{ $wmStyle($ci === 0 ? 'center 58%' : 'center') }}">
            <div class="page-body">
                @if($ci === 0)
                    {!! $kop() !!}
                    <div class="judul">PENCAPAIAN KOMPETENSI PESERTA DIDIK</div>
                    {!! $identitas($siswa, true) !!}
                @endif
                <div class="{{ $appendEkskul ? 'fill-half' : 'fill' }}" style="display:flex; flex-direction:column;">
                    <table class="tbl nilai-fill">
                        {!! $hdrNilai !!}
                        <tbody>
                            @foreach($chunk as $i => $ng)
                                @php $o = $olah[$ng->uuid][$siswa->uuid] ?? null; @endphp
                                <tr>
                                    <td rowspan="2" class="t-no">{{ $i + 1 }}</td>
                                    <td rowspan="2" class="t-mapel">{{ $ng->pelajaran?->nama }}</td>
                                    <td rowspan="2" class="t-nilai">{{ $o['nilai'] ?? 0 }}</td>
                                    <td class="t-cap">{{ $o['pos'] ?? '' }}</td>
                                </tr>
                                <tr><td class="t-cap">{{ $o['neg'] ?? '' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($appendEkskul)
                    {!! $blokEkskulTtd($siswa) !!}
                @endif
            </div>
            {!! $foot($siswa) !!}
        </div>
    @empty
    @endforelse

    {{-- Bila chunk terakhir penuh (atau tak ada mapel): ekskul/ttd di halaman tersendiri --}}
    @if($lastFull || $chunks->isEmpty())
    <div class="page" style="{{ $wmStyle('center') }}">
        <div class="page-body">
            @if($chunks->isEmpty())
                {!! $kop() !!}
                <div class="judul">PENCAPAIAN KOMPETENSI PESERTA DIDIK</div>
                {!! $identitas($siswa, true) !!}
            @endif
            {!! $blokEkskulTtd($siswa) !!}
        </div>
        {!! $foot($siswa) !!}
    </div>
    @endif

    {{-- ══════════ HALAMAN PENJABARAN ══════════ --}}
    @if($adaPenj)
    <div class="page page-penjabaran" style="{{ $wmStyle('center') }}">
        <div class="page-body">
            {!! $kop() !!}
            <div class="judul">PENCAPAIAN KOMPETENSI PESERTA DIDIK <small>PENJABARAN NILAI</small></div>
            {!! $identitas($siswa, false) !!}
            <div class="fill">
                @php $huruf = ['A','B','C','D','E','F','G','H']; $bi = 0; @endphp
                @foreach($penjabaran as $p)
                    @php
                        $punya = false;
                        foreach ($p['komponen'] as $k) { if (($p['nilai'][$siswa->uuid][$k->uuid] ?? null) !== null) { $punya = true; break; } }
                    @endphp
                    @if($punya)
                    @php $kmb = $p['kktp']; $no = 1; $jml = 0; $cnt = 0; @endphp
                    <p class="m-0 mt-1 fs-12">Ketuntasan Minimal Belajar : {{ $kmb }}</p>
                    <table class="penj">
                        <tr class="hd"><td colspan="4">{{ $huruf[$bi] ?? '' }}. {{ $p['nama'] }}</td></tr>
                        @foreach($p['komponen'] as $k)
                            @php $nv = $p['nilai'][$siswa->uuid][$k->uuid] ?? null; @endphp
                            @if($nv !== null)
                            <tr>
                                <td style="width:5%">{{ $no }}</td>
                                <td style="width:40%">{{ $k->nama }}</td>
                                <td style="width:10%; text-align:center">{{ $nv }}</td>
                                <td style="width:45%">@if($nv < $kmb){{ $sem?->semester == 1 ? 'Belum Tuntas' : 'Tidak Tuntas' }}@elseif($nv == $kmb)Tuntas @else Terlampaui @endif</td>
                            </tr>
                            @php $no++; $cnt++; $jml += $nv; @endphp
                            @endif
                        @endforeach
                        <tr class="sum"><td colspan="2"><b>Jumlah</b></td><td style="text-align:center"><b>{{ $jml }}</b></td><td></td></tr>
                        <tr class="hd"><td colspan="2"><b>Rata-rata</b></td><td style="text-align:center"><b>{{ $cnt ? round($jml / $cnt, 2) : 0 }}</b></td><td></td></tr>
                    </table>
                    @php $bi++; @endphp
                    @endif
                @endforeach
            </div>
            <div class="ttd">
                <div class="b">
                    <p class="m-0">Mengetahui,</p>
                    <p class="m-0">Orang Tua / Wali Murid</p>
                    <p class="nm">(......................................)</p>
                </div>
                <div class="b">
                    <p class="m-0">{{ $kota }}, {{ $tanggal }}</p>
                    <p class="m-0">Wali Kelas</p>
                    <p class="nm">{{ $walikelas?->nama ?? '......................................' }}</p>
                    <p class="m-0">NIK. {{ $walikelas?->nik ?: '-' }}</p>
                </div>
            </div>
        </div>
        {!! $foot($siswa) !!}
    </div>
    @endif
    @endforeach
</body>
</html>
