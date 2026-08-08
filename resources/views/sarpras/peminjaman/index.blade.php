@extends('sarpras.layouts.app')
@section('title', 'Peminjaman')
@section('sarpras_title', 'Peminjaman')

@section('sarpras_subtitle')
{{ ($hanyaMilikSaya ?? false)
    ? 'Ajukan pinjam barang atau ruangan, pantau status pengajuan Anda.'
    : 'Kelola peminjaman barang inventaris dan jadwal pemakaian ruangan dalam satu tempat.' }}
@endsection

@section('sarpras_actions')
    @can('sarpras.peminjaman.ajukan')
        <a href="{{ route('sarpras.peminjaman.create') }}" class="sarpras-google-btn-primary px-5 py-2.5 text-xs sm:text-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Ajukan Pinjam
        </a>
    @endcan
@endsection

@section('sarpras_body')
@php
    $tab = $tab ?? 'barang';
    $bStatus = [
        'diajukan' => ['Menunggu', 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300'],
        'dipinjam' => ['Dipinjam', 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300'],
        'ditolak' => ['Ditolak', 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
        'dikembalikan' => ['Selesai', 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'],
        'terlambat' => ['Terlambat', 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
    ];
    $statusMeta = [
        'tersedia' => ['Tersedia', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600'],
        'digunakan' => ['Digunakan', 'bg-amber-100 dark:bg-amber-900/40 text-amber-600'],
        'maintenance' => ['Maintenance', 'bg-rose-100 dark:bg-rose-900/40 text-rose-600'],
    ];
@endphp

<div class="flex gap-2 mb-4">
    <a href="{{ route('sarpras.peminjaman.index', ['tab' => 'barang']) }}"
       class="px-4 py-2 rounded-xl text-xs font-bold {{ $tab === 'barang' ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">Barang</a>
    <a href="{{ route('sarpras.peminjaman.index', ['tab' => 'ruangan']) }}"
       class="px-4 py-2 rounded-xl text-xs font-bold {{ $tab === 'ruangan' ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">Ruangan</a>
</div>

@if($tab === 'barang')
<div class="card p-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-slate-400 border-b">
                @unless($hanyaMilikSaya ?? false)<th class="pb-2">Peminjam</th>@endunless
                <th class="pb-2">Detail</th><th class="pb-2">Periode</th><th class="pb-2">Status</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($peminjaman as $p)
                @php [$pl, $pc] = $bStatus[$p->status] ?? [ucfirst($p->status), 'bg-slate-100']; @endphp
                <tr class="border-b border-slate-50 dark:border-slate-700/50">
                    @unless($hanyaMilikSaya ?? false)
                        <td class="py-2.5">{{ $p->peminjam?->name ?? '—' }}</td>
                    @endunless
                    <td class="py-2.5">
                        <span class="font-medium">{{ $p->keperluan }}</span>
                        @if($p->items_count > 0)<span class="text-slate-500"> · {{ $p->items_count }} barang</span>@endif
                        @if($p->ruangan)<span class="text-slate-500"> · {{ $p->ruangan->kode }}</span>@endif
                    </td>
                    <td class="py-2.5 whitespace-nowrap">{{ optional($p->mulai)->format('d/m/Y H:i') }} – {{ optional($p->selesai)->format('d/m/Y H:i') }}</td>
                    <td class="py-2.5"><span class="badge {{ $pc }}">{{ $pl }}</span></td>
                    <td class="py-2.5"><a href="{{ route('sarpras.peminjaman.show', $p) }}" class="text-primary text-xs font-bold">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ ($hanyaMilikSaya ?? false) ? 4 : 5 }}" class="py-8 text-center text-slate-500">Belum ada pengajuan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div x-data="{ open: false, form: { ruangan_id: '' } }" class="space-y-4">
    @if(($canApprove ?? false) && ($pending ?? collect())->isNotEmpty())
    <div class="card p-5 border-l-4 border-amber-400">
        <h3 class="font-bold mb-3">Menunggu Persetujuan Ruangan ({{ $pending->count() }})</h3>
        @foreach($pending as $b)
        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 mb-2">
            <div>
                <p class="font-semibold">{{ $b->ruangan?->kode }} — {{ $b->keperluan }}</p>
                <p class="text-xs text-slate-500">{{ $b->peminjam?->name }} · {{ $b->mulai?->format('d/m/Y H:i') }}–{{ $b->selesai?->format('H:i') }}</p>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('sarpras.peminjaman.setujui', $b) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-sm font-bold">Setujui</button></form>
                <form method="POST" action="{{ route('sarpras.peminjaman.tolak', $b) }}">@csrf<input type="hidden" name="alasan_tolak" value="Ditolak operator"><button class="px-3 py-1.5 rounded-lg border border-rose-300 text-rose-600 text-sm">Tolak</button></form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @can('sarpras.peminjaman.ajukan')
    <button type="button" @click="open=true" class="sarpras-google-btn-primary px-4 py-2 text-sm font-bold"><i data-lucide="plus" class="w-4 h-4 inline"></i> Ajukan Ruangan</button>
    @endcan

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($rooms ?? [] as $room)
        @php [$sl, $sc] = $statusMeta[$room->status] ?? $statusMeta['tersedia']; @endphp
        <div class="card p-4">
            <div class="flex justify-between gap-2">
                <div><p class="font-bold">{{ $room->kode }}</p><p class="text-xs text-slate-500">{{ $room->nama }}</p></div>
                <span class="badge {{ $sc }}">{{ $sl }}</span>
            </div>
            @can('sarpras.peminjaman.ajukan')
            <button type="button" @click="form.ruangan_id='{{ $room->id }}'; open=true" class="mt-3 text-sm font-bold text-primary">Ajukan penggunaan</button>
            @endcan
        </div>
        @endforeach
    </div>

    <div class="card p-5">
        <h3 class="font-bold mb-3">Riwayat Jadwal Ruangan</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-400 border-b"><th class="pb-2">Ruangan</th><th class="pb-2">Kegiatan</th><th class="pb-2">Waktu</th><th class="pb-2">Status</th></tr></thead>
                <tbody>
                @foreach($peminjaman->whereNotNull('ruangan_id') as $b)
                    @php [$bl, $bc] = $bStatus[$b->status] ?? ['—','bg-slate-100']; @endphp
                    <tr class="border-b border-slate-50"><td class="py-2">{{ $b->ruangan?->kode }}</td><td class="py-2">{{ $b->keperluan }}</td><td class="py-2">{{ $b->mulai?->format('d/m/Y H:i') }}</td><td class="py-2"><span class="badge {{ $bc }}">{{ $bl }}</span></td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-[9990] grid place-items-center p-4 bg-slate-900/50" @click.self="open=false">
        <div class="card w-full max-w-md p-5" @click.stop>
            <h3 class="font-bold mb-3">Ajukan Pemakaian Ruangan</h3>
            <form method="POST" action="{{ route('sarpras.peminjaman.ruangan.store') }}" class="space-y-3">
                @csrf
                <select name="ruangan_id" x-model="form.ruangan_id" required class="form-input text-sm w-full">
                    <option value="">— pilih ruangan —</option>
                    @foreach($allRooms ?? [] as $r)
                        <option value="{{ $r->id }}">{{ $r->kode }} — {{ $r->nama }}</option>
                    @endforeach
                </select>
                <input name="keperluan" required class="form-input text-sm w-full" placeholder="Keperluan">
                <input type="date" name="tanggal" value="{{ now()->addDay()->format('Y-m-d') }}" required class="form-input text-sm w-full">
                <div class="grid grid-cols-2 gap-2">
                    <input type="time" name="jam_mulai" value="08:00" required class="form-input text-sm">
                    <input type="time" name="jam_selesai" value="10:00" required class="form-input text-sm">
                </div>
                <div class="flex gap-2"><button type="button" @click="open=false" class="flex-1 py-2 rounded-xl border">Batal</button><button class="flex-1 py-2 rounded-xl bg-primary text-white font-bold">Kirim</button></div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
