@extends('layouts.app')
@section('title', $ruangan->nama)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('ujian.paket.index') }}" class="hover:underline">Paket Ujian</a> /
                <a href="{{ route('ujian.paket.show', $paket) }}" class="hover:underline">{{ $paket->nama }}</a> / {{ $ruangan->nama }}
            </nav>
            <h1 class="page-title">{{ $ruangan->nama }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $ruangan->peserta->count() }} peserta{{ $ruangan->kapasitas ? ' / ' . $ruangan->kapasitas . ' kapasitas' : '' }}
                @if($ruangan->keterangan) · {{ $ruangan->keterangan }} @endif
            </p>
        </div>
        <a href="{{ route('ujian.ruangan.monitor', $ruangan) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="radar" class="w-4 h-4 inline"></i> Buka Monitor Pengawas
        </a>
    </div>

    {{-- Peserta --}}
    <div class="card p-6 space-y-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100">Peserta ({{ $ruangan->peserta->count() }})</h2>
        <div class="table-responsive">
            <table class="data-table w-full text-sm">
                <thead><tr><th>Nama</th><th>NIS</th><th>Kelas</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse($ruangan->peserta->sortBy(fn($p) => $p->siswa?->nama) as $p)
                    <tr>
                        <td class="font-medium">{{ $p->siswa?->nama ?? '(siswa terhapus)' }}</td>
                        <td class="text-slate-500">{{ $p->siswa?->nis }}</td>
                        <td class="text-slate-500">{{ $p->siswa?->kelas?->tingkat }}{{ $p->siswa?->kelas?->kelas }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('ujian.paket.ruangan.peserta.destroy', [$paket, $ruangan, $p]) }}" onsubmit="return confirmAction(this, 'Lepas {{ addslashes($p->siswa?->nama ?? '') }} dari ruangan ini?', 'orange')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-rose-500 hover:underline">Lepas</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-6 text-slate-400">Belum ada peserta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($siswaTersedia->isNotEmpty())
        <form method="POST" action="{{ route('ujian.paket.ruangan.peserta', [$paket, $ruangan]) }}" class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700">
            @csrf
            <label class="block text-xs font-medium text-slate-500">Tambah peserta (Ctrl/Cmd+klik utk pilih banyak)</label>
            <select name="id_siswa[]" multiple class="form-select h-40">
                @foreach($siswaTersedia->groupBy(fn($s) => $s->kelas ? $s->kelas->tingkat . $s->kelas->kelas : '—') as $kelasLabel => $grup)
                <optgroup label="{{ $kelasLabel }}">
                    @foreach($grup as $s)
                    <option value="{{ $s->uuid }}">{{ $s->nama }} ({{ $s->nis }})</option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">Tambahkan ke Ruangan</button>
        </form>
        @endif
    </div>

    {{-- QR Ruangan --}}
    <div class="card p-6 space-y-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100">QR Ruangan</h2>
        <p class="text-xs text-slate-400">
            Cetak &amp; tempel QR ini secara fisik di ruangan. Siswa scan untuk mencatat kehadiran sendiri.
            Guru scan untuk masuk halaman monitor — guru mana pun boleh masuk, asal ruangan ini punya ujian dijadwalkan pada hari itu.
        </p>
        <div class="flex flex-col sm:flex-row items-center gap-5">
            <img src="{{ $qrUri }}" alt="QR Ruangan {{ $ruangan->nama }}" class="w-44 h-44 flex-shrink-0 border border-slate-100 dark:border-slate-700 rounded-xl p-2">
            <div class="text-sm space-y-2 min-w-0">
                <p class="text-slate-500 dark:text-slate-400 break-all font-mono text-xs">{{ $urlScan }}</p>
                <a href="{{ route('ujian.paket.ruangan.cetak', [$paket, $ruangan]) }}" target="_blank" class="inline-block px-4 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i data-lucide="printer" class="w-3.5 h-3.5 inline"></i> Cetak Poster QR
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
