{{-- Partial Tampilan Dokumen Program Semester (PROSEM) Kurikulum Merdeka SMP --}}
@php
    $months = $prosem['months'] ?? [];
    $calendarEvents = $prosem['calendar_events'] ?? [];
    $blockedColMap = $prosem['blocked_col_map'] ?? [];
    $babs = $prosem['babs'] ?? [];

    // Hitung total baris data tabel untuk rowspan event non-KBM
    $totalDataRows = 0;
    foreach ($babs as $b) {
        $totalDataRows += 1; // Header bab
        $totalDataRows += count($b['tps']); // TP rows
        $totalDataRows += 1; // Penilaian sumatif
    }
    if (($prosem['cadangan_jp'] ?? 0) > 0) {
        $totalDataRows += 1;
    }
    $totalDataRows += 1; // Total JP

    // Peta awal event untuk col_index -> event
    $eventStartMap = [];
    foreach ($calendarEvents as $ev) {
        $eventStartMap[$ev['start_col']] = $ev;
    }
@endphp

<div class="prosem-document bg-white text-black p-4 sm:p-6 text-xs font-sans max-w-full overflow-x-auto leading-normal">
    {{-- Kop Surat Resmi Sekolah --}}
    <div class="prosem-kop border-b-2 border-black pb-2 mb-4 text-center">
        {!! \App\Support\SchoolLetterhead::kopTeksHtml() !!}
    </div>

    {{-- Judul Formal --}}
    <div class="text-center font-bold mb-4 space-y-0.5 text-slate-900">
        <h2 class="text-sm sm:text-base uppercase tracking-wide m-0">{{ $prosem['title'] ?? 'PROGRAM SEMESTER' }}</h2>
        <h3 class="text-xs sm:text-sm uppercase m-0">MATA PELAJARAN {{ $prosem['mapel'] ?? '' }}</h3>
        <p class="text-xs m-0">TAHUN PELAJARAN {{ $prosem['tahun'] ?? '' }}</p>
    </div>

    {{-- Tabel Program Semester 26 Kolom --}}
    <table class="w-full border-collapse border border-black text-[10px] leading-tight mb-6">
        <thead>
            {{-- Header Baris 1 --}}
            <tr class="bg-slate-100 font-bold text-center">
                <th rowspan="3" class="border border-black px-2 py-1.5 w-[28%] min-w-[220px] text-center align-middle">
                    Materi pokok/ Tujuan Pembelajaran
                </th>
                <th rowspan="3" class="border border-black px-1 py-1.5 w-[6%] min-w-[45px] text-center align-middle">
                    Alokasi Waktu
                </th>
                <th colspan="26" class="border border-black px-1 py-1 text-center font-extrabold uppercase tracking-wider bg-slate-200">
                    BULAN
                </th>
            </tr>
            {{-- Header Baris 2 (Nama Bulan) --}}
            <tr class="bg-slate-100 font-bold text-center">
                @foreach($months as $m)
                    <th colspan="{{ $m['weeks'] }}" class="border border-black px-1 py-1 text-center bg-slate-100">
                        {{ $m['name'] }}
                    </th>
                @endforeach
            </tr>
            {{-- Header Baris 3 (Minggu Ke-) --}}
            <tr class="bg-slate-50 font-semibold text-center text-[9px]">
                @foreach($months as $m)
                    @for($w = 1; $w <= $m['weeks']; $w++)
                        <th class="border border-black px-0.5 py-1 text-center min-w-[18px]">
                            {{ $w }}
                        </th>
                    @endfor
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $isFirstDataRow = true; @endphp

            @foreach($babs as $b)
                {{-- Baris Header Bab --}}
                <tr class="font-bold">
                    <td class="border border-black px-2 py-1 text-left font-bold text-purple-950" style="background-color: #f3d0ea;">
                        {{ $b['bab_title'] }}
                    </td>
                    <td class="border border-black px-1 py-1 text-center" style="background-color: #f3d0ea;"></td>

                    {{-- Kolom Minggu untuk Header Bab --}}
                    @php $c = 0; @endphp
                    @while($c < 26)
                        @if(isset($blockedColMap[$c]))
                            @php
                                $ev = null;
                                foreach ($calendarEvents as $cand) {
                                    if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                        $ev = $cand;
                                        break;
                                    }
                                }
                            @endphp
                            @if($isFirstDataRow)
                                <td rowspan="{{ $totalDataRows }}" colspan="{{ $ev['col_span'] }}"
                                    class="border border-black text-center align-middle font-bold text-[9px] uppercase tracking-wider select-none px-0.5"
                                    style="background-color: {{ $ev['color'] }}; color: {{ $ev['text_color'] }}; writing-mode: vertical-lr; transform: rotate(180deg); text-orientation: mixed; word-break: break-word; line-height: 1.1;">
                                    {{ $ev['label'] }}
                                </td>
                            @endif
                            @php $c += $ev['col_span']; @endphp
                        @else
                            <td class="border border-black px-0.5 py-1 text-center" style="background-color: #f3d0ea;"></td>
                            @php $c++; @endphp
                        @endif
                    @endwhile
                </tr>
                @php $isFirstDataRow = false; @endphp

                {{-- Baris Butir-Butir TP --}}
                @php $tpCount = count($b['tps']); @endphp
                @foreach($b['tps'] as $tIdx => $t)
                    <tr>
                        <td class="border border-black px-2 py-1.5 align-top text-justify">
                            {{ $t['tp'] }}
                        </td>
                        @if($tIdx === 0)
                            <td rowspan="{{ $tpCount }}" class="border border-black px-1 py-1 text-center font-bold align-middle">
                                {{ $b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2)) }}
                            </td>
                        @endif

                        {{-- Kolom Minggu untuk Butir TP --}}
                        @php $c = 0; @endphp
                        @while($c < 26)
                            @if(isset($blockedColMap[$c]))
                                {{-- Dilewati karena sudah di-rowspan oleh baris pertama --}}
                                @php
                                    $evSpan = 1;
                                    foreach ($calendarEvents as $cand) {
                                        if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                            $evSpan = $cand['col_span'];
                                            break;
                                        }
                                    }
                                    $c += $evSpan;
                                @endphp
                            @else
                                <td class="border border-black px-0.5 py-1 text-center font-semibold {{ !empty($t['weeks'][$c]) ? 'bg-amber-50/40 text-black' : '' }}">
                                    {{ $t['weeks'][$c] ?? '' }}
                                </td>
                                @php $c++; @endphp
                            @endif
                        @endwhile
                    </tr>
                @endforeach

                {{-- Baris PENILAIAN SUMATIF --}}
                <tr class="font-bold">
                    <td class="border border-black px-2 py-1 text-left font-bold uppercase" style="background-color: #fce7f3;">
                        PENILAIAN SUMATIF
                    </td>
                    <td class="border border-black px-1 py-1 text-center font-bold" style="background-color: #fce7f3;">
                        {{ $prosem['sumatif_jp'] ?? 2 }}
                    </td>

                    {{-- Kolom Minggu untuk PENILAIAN SUMATIF --}}
                    @php $c = 0; @endphp
                    @while($c < 26)
                        @if(isset($blockedColMap[$c]))
                            @php
                                $evSpan = 1;
                                foreach ($calendarEvents as $cand) {
                                    if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                        $evSpan = $cand['col_span'];
                                        break;
                                    }
                                }
                                $c += $evSpan;
                            @endphp
                        @else
                            <td class="border border-black px-0.5 py-1 text-center font-bold" style="background-color: {{ !empty($b['sumatif_weeks'][$c]) ? '#fce7f3' : 'transparent' }};">
                                {{ $b['sumatif_weeks'][$c] ?? '' }}
                            </td>
                            @php $c++; @endphp
                        @endif
                    @endwhile
                </tr>
            @endforeach

            {{-- Baris CADANGAN (jika ada) --}}
            @if(($prosem['cadangan_jp'] ?? 0) > 0)
                <tr class="font-semibold">
                    <td class="border border-black px-2 py-1 text-left font-bold">CADANGAN</td>
                    <td class="border border-black px-1 py-1 text-center font-bold">{{ $prosem['cadangan_jp'] }}</td>
                    @php $c = 0; @endphp
                    @while($c < 26)
                        @if(isset($blockedColMap[$c]))
                            @php
                                $evSpan = 1;
                                foreach ($calendarEvents as $cand) {
                                    if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                        $evSpan = $cand['col_span'];
                                        break;
                                    }
                                }
                                $c += $evSpan;
                            @endphp
                        @else
                            <td class="border border-black px-0.5 py-1 text-center"></td>
                            @php $c++; @endphp
                        @endif
                    @endwhile
                </tr>
            @endif

            {{-- Baris TOTAL JP --}}
            <tr class="font-bold bg-slate-50">
                <td class="border border-black px-2 py-1.5 text-left font-bold uppercase tracking-wider text-slate-800">
                    TOTAL JP
                </td>
                <td class="border border-black px-1 py-1.5 text-center font-extrabold text-slate-900 whitespace-nowrap">
                    {{ $prosem['total_jp'] }} JP
                </td>

                @php $c = 0; @endphp
                @while($c < 26)
                    @if(isset($blockedColMap[$c]))
                        @php
                            $evSpan = 1;
                            foreach ($calendarEvents as $cand) {
                                if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                    $evSpan = $cand['col_span'];
                                    break;
                                }
                            }
                            $c += $evSpan;
                        @endphp
                    @else
                        <td class="border border-black px-0.5 py-1 text-center bg-slate-50"></td>
                        @php $c++; @endphp
                    @endif
                @endwhile
            </tr>
        </tbody>
    </table>

    {{-- Tanda Tangan Formal --}}
    @php
        $sig = \App\Support\ProsemProtaDocument::resolveSignature($prosem['signature'] ?? [], $prosem['semester_type'] ?? null);
    @endphp
    <div class="grid grid-cols-2 gap-4 mt-8 pt-2 text-xs">
        <div class="text-left">
            <p class="m-0">Mengetahui,</p>
            <p class="m-0 font-medium">{{ $sig['jabatan_kepala'] }}</p>
            <div class="h-16"></div>
            <p class="m-0 font-bold underline">{{ $sig['kepala_sekolah'] }}</p>
            <p class="m-0 text-slate-600">NIK. {{ $sig['nip_kepala'] }}</p>
        </div>
        <div class="text-right">
            <p class="m-0">{{ $sig['tempat_tanggal'] }}</p>
            <p class="m-0 font-medium">{{ $sig['jabatan_guru'] }}</p>
            <div class="h-16"></div>
            <p class="m-0 font-bold underline">{{ $sig['guru'] }}</p>
            <p class="m-0 text-slate-600">NIK. {{ $sig['nip_guru'] }}</p>
        </div>
    </div>
</div>
