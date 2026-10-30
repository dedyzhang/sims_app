{{-- Partial Tampilan Dokumen Program Tahunan (PROTA) Kurikulum Merdeka SMP --}}
<div class="prota-document bg-white text-black p-4 sm:p-6 text-xs font-sans max-w-full overflow-x-auto leading-normal">
    {{-- Kop Surat Resmi Sekolah --}}
    <div class="prota-kop border-b-2 border-black pb-2 mb-4 text-center">
        {!! \App\Support\SchoolLetterhead::kopTeksHtml() !!}
    </div>

    {{-- Judul Formal --}}
    <div class="text-center font-bold mb-4 space-y-0.5 text-slate-900">
        <h2 class="text-sm sm:text-base uppercase tracking-wide m-0">PROGRAM TAHUNAN</h2>
        <h3 class="text-xs sm:text-sm uppercase m-0">{{ $prota['kelas'] ?? 'KELAS VII' }}</h3>
        <h3 class="text-xs sm:text-sm uppercase m-0">MATA PELAJARAN {{ $prota['mapel'] ?? '' }}</h3>
        <p class="text-xs m-0">TAHUN PELAJARAN {{ $prota['tahun'] ?? '' }}</p>
    </div>

    {{-- Tabel Program Tahunan --}}
    <table class="w-full border-collapse border border-black text-[11px] leading-tight mb-6">
        <thead>
            <tr class="bg-slate-100 font-bold text-center">
                <th class="border border-black px-2 py-1.5 w-[14%]">ELEMEN</th>
                <th class="border border-black px-2 py-1.5 w-[26%]">CAPAIAN PEMBELAJARAN (CP)</th>
                <th class="border border-black px-2 py-1.5 w-[36%]">TUJUAN PEMBELAJARAN (TP)</th>
                <th class="border border-black px-1.5 py-1.5 w-[8%]">KODE TP</th>
                <th class="border border-black px-1 py-1.5 w-[8%]">ALOKASI WAKTU</th>
                <th class="border border-black px-1.5 py-1.5 w-[8%]">KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            @php
                $isFirstTableRow = true;
                $primaryElemen = $prota['primary_elemen'] ?? 'Pemahaman Konsep';
                $primaryCp = $prota['primary_cp'] ?? '';
                $totalTableRows = $prota['total_table_rows'] ?? 30;
            @endphp

            @foreach(['semester_1', 'semester_2'] as $sKey)
                @php
                    $sem = $prota['semesters'][$sKey];
                @endphp

                {{-- Separator Semester --}}
                <tr class="bg-slate-200/80 font-bold text-center">
                    @if($isFirstTableRow)
                        <td rowspan="{{ $totalTableRows }}" class="border border-black px-2 py-2 align-top font-bold text-slate-800 bg-slate-50/50">
                            {{ $primaryElemen }}
                        </td>
                        <td rowspan="{{ $totalTableRows }}" class="border border-black px-2.5 py-2 align-top text-justify leading-relaxed text-slate-800 bg-slate-50/50">
                            {{ $primaryCp }}
                        </td>
                        @php $isFirstTableRow = false; @endphp
                    @endif

                    <td colspan="4" class="border border-black px-2 py-1.5 text-center font-bold tracking-wider uppercase bg-slate-200">
                        {{ $sem['label'] }}
                    </td>
                </tr>

                {{-- Bab & Butir TP dalam Semester --}}
                @foreach($sem['babs'] as $b)
                    {{-- Header Bab --}}
                    <tr class="font-bold">
                        <td colspan="2" class="border border-black px-2 py-1.5 text-center font-bold text-purple-950" style="background-color: #f3d0ea;">
                            {{ $b['bab_title'] }}
                        </td>
                        <td class="border border-black px-1 py-1.5 text-center" style="background-color: #f3d0ea;"></td>
                        <td class="border border-black px-1 py-1.5 text-center" style="background-color: #f3d0ea;"></td>
                    </tr>

                    {{-- Butir-butir TP --}}
                    @php
                        $tpCount = count($b['tps']);
                    @endphp
                    @foreach($b['tps'] as $tIdx => $t)
                        <tr>
                            <td class="border border-black px-2 py-1.5 align-top text-justify">
                                {{ $t['tp'] }}
                            </td>
                            <td class="border border-black px-1 py-1.5 text-center font-semibold align-middle whitespace-nowrap">
                                {{ $t['kode'] }}
                            </td>
                            @if($tIdx === 0)
                                <td rowspan="{{ $tpCount }}" class="border border-black px-1 py-1.5 text-center font-bold align-middle">
                                    {{ $b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2)) }}
                                </td>
                            @endif
                            <td class="border border-black px-1 py-1.5 text-center"></td>
                        </tr>
                    @endforeach

                    {{-- Baris PENILAIAN SUMATIF --}}
                    <tr class="font-bold">
                        <td colspan="2" class="border border-black px-2 py-1 text-center font-bold uppercase" style="background-color: #fce7f3;">
                            PENILAIAN SUMATIF
                        </td>
                        <td class="border border-black px-1 py-1 text-center font-bold" style="background-color: #fce7f3;">
                            {{ $b['sumatif_jp'] ?? $sem['sumatif_jp_per_bab'] ?? 2 }}
                        </td>
                        <td class="border border-black px-1 py-1 text-center"></td>
                    </tr>
                @endforeach

                {{-- Baris CADANGAN (jika ada) --}}
                @if(($sem['cadangan_jp'] ?? 0) > 0)
                    <tr class="font-semibold">
                        <td colspan="2" class="border border-black px-2 py-1 text-center font-bold">
                            CADANGAN
                        </td>
                        <td class="border border-black px-1 py-1 text-center font-bold">
                            {{ $sem['cadangan_jp'] }}
                        </td>
                        <td class="border border-black px-1 py-1 text-center"></td>
                    </tr>
                @endif

                {{-- Baris TOTAL ALOKASI WAKTU SEMESTER --}}
                <tr class="font-bold bg-slate-50">
                    <td colspan="2" class="border border-black px-2 py-1.5 text-center font-bold uppercase tracking-wider text-slate-800">
                        TOTAL ALOKASI WAKTU {{ $sem['label'] }}
                    </td>
                    <td class="border border-black px-1 py-1.5 text-center font-extrabold text-slate-900 whitespace-nowrap">
                        {{ $sem['total_jp'] }} JP
                    </td>
                    <td class="border border-black px-1 py-1.5 text-center"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Tanda Tangan Formal --}}
    @php
        $sig = \App\Support\ProsemProtaDocument::resolveSignature($prota['signature'] ?? []);
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
