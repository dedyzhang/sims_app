@extends('sarpras.layouts.app')
@section('title', 'Perbaikan & Teknisi')
@section('sarpras_title', 'Perbaikan, Teknisi & Pemeliharaan')
@section('sarpras_subtitle', 'Pencatatan perbaikan barang rusak, penugasan teknisi internal/eksternal, serta jadwal pemeliharaan rutin berulang.')

@section('sarpras_actions')
    @can('sarpras.teknisi.kelola')
        <a href="{{ route('sarpras.teknisi.index') }}" class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="users" class="w-4 h-4"></i> Kelola Teknisi
        </a>
    @endcan
    @can('sarpras.jadwal.kelola')
        <a href="{{ route('sarpras.jadwal.create') }}" class="inline-flex items-center gap-2 bg-slate-900 dark:bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold">
            <i data-lucide="calendar-plus" class="w-4 h-4"></i> Jadwalkan Pemeliharaan
        </a>
    @endcan
@endsection

@section('sarpras_body')
@php
    $prbStatus = [
        'antri'      => ['Antri',          'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'],
        'dikerjakan' => ['Sedang Diproses','bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300'],
        'selesai'    => ['Selesai',        'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300'],
        'batal'      => ['Batal',          'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
    ];
@endphp

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    {{-- Log perbaikan / service --}}
    <div class="card p-5 xl:col-span-2">
        <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-4">
            <span class="grid place-items-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-500"><i data-lucide="wrench" class="w-4 h-4"></i></span>
            Log Perbaikan / Service
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <th class="pb-2 font-semibold">Deskripsi Perbaikan</th><th class="pb-2 font-semibold">Teknisi</th><th class="pb-2 font-semibold">Biaya</th><th class="pb-2 font-semibold">Tanggal</th><th class="pb-2 font-semibold">Status</th><th class="pb-2 font-semibold">Aksi</th>
                </tr></thead>
                <tbody>
                @foreach($perbaikan as $p)
                    @php [$sl, $sc] = $prbStatus[$p->status] ?? [ucfirst($p->status), 'bg-slate-100 text-slate-500']; @endphp
                    <tr class="border-b border-slate-50 dark:border-slate-700/50 align-top">
                        <td class="py-3">
                            <p class="font-semibold text-slate-700 dark:text-slate-200">Aset: {{ $p->aset?->nama ?? '—' }} @if($p->aset)({{ $p->aset->kode }})@endif</p>
                            @if($p->catatan ?? $p->deskripsi)<p class="text-[11px] text-slate-400 italic mt-0.5">Notes: {{ $p->catatan ?? $p->deskripsi }}</p>@endif
                        </td>
                        <td class="py-3">
                            <p class="text-slate-700 dark:text-slate-200">{{ $p->teknisi?->nama ?? '—' }}</p>
                            @if($p->teknisi)<p class="text-[11px] text-slate-400 capitalize">Tipe: {{ $p->teknisi->tipe }}</p>@endif
                        </td>
                        <td class="py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $p->biaya_rp }}</td>
                        <td class="py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ optional($p->tgl_mulai)->format('d/m/Y') ?? '—' }}</td>
                        <td class="py-3"><span class="badge {{ $sc }}">{{ $sl }}</span></td>
                        <td class="py-3">
                            @if($p->status !== 'selesai')
                                @can('sarpras.perbaikan.kelola')
                                <form method="POST" action="{{ route('sarpras.perbaikan.selesai', $p) }}"
                                      onsubmit="return confirmAction(this, 'Tandai perbaikan {{ addslashes($p->aset?->nama ?? $p->kode) }} sebagai SELESAI?', 'blue')">
                                    @csrf
                                    <button class="px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-600">Selesai</button>
                                </form>
                                @endcan
                            @else
                                <a href="{{ route('sarpras.perbaikan.show', $p) }}" class="text-blue-600 hover:underline text-xs">Detail</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        
    </div>

    {{-- Jadwal pemeliharaan rutin --}}
    <div class="card p-5">
        <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-4">
            <span class="grid place-items-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500"><i data-lucide="calendar-clock" class="w-4 h-4"></i></span>
            Jadwal Pemeliharaan Rutin
        </h3>
        @foreach($jadwal as $j)
        <div class="flex items-start justify-between gap-2 py-2.5 border-b border-slate-50 dark:border-slate-700/50 last:border-0">
            <div>
                <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $j->nama }}</p>
                <p class="text-[11px] text-slate-400">{{ $j->aset?->nama ?? 'Umum' }} · tiap {{ $j->interval_hari }} hari</p>
            </div>
            <div class="text-right whitespace-nowrap">
                <p class="text-[11px] text-slate-400">Berikutnya</p>
                <p class="text-xs font-semibold {{ optional($j->tgl_berikutnya)->isPast() ? 'text-rose-500' : 'text-slate-700 dark:text-slate-200' }}">{{ optional($j->tgl_berikutnya)->format('d/m/Y') ?? '—' }}</p>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
