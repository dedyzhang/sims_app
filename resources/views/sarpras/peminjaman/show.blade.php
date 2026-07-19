@extends('sarpras.layouts.app')
@section('title', 'Peminjaman ' . $peminjaman->kode)
@section('sarpras_title', $peminjaman->kode)
@section('sarpras_subtitle', 'Detail pengajuan pinjam barang' . ($peminjaman->peminjam?->name ? ' · ' . $peminjaman->peminjam->name : ''))

@section('sarpras_actions')
    <a href="{{ route('sarpras.peminjaman.index') }}" class="sarpras-google-btn inline-flex px-4 py-2 rounded-xl text-xs sm:text-sm font-bold">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
@endsection

@section('sarpras_body')
@php
    $statusMeta = [
        'diajukan'  => ['Menunggu', 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'],
        'disetujui' => ['Disetujui', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'],
        'ditolak'   => ['Ditolak', 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300'],
        'dipinjam'  => ['Dipinjam', 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300'],
        'selesai'   => ['Selesai', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
        'terlambat' => ['Terlambat', 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300'],
        'dikembalikan' => ['Dikembalikan', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
    ];
    [$statusLabel, $statusClass] = $statusMeta[$peminjaman->status] ?? [ucfirst($peminjaman->status), 'bg-slate-100 text-slate-700'];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 card !rounded-[24px] p-5 sm:p-6 space-y-5">
        <div class="flex justify-between items-start gap-3">
            <div>
                <p class="text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Status pengajuan</p>
                <h2 class="text-xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $peminjaman->kode }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $peminjaman->peminjam?->name ?? '—' }}</p>
            </div>
            <span class="badge {{ $statusClass }} px-3 py-1.5 text-xs font-extrabold">{{ $statusLabel }}</span>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-[11px] font-extrabold uppercase text-slate-400">Keperluan</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $peminjaman->keperluan }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold uppercase text-slate-400">Ruangan</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $peminjaman->ruangan ? $peminjaman->ruangan->kode . ' — ' . $peminjaman->ruangan->nama : '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold uppercase text-slate-400">Mulai</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ optional($peminjaman->mulai)->format('d/m/Y H:i') ?? $peminjaman->tgl_pinjam->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold uppercase text-slate-400">Selesai</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ optional($peminjaman->selesai)->format('d/m/Y H:i') ?? $peminjaman->tgl_kembali_rencana->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold uppercase text-slate-400">Kembali Aktual</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ optional($peminjaman->tgl_kembali_aktual)->format('d/m/Y') ?? '—' }}</dd>
            </div>
        </dl>

        @if ($peminjaman->items->isNotEmpty())
            <div>
                <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100 mb-2">Aset Dipinjam</h3>
                <div class="rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-400 bg-[#f8fafd] dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-700">
                                <th class="py-2.5 px-3 font-bold">Kode</th>
                                <th class="py-2.5 px-3 font-bold">Nama</th>
                                <th class="py-2.5 px-3 font-bold">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($peminjaman->items as $it)
                            <tr class="border-b border-slate-50 dark:border-slate-700/50">
                                <td class="py-2.5 px-3 font-medium">{{ $it->aset?->kode }}</td>
                                <td class="py-2.5 px-3">{{ $it->aset?->nama }}</td>
                                <td class="py-2.5 px-3">{{ $it->qty }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($peminjaman->alasan_tolak)
            <div class="rounded-2xl border border-rose-200 bg-rose-50 dark:bg-rose-950/30 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-sm p-4">
                <b>Ditolak:</b> {{ $peminjaman->alasan_tolak }}
            </div>
        @endif
    </div>

    <div class="card !rounded-[24px] p-5 sm:p-6 space-y-4 h-fit">
        <h3 class="font-extrabold text-slate-800 dark:text-slate-100">Tindakan</h3>
        @can('sarpras.peminjaman.setujui')
            @if ($peminjaman->status === 'diajukan')
                <form method="POST" action="{{ route('sarpras.peminjaman.setujui', $peminjaman) }}">@csrf
                    <button class="sarpras-google-btn-success w-full px-4 py-2.5 text-sm">
                        <i data-lucide="check" class="w-4 h-4"></i> Setujui (tandai dipinjam)
                    </button>
                </form>
                <form method="POST" action="{{ route('sarpras.peminjaman.tolak', $peminjaman) }}" class="space-y-2">@csrf
                    <textarea name="alasan_tolak" rows="2" placeholder="Alasan tolak" class="sarpras-field"></textarea>
                    <button class="sarpras-google-btn-danger w-full px-4 py-2.5 text-sm">
                        <i data-lucide="x" class="w-4 h-4"></i> Tolak
                    </button>
                </form>
            @endif
        @endcan
        @can('sarpras.peminjaman.kelola')
            @if (in_array($peminjaman->status, ['dipinjam','terlambat']))
                <form method="POST" action="{{ route('sarpras.peminjaman.kembalikan', $peminjaman) }}">@csrf
                    <button class="sarpras-google-btn-primary w-full px-4 py-2.5 text-sm">
                        <i data-lucide="undo-2" class="w-4 h-4"></i> Tandai Dikembalikan
                    </button>
                </form>
            @endif
        @endcan
        @if (
            ! auth()->user()->can('sarpras.peminjaman.setujui')
            && ! (auth()->user()->can('sarpras.peminjaman.kelola') && in_array($peminjaman->status, ['dipinjam','terlambat']))
        )
            <p class="text-sm text-slate-500 dark:text-slate-400">Pantau status pengajuan Anda di sini. Persetujuan dilakukan oleh Waka Sarpras.</p>
        @endif
    </div>
</div>
@endsection
