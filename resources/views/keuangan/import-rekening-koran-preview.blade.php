@extends('layouts.app')
@section('title', 'Pratinjau Import Rekening Koran')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('keuangan.verifikasi') }}" class="hover:underline">Verifikasi</a> / Pratinjau Import</nav>
        <h1 class="page-title">Pratinjau Import Rekening Koran</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ count($preview) }} transaksi terbaca dari file — belum ada yang tersimpan. Baris "Saran otomatis" sudah tercentang (biarkan saja utk terapkan otomatis), baris lain bisa dicentang &amp; pilih bulan manual.</p>
    </div>

    <form method="POST" action="{{ route('keuangan.import-rekening-koran.apply') }}">
        @csrf
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="p-3 text-left w-8"></th>
                            <th class="p-3 text-left">Transaksi Bank</th>
                            <th class="p-3 text-left">Siswa</th>
                            <th class="p-3 text-left">Diterapkan ke Bulan</th>
                            <th class="p-3 text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($preview as $i => $row)
                        <tr class="{{ in_array($row['status'], ['va_tidak_ditemukan', 'va_ganda', 'tidak_ada_tagihan']) ? 'opacity-60' : '' }}">
                            <td class="p-3 align-top">
                                @if(in_array($row['status'], ['saran_otomatis', 'perlu_pilih_manual']))
                                    <input type="checkbox" name="baris[{{ $i }}][terapkan]" value="1" @checked($row['status']==='saran_otomatis') class="rounded text-primary focus:ring-primary">
                                @endif
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-mono text-xs text-slate-500">{{ $row['no_pelanggan'] }}</p>
                                <p class="font-semibold text-slate-700 dark:text-slate-200">Rp {{ number_format($row['nominal'], 0, ',', '.') }}</p>
                                <p class="text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($row['tanggal'])->format('d/m/Y') }}</p>
                            </td>
                            <td class="p-3 align-top">
                                @if($row['siswa'])
                                    <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $row['siswa']->nama }}</p>
                                @else
                                    <p class="text-slate-400 italic">-</p>
                                @endif
                            </td>
                            <td class="p-3 align-top">
                                @if($row['opsi']->isNotEmpty())
                                    <select name="baris[{{ $i }}][pembayaran_uuid]" class="form-select text-xs !py-1.5">
                                        <option value="">— pilih bulan —</option>
                                        @foreach($row['opsi'] as $o)
                                            <option value="{{ $o->uuid }}" @selected($row['saran_pembayaran_uuid'] === $o->uuid)>
                                                {{ $o->label_bulan }} · Rp {{ number_format($o->nominal, 0, ',', '.') }} · {{ ucfirst($o->status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                                <input type="hidden" name="baris[{{ $i }}][nominal]" value="{{ $row['nominal'] }}">
                                <input type="hidden" name="baris[{{ $i }}][tanggal_bayar]" value="{{ $row['tanggal'] }}">
                            </td>
                            <td class="p-3 align-top text-xs">
                                @php
                                    $badgeClass = match($row['status']) {
                                        'saran_otomatis' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
                                        'perlu_pilih_manual' => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                                        default => 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400',
                                    };
                                    $badgeLabel = match($row['status']) {
                                        'saran_otomatis' => 'Saran otomatis',
                                        'perlu_pilih_manual' => 'Perlu pilih manual',
                                        'va_tidak_ditemukan' => 'VA tak ditemukan',
                                        'va_ganda' => 'VA ganda',
                                        'tidak_ada_tagihan' => 'Tak ada tagihan',
                                        default => $row['status'],
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} mb-1 inline-block">{{ $badgeLabel }}</span>
                                <p class="text-slate-500 dark:text-slate-400">{{ $row['pesan'] }}</p>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('keuangan.verifikasi') }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Batal</a>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i> Terapkan yang Dicentang
            </button>
        </div>
    </form>
</div>
@endsection
