@extends('sarpras.layouts.app')
@section('title', 'Laporan Sarpras')
@section('sarpras_title', 'Laporan Sarpras')
@section('sarpras_subtitle', 'Rekap kondisi aset, nilai inventaris, dan ekspor untuk pelaporan sekolah.')

@section('sarpras_actions')
    @can('sarpras.laporan.export')
        <a href="{{ route('sarpras.laporan.aset.excel') }}" class="sarpras-google-btn-ghost px-4 py-2 text-xs sm:text-sm">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Ekspor Excel
        </a>
        <a href="{{ route('sarpras.laporan.aset.pdf') }}" target="_blank" class="sarpras-google-btn-primary px-5 py-2.5 text-xs sm:text-sm">
            <i data-lucide="file-text" class="w-4 h-4"></i> Ekspor PDF
        </a>
    @endcan
@endsection

@section('sarpras_body')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="card p-5 lg:col-span-2 !rounded-[24px]">
        <h3 class="font-extrabold text-slate-800 dark:text-slate-100 mb-4">Rekap Aset per Kondisi</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr class="text-left text-gray-500 border-b">
                        <th class="py-2">Kondisi</th>
                        <th class="py-2 text-center">Jumlah</th>
                        <th class="py-2 text-right">Nilai Perolehan</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rekapKondisi as $r)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                        <td class="py-3 capitalize text-sm font-semibold">
                            <span class="inline-block w-2.5 h-2.5 rounded-full mr-2" 
                                  style="background: {{ ['baik' => '#10b981', 'rusak_ringan' => '#f59e0b', 'rusak_berat' => '#ef4444', 'hilang' => '#94a3b8'][$r->kondisi] ?? '#94a3b8' }}"></span>
                            {{ str_replace('_',' ',$r->kondisi) }}
                        </td>
                        <td class="py-3 text-sm text-center font-medium text-slate-600 dark:text-slate-300">{{ $r->jml }} unit</td>
                        <td class="py-3 text-sm font-bold text-slate-700 dark:text-slate-200 text-right">{{ \App\Sarpras\Support\Rupiah::format($r->nilai) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card p-5 flex flex-col justify-center min-h-[160px]">
        <div class="flex items-center gap-3">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500 flex-shrink-0"><i data-lucide="banknote" class="w-6 h-6"></i></span>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Nilai Total Aset</p>
                <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $totalNilaiRp }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
