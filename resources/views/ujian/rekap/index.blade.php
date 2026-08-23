@extends('layouts.app')
@section('title', 'Rekap Berita Acara & Agenda Ujian')

@section('content')
<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Rekap Berita Acara &amp; Agenda Ujian</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pemantauan silang jadwal vs realisasi Berita Acara (BA) untuk seluruh ruangan per tanggal.</p>
        </div>
        <div class="flex flex-col sm:flex-row flex-wrap items-start sm:items-center gap-3 w-full lg:w-auto">
            <form x-data @change="$el.submit()" action="{{ route('ujian.rekap.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                @if(isset($paketList) && $paketList->isNotEmpty())
                <select name="paket_id" class="form-input text-sm py-2">
                    <option value="">Semua Paket (Jika Ada)</option>
                    @foreach($paketList as $paket)
                        <option value="{{ $paket->uuid }}" {{ $paketId === $paket->uuid ? 'selected' : '' }}>
                            {{ $paket->nama }}
                        </option>
                    @endforeach
                </select>
                @endif
                <input type="date" name="tanggal" value="{{ $tanggalString }}" class="form-input text-sm py-2">
            </form>
            <div class="flex items-center gap-2 w-full sm:w-auto grid grid-cols-3 sm:flex">
                <a href="{{ route('ujian.rekap.cetak', ['tanggal' => $tanggalString, 'paket_id' => $paketId]) }}" target="_blank" class="btn-primary px-3 py-2 rounded-xl text-sm font-bold flex items-center justify-center gap-1.5 whitespace-nowrap" title="Cetak Rekap">
                    <i data-lucide="table" class="w-4 h-4"></i> <span class="hidden md:inline">Cetak Rekap</span><span class="md:hidden">Rekap</span>
                </a>
                <a href="{{ route('ujian.rekap.cetakBulkBa', ['tanggal' => $tanggalString, 'paket_id' => $paketId]) }}" target="_blank" class="btn-secondary px-3 py-2 rounded-xl text-sm font-bold flex items-center justify-center gap-1.5 whitespace-nowrap" title="Cetak Semua Berita Acara">
                    <i data-lucide="files" class="w-4 h-4"></i> <span class="hidden md:inline">Semua BA</span><span class="md:hidden">BA</span>
                </a>
                <a href="{{ route('ujian.rekap.cetakBulkDh', ['tanggal' => $tanggalString, 'paket_id' => $paketId]) }}" target="_blank" class="btn-secondary px-3 py-2 rounded-xl text-sm font-bold flex items-center justify-center gap-1.5 whitespace-nowrap" title="Cetak Semua Daftar Hadir">
                    <i data-lucide="users" class="w-4 h-4"></i> <span class="hidden md:inline">Semua DH</span><span class="md:hidden">DH</span>
                </a>
            </div>
        </div>
    </div>

    @if(empty($rekap))
        <div class="card p-10 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 text-slate-400 rounded-full flex items-center justify-center mb-4">
                <i data-lucide="calendar-x" class="w-8 h-8"></i>
            </div>
            <h3 class="font-bold text-slate-700 dark:text-slate-200 text-lg">Tidak Ada Agenda</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm">Tidak ditemukan jadwal/agenda ujian ataupun rekaman berita acara pada tanggal ini.</p>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border-collapse border border-slate-200 dark:border-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-semibold">
                        <tr>
                            <th class="px-5 py-3 w-48 border-r border-slate-200 dark:border-slate-700">Ruangan</th>
                            <th class="px-5 py-3 w-64 border-r border-slate-200 dark:border-slate-700">Agenda / Mapel</th>
                            <th class="px-5 py-3 w-32 border-r border-slate-200 dark:border-slate-700">Waktu</th>
                            <th class="px-5 py-3 w-48 border-r border-slate-200 dark:border-slate-700">Pengawas</th>
                            <th class="px-5 py-3 w-32 text-center border-r border-slate-200 dark:border-slate-700">BA Diisi?</th>
                            <th class="px-5 py-3 w-32 text-center">Hadir / Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 border-b border-slate-200 dark:border-slate-700">
                        @foreach($rekap as $baris)
                            @php
                                $rowspan = count($baris['agendas']) > 0 ? count($baris['agendas']) : 1;
                            @endphp
                            
                            @if(count($baris['agendas']) === 0)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition group">
                                    <td class="px-5 py-4 align-top border-r border-slate-200 dark:border-slate-700">
                                        <div class="font-bold text-slate-700 dark:text-slate-200">{{ $baris['ruangan']->nama }}</div>
                                        <div class="text-xs text-slate-400 mt-0.5">{{ $baris['ruangan']->paket->nama }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-400 italic text-center" colspan="5">Tidak ada sesi di ruangan ini pada tanggal terpilih.</td>
                                </tr>
                            @else
                                @foreach($baris['agendas'] as $idx => $agenda)
                                    @php 
                                        $ba = $agenda['berita_acara']; 
                                        $sesi = $agenda['sesi'];
                                    @endphp
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition group">
                                        @if($idx === 0)
                                            <td class="px-5 py-4 align-top border-r border-b-0 border-slate-200 dark:border-slate-700 bg-slate-50/30 dark:bg-slate-800/30" rowspan="{{ $rowspan }}">
                                                <div class="font-bold text-slate-700 dark:text-slate-200">{{ $baris['ruangan']->nama }}</div>
                                                <div class="text-xs text-slate-400 mt-0.5">{{ $baris['ruangan']->paket->nama }}</div>
                                            </td>
                                        @endif
                                        
                                        <td class="px-5 py-4 align-top border-r border-slate-200 dark:border-slate-700">
                                            @if($agenda['tipe'] === 'adhoc')
                                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 mb-1">AD-HOC</span><br>
                                                <span class="font-medium text-slate-700 dark:text-slate-200">
                                                    {{ $ba ? $ba->ujianList->pluck('pelajaran.nama')->filter()->implode(', ') : 'Tanpa Jadwal' }}
                                                </span>
                                            @else
                                                <div class="font-medium text-slate-700 dark:text-slate-200">
                                                    {{ $sesi->mapelNama() ?: 'Agenda tanpa mapel' }}
                                                </div>
                                                <div class="text-xs text-slate-400 mt-0.5">Sesi {{ $sesi->label }}</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 align-top border-r border-slate-200 dark:border-slate-700">
                                            @if($ba && $ba->jam_mulai_aktual)
                                                {{ substr($ba->jam_mulai_aktual, 0, 5) }} - {{ substr($ba->jam_selesai_aktual, 0, 5) }}
                                            @elseif($sesi)
                                                {{ substr($sesi->jam_mulai, 0, 5) }} - {{ substr($sesi->jam_selesai, 0, 5) }}
                                                <span class="text-[10px] text-slate-400 block">(Terjadwal)</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 align-top border-r border-slate-200 dark:border-slate-700">
                                            @if($ba && $ba->pengawas)
                                                <span class="text-slate-700 dark:text-slate-200">{{ $ba->pengawas->nama }}</span>
                                            @else
                                                <span class="text-slate-400 italic">Belum ada data</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 align-top text-center border-r border-slate-200 dark:border-slate-700">
                                            @if($ba)
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600">
                                                    <i data-lucide="check" class="w-4 h-4"></i>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-100 text-rose-600">
                                                    <i data-lucide="x" class="w-4 h-4"></i>
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 align-top text-center">
                                            @if($ba)
                                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ $ba->jumlah_hadir }}</span> / 
                                                <span class="text-slate-500">{{ $ba->jumlah_hadir + $ba->jumlah_tidak_hadir }}</span>
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
            </div>
        </div>
    @endif
</div>
@endsection
