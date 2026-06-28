@extends('sarpras.layouts.app')
@section('title', 'Laporan Sarpras')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Laporan Data Sarpras</h2>
    <div class="flex gap-2">
        @can('sarpras.laporan.export')
            <a href="{{ route('sarpras.laporan.aset.excel') }}" class="border px-4 py-2 rounded text-sm">⬇ Aset (Excel)</a>
            <a href="{{ route('sarpras.laporan.aset.pdf') }}" target="_blank" class="border px-4 py-2 rounded text-sm">⬇ Aset (PDF)</a>
        @endcan
        <a href="{{ route('sarpras.laporan.aktivitas') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">Log Aktivitas</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Rekap Aset per Kondisi</h3>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 border-b"><th class="py-2">Kondisi</th><th>Jumlah</th><th>Nilai</th></tr></thead>
            <tbody>
            @foreach ($rekapKondisi as $r)
                <tr class="border-b">
                    <td class="py-2 capitalize">{{ str_replace('_',' ',$r->kondisi) }}</td>
                    <td>{{ $r->jml }}</td>
                    <td>{{ \App\Sarpras\Support\Rupiah::format($r->nilai) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Nilai Total Aset</h3>
        <p class="text-3xl font-bold text-emerald-700">{{ $totalNilaiRp }}</p>
    </div>
</div>
@endsection
