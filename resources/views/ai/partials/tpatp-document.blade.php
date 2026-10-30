{{--
    Dokumen Tujuan Pembelajaran (TP) dan Alur Tujuan Pembelajaran (ATP) Kurikulum Merdeka.
    Dipakai bersama oleh pratinjau layar (interaktif & responsif) dan ekspor PDF landscape
    sehingga format visual yang dilihat guru identik dengan dokumen cetak resmi.
--}}
@php
    $identity = $doc['identity'] ?? [];
    $mapel = $identity['mapel'] ?? '-';
    $faseKelas = $identity['fase_kelas'] ?? 'D/VII-IX';
    $tahun = $identity['tahun'] ?? '-';
    $sig = $doc['signature'] ?? [];
    $hasTp = !empty($doc['has_tp']) || !empty($doc['tp_table']['rows']);
    $hasAtp = !empty($doc['has_atp']) || !empty($doc['atp_diagram']['nodes']);

    $isPdf = $isPdf ?? false;
    $kop = $kop ?? ($doc['kop'] ?? \App\Support\SchoolLetterhead::lines());
    $kopTeksHtml = $kopTeksHtml ?? ($doc['kop_html'] ?? \App\Support\SchoolLetterhead::kopTeksHtml());
    $kopLogoKiri = $kopLogoKiri ?? ($isPdf
        ? ($doc['kop_logo_kiri_data_uri'] ?? \App\Support\SchoolLetterhead::logoKiriDataUri())
        : ($doc['kop_logo_kiri'] ?? \App\Support\SchoolLetterhead::logoKiriUrl()));
    $kopLogoKanan = $kopLogoKanan ?? ($isPdf
        ? ($doc['kop_logo_kanan_data_uri'] ?? \App\Support\SchoolLetterhead::logoKananDataUri())
        : ($doc['kop_logo_kanan'] ?? \App\Support\SchoolLetterhead::logoKananUrl()));
@endphp

<div class="tpatp-doc-container">
    {{-- ==================== 1. BAGIAN TUJUAN PEMBELAJARAN (TP) ==================== --}}
    @if($hasTp)
    <section class="tpatp-section tpatp-page-tp">
        {{-- KOP SURAT RESMI SEKOLAH DARI APLIKASI SIMS --}}
        <div class="tpatp-kop">
            <table class="tpatp-kop-table">
                <tr>
                    <td class="tpatp-kop-logo-cell tpatp-kop-logo-left">
                        @if(!empty($kopLogoKiri))
                            <img src="{{ $kopLogoKiri }}" alt="Logo Tut Wuri" class="tpatp-kop-logo">
                        @endif
                    </td>
                    <td class="tpatp-kop-center-cell">
                        <div class="tpatp-kop-text">
                            @if(!empty($kopTeksHtml))
                                {!! $kopTeksHtml !!}
                            @elseif(!empty($kop))
                                @foreach($kop as $idx => $line)
                                    <div class="tpatp-kop-line {{ $idx === 0 ? 'tpatp-kop-line-main' : '' }}">{{ $line }}</div>
                                @endforeach
                            @endif
                        </div>
                    </td>
                    <td class="tpatp-kop-logo-cell tpatp-kop-logo-right">
                        @if(!empty($kopLogoKanan))
                            <img src="{{ $kopLogoKanan }}" alt="Logo Sekolah" class="tpatp-kop-logo">
                        @endif
                    </td>
                </tr>
            </table>
            <div class="tpatp-kop-divider"></div>
        </div>

        {{-- JUDUL --}}
        <h1 class="tpatp-title">{{ $doc['title_tp'] ?: 'TUJUAN PEMBELAJARAN (TP)' }}</h1>

        {{-- IDENTITAS --}}
        <table class="tpatp-identitas">
            <tr>
                <td class="lbl">MATA PELAJARAN</td>
                <td class="sep">:</td>
                <td class="val">{{ $mapel }}</td>
            </tr>
            <tr>
                <td class="lbl">FASE / KELAS</td>
                <td class="sep">:</td>
                <td class="val">{{ $faseKelas }}</td>
            </tr>
            <tr>
                <td class="lbl">TAHUN PELAJARAN</td>
                <td class="sep">:</td>
                <td class="val">{{ $tahun }}</td>
            </tr>
        </table>

        {{-- TABEL TP FORMAL --}}
        <div class="tpatp-table-responsive">
            <table class="tpatp-tbl">
                <thead>
                    <tr>
                        <th style="width: 11%;">ELEMEN</th>
                        <th style="width: 24%;">CAPAIAN PEMBELAJARAN</th>
                        <th style="width: 16%;">LINGKUP MATERI</th>
                        <th style="width: 29%;">TUJUAN PEMBELAJARAN (TP)</th>
                        <th style="width: 7%;">KODE TP</th>
                        <th style="width: 7%;">ALOKASI WAKTU (JP)</th>
                        <th style="width: 6%;">SEMESTER</th>
                    </tr>
                </thead>
                @if(!empty($doc['tp_table']['elements']))
                    <tbody>
                        @foreach($doc['tp_table']['elements'] as $el)
                            @php $elFirst = true; @endphp
                            @foreach($el['materi_groups'] as $mat)
                                @php $matFirst = true; @endphp
                                @foreach($mat['tps'] as $tpItem)
                                    <tr>
                                        @if($elFirst)
                                            <td class="cell-elemen" rowspan="{{ $el['rowspan'] }}">{{ $el['nama'] }}</td>
                                            <td class="cell-cp" rowspan="{{ $el['rowspan'] }}">{{ $el['cp'] }}</td>
                                            @php $elFirst = false; @endphp
                                        @endif

                                        @if($matFirst)
                                            <td class="cell-materi" rowspan="{{ $mat['rowspan'] }}">{{ $mat['nama'] }}</td>
                                        @endif

                                        <td class="cell-tp">{{ $tpItem['tp'] }}</td>
                                        <td class="cell-code">{{ $tpItem['kode'] }}</td>

                                        @if($matFirst)
                                            <td class="cell-jp" rowspan="{{ $mat['rowspan'] }}">{{ $mat['jp'] }}</td>
                                            <td class="cell-sem" rowspan="{{ $mat['rowspan'] }}">{{ $mat['semester'] }}</td>
                                            @php $matFirst = false; @endphp
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                @elseif(!empty($doc['tp_table']['rows']))
                    <tbody>
                        @foreach($doc['tp_table']['rows'] as $r)
                            <tr>
                                <td class="cell-elemen">{{ $r['elemen'] }}</td>
                                <td class="cell-cp">{{ $r['cp'] }}</td>
                                <td class="cell-materi">{{ $r['lingkup_materi'] }}</td>
                                <td class="cell-tp">{{ $r['tp'] }}</td>
                                <td class="cell-code">{{ $r['kode_tp'] }}</td>
                                <td class="cell-jp">{{ $r['jp'] }}</td>
                                <td class="cell-sem">{{ $r['semester'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @else
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-slate-400">Belum ada baris tujuan pembelajaran.</td>
                        </tr>
                    </tbody>
                @endif
            </table>
        </div>

        {{-- BLOK TANDA TANGAN --}}
        @php
            $currentUser = auth()->user();
            $defaultGuruNama = $currentUser?->guru?->nama ?: ($currentUser?->name ?: 'Guru Mata Pelajaran');
            $defaultGuruNik = $currentUser?->guru?->nik ?: ($currentUser?->guru?->nip ?: ($currentUser?->username ?: ''));
            $sigName = !empty($sig['name']) && !str_contains($sig['name'], '......') ? $sig['name'] : $defaultGuruNama;
            $sigNip = !empty($sig['nip']) && !str_contains($sig['nip'], '......') ? $sig['nip'] : $defaultGuruNik;
        @endphp
        <div class="tpatp-ttd-container">
            <div class="tpatp-ttd-box">
                <p class="tpatp-ttd-date">{{ $sig['place_date'] ?: 'Tanjungpinang, 17 Juli 2026' }}</p>
                <p class="tpatp-ttd-role">{{ $sig['role'] ?: 'Guru Mata Pelajaran' }}</p>
                <div class="tpatp-ttd-space"></div>
                <p class="tpatp-ttd-name">{{ $sigName }}</p>
                <p class="tpatp-ttd-nip">{{ !empty($sigNip) ? 'NIK. ' . $sigNip : 'NIK. -' }}</p>
            </div>
        </div>
    </section>
    @endif

    {{-- ==================== 2. BAGIAN ALUR TUJUAN PEMBELAJARAN (ATP) ==================== --}}
    @if($hasAtp)
    <section class="tpatp-section tpatp-page-atp {{ $hasTp ? 'tpatp-page-break' : '' }}">
        {{-- KOP SURAT RESMI SEKOLAH DARI APLIKASI SIMS --}}
        <div class="tpatp-kop">
            <table class="tpatp-kop-table">
                <tr>
                    <td class="tpatp-kop-logo-cell tpatp-kop-logo-left">
                        @if(!empty($kopLogoKiri))
                            <img src="{{ $kopLogoKiri }}" alt="Logo Tut Wuri" class="tpatp-kop-logo">
                        @endif
                    </td>
                    <td class="tpatp-kop-center-cell">
                        <div class="tpatp-kop-text">
                            @if(!empty($kopTeksHtml))
                                {!! $kopTeksHtml !!}
                            @elseif(!empty($kop))
                                @foreach($kop as $idx => $line)
                                    <div class="tpatp-kop-line {{ $idx === 0 ? 'tpatp-kop-line-main' : '' }}">{{ $line }}</div>
                                @endforeach
                            @endif
                        </div>
                    </td>
                    <td class="tpatp-kop-logo-cell tpatp-kop-logo-right">
                        @if(!empty($kopLogoKanan))
                            <img src="{{ $kopLogoKanan }}" alt="Logo Sekolah" class="tpatp-kop-logo">
                        @endif
                    </td>
                </tr>
            </table>
            <div class="tpatp-kop-divider"></div>
        </div>

        {{-- JUDUL --}}
        <h1 class="tpatp-title">{{ $doc['title_atp'] ?: 'ALUR TUJUAN PEMBELAJARAN (ATP)' }}</h1>

        {{-- IDENTITAS --}}
        <table class="tpatp-identitas">
            <tr>
                <td class="lbl">MATA PELAJARAN</td>
                <td class="sep">:</td>
                <td class="val">{{ $mapel }}</td>
            </tr>
            <tr>
                <td class="lbl">FASE / KELAS</td>
                <td class="sep">:</td>
                <td class="val">{{ $faseKelas }}</td>
            </tr>
            <tr>
                <td class="lbl">TAHUN PELAJARAN</td>
                <td class="sep">:</td>
                <td class="val">{{ $tahun }}</td>
            </tr>
        </table>

        {{-- DIAGRAM ALUR TUJUAN PEMBELAJARAN (FLOWCHART RESMI) --}}
        <div class="tpatp-flowchart-wrapper">
            <div class="tpatp-flowchart-card">
                @php
                    $byGrade = $doc['atp_diagram']['by_grade'] ?? [7 => [], 8 => [], 9 => []];
                    $allFlowRows = [];

                    foreach ([7, 8, 9] as $g) {
                        $gNodes = $byGrade[$g] ?? [];
                        if (empty($gNodes)) continue;

                        $cnt = count($gNodes);
                        if ($cnt <= 6) {
                            $allFlowRows[] = ['grade' => $g, 'is_subrow' => false, 'nodes' => $gNodes];
                        } else {
                            $gGanjil = [];
                            $gGenap = [];
                            $curS = 'Ganjil';
                            foreach ($gNodes as $n) {
                                $s = (string) ($n['semester'] ?? '');
                                $j = (string) ($n['judul'] ?? '');
                                if (stripos($s, 'Genap') !== false || stripos($j, 'Semester Genap') !== false) {
                                    $curS = 'Genap';
                                }
                                if ($curS === 'Genap') {
                                    $gGenap[] = $n;
                                } else {
                                    $gGanjil[] = $n;
                                }
                            }

                            $grps = [];
                            if (!empty($gGanjil)) $grps[] = $gGanjil;
                            if (!empty($gGenap)) $grps[] = $gGenap;
                            if (empty($grps)) $grps[] = [$gNodes];

                            foreach ($grps as $grpIdx => $grp) {
                                $allFlowRows[] = ['grade' => $g, 'is_subrow' => ($grpIdx > 0), 'nodes' => $grp];
                            }
                        }
                    }
                @endphp

                @foreach($allFlowRows as $rIdx => $rowItem)
                    @php
                        $g = $rowItem['grade'];
                        $rNodes = $rowItem['nodes'];
                        $rowCls = match($g) {
                            7 => 'tpatp-flow-row--g7',
                            8 => 'tpatp-flow-row--g8',
                            default => 'tpatp-flow-row--g9',
                        };
                    @endphp
                    <div class="tpatp-flow-row {{ $rowCls }}" style="{{ count($rNodes) <= 2 ? 'max-width: 650px; margin-left: auto; margin-right: auto;' : '' }}">
                        <div class="tpatp-flow-track">
                            @foreach($rNodes as $nodeIdx => $node)
                                @php
                                    $type = $node['type'] ?? 'tp';
                                    $isStart = $type === 'start';
                                    $isTrans = $type === 'transition';
                                    $isEnd = $type === 'end';
                                    $materi = trim((string) ($node['materi'] ?? ''));
                                    $tpDesc = trim((string) ($node['tp_desc'] ?? ''));
                                    $judul = trim((string) ($node['judul'] ?? ''));
                                    $code = trim((string) ($node['kode'] ?? ''));
                                    if ($judul === '-' || $judul === '–') $judul = '';
                                    if ($materi === '-' || $materi === '–') $materi = '';
                                    if ($tpDesc === '-' || $tpDesc === '–') $tpDesc = '';
                                    if ($code === '-' || $code === '–') $code = '';

                                    if ($isStart && $judul === '') {
                                        $semName = !empty($node['semester']) ? $node['semester'] : 'Ganjil';
                                        $judul = "Awal fase D Kelas {$g} Semester {$semName}";
                                    } elseif ($isTrans && $judul === '') {
                                        $nextG = $g < 9 ? $g + 1 : 9;
                                        $judul = "Capaian Pembelajaran Akhir Fase D Kelas {$g} menuju pembelajaran fase D kelas {$nextG} Semester Ganjil";
                                    } elseif ($isEnd && $judul === '') {
                                        $judul = "Capaian Pembelajaran Akhir Fase D";
                                    }

                                    $dispMateri = $materi !== '' ? $materi : $judul;
                                    if ($dispMateri === '' || $dispMateri === '-') {
                                        $dispMateri = $tpDesc !== '' ? $tpDesc : ($code !== '' ? "Tujuan Pembelajaran {$code}" : 'Tujuan Pembelajaran');
                                    }
                                    $dispTp = ($tpDesc !== '' && $tpDesc !== '-' && $tpDesc !== $dispMateri) ? $tpDesc : '';

                                    $nodeCls = match(true) {
                                        $isStart => 'tpatp-node--start',
                                        $isTrans => 'tpatp-node--trans-orange',
                                        $isEnd => 'tpatp-node--end-green',
                                        $g === 7 => 'tpatp-node--tp-pink',
                                        $g === 8 => 'tpatp-node--tp-orange',
                                        default => 'tpatp-node--tp-green',
                                    };
                                    $badgeCls = match($g) {
                                        7 => 'node-badge--pink',
                                        8 => 'node-badge--orange',
                                        default => 'node-badge--green',
                                    };
                                @endphp
                                <div class="tpatp-flow-node {{ $nodeCls }}">
                                    @if($isStart || $isTrans || $isEnd)
                                        <div class="node-title font-bold">{{ $judul }}</div>
                                    @else
                                        <div class="node-title font-bold" style="font-size: 8px; line-height: 1.25;">Materi: {{ $dispMateri }}</div>
                                        @if($dispTp !== '')
                                            <div style="font-size: 7.2px; color: #475569; margin: 2px 0; line-height: 1.2;">{{ Str::limit($dispTp, 75, '...') }}</div>
                                        @endif
                                        @if(!empty($code))
                                            <span class="node-badge {{ $badgeCls }}">({{ $code }})</span>
                                        @endif
                                    @endif
                                </div>
                                @if($nodeIdx < count($rNodes) - 1)
                                    <div class="tpatp-flow-arrow">➔</div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @if($rIdx < count($allFlowRows) - 1)
                        @php $nextR = $allFlowRows[$rIdx + 1]; @endphp
                        @if($nextR['grade'] === $g && $nextR['is_subrow'])
                            <div style="text-align: center; color: #64748b; font-size: 13px; font-weight: bold; margin: 3px 0 5px;">↓</div>
                        @else
                            <div style="height: 10px;"></div>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>

        {{-- TABEL RINGKASAN URUTAN ALUR ATP --}}
        @if(!empty($doc['atp_diagram']['summary_rows']))
        <div class="tpatp-atp-summary-wrapper" style="margin-top: 24px;">
            <h3 class="tpatp-subheading">Ringkasan Alur Tujuan Pembelajaran</h3>
            <table class="tpatp-tbl tpatp-tbl--summary">
                <thead>
                    <tr>
                        <th style="width: 4%;">NO</th>
                        <th style="width: 6%;">KELAS</th>
                        <th style="width: 8%;">SEMESTER</th>
                        <th style="width: 22%;">LINGKUP MATERI</th>
                        <th style="width: 9%;">KODE TP</th>
                        <th style="width: 45%;">TUJUAN PEMBELAJARAN (TP)</th>
                        <th style="width: 6%;">JP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($doc['atp_diagram']['summary_rows'] as $sr)
                    <tr>
                        <td class="text-center">{{ $sr['no'] }}</td>
                        <td class="text-center">{{ $sr['kelas'] }}</td>
                        <td class="text-center">{{ $sr['semester'] }}</td>
                        <td class="font-semibold">{{ $sr['lingkup_materi'] }}</td>
                        <td class="text-center font-bold text-indigo-700">{{ $sr['kode_tp'] }}</td>
                        <td>{{ $sr['tp'] }}</td>
                        <td class="text-center">{{ $sr['jp'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- BLOK TANDA TANGAN --}}
        @php
            $currentUser = auth()->user();
            $defaultGuruNama = $currentUser?->guru?->nama ?: ($currentUser?->name ?: 'Guru Mata Pelajaran');
            $defaultGuruNik = $currentUser?->guru?->nik ?: ($currentUser?->guru?->nip ?: ($currentUser?->username ?: ''));
            $sigName = !empty($sig['name']) && !str_contains($sig['name'], '......') ? $sig['name'] : $defaultGuruNama;
            $sigNip = !empty($sig['nip']) && !str_contains($sig['nip'], '......') ? $sig['nip'] : $defaultGuruNik;
        @endphp
        <div class="tpatp-ttd-container">
            <div class="tpatp-ttd-box">
                <p class="tpatp-ttd-date">{{ $sig['place_date'] ?: 'Tanjungpinang, 17 Juli 2026' }}</p>
                <p class="tpatp-ttd-role">{{ $sig['role'] ?: 'Guru Mata Pelajaran' }}</p>
                <div class="tpatp-ttd-space"></div>
                <p class="tpatp-ttd-name">{{ $sigName }}</p>
                <p class="tpatp-ttd-nip">{{ !empty($sigNip) ? 'NIK. ' . $sigNip : 'NIK. -' }}</p>
            </div>
        </div>
    </section>
    @endif
</div>
