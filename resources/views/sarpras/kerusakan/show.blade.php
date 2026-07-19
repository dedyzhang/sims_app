@extends('sarpras.layouts.app')
@section('title', 'Laporan ' . $kerusakan->kode)
@section('sarpras_title', $kerusakan->kode)
@section('sarpras_subtitle', 'Dilaporkan oleh ' . ($kerusakan->pelapor?->name ?? '—') . ' · ' . $kerusakan->created_at->format('d/m/Y H:i'))

@section('sarpras_actions')
    <a href="{{ route('sarpras.kerusakan.index') }}" class="sarpras-google-btn inline-flex px-4 py-2 rounded-xl text-xs sm:text-sm font-bold">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
@endsection

@section('sarpras_body')
@php
    $statusMeta = [
        'dilaporkan' => ['Dilaporkan', 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'],
        'diterima'   => ['Diterima', 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300'],
        'ditolak'    => ['Ditolak', 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300'],
        'selesai'    => ['Selesai', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'],
    ];
    $urgensiMeta = [
        'rendah'  => 'bg-slate-100 text-slate-700',
        'sedang'  => 'bg-amber-100 text-amber-800',
        'tinggi'  => 'bg-orange-100 text-orange-800',
        'darurat' => 'bg-rose-100 text-rose-800',
    ];
    [$statusLabel, $statusClass] = $statusMeta[$kerusakan->status] ?? [ucfirst($kerusakan->status), 'bg-slate-100 text-slate-700'];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 card !rounded-[24px] p-5 sm:p-6 space-y-5">
        <div class="flex justify-between items-start gap-3">
            <div>
                <p class="text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Laporan kerusakan</p>
                <h2 class="text-xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $kerusakan->kode }}</h2>
            </div>
            <div class="flex flex-wrap gap-2 justify-end">
                <span class="badge {{ $urgensiMeta[$kerusakan->urgensi] ?? 'bg-slate-100' }} px-3 py-1.5 text-xs font-extrabold capitalize">{{ $kerusakan->urgensi }}</span>
                <span class="badge {{ $statusClass }} px-3 py-1.5 text-xs font-extrabold">{{ $statusLabel }}</span>
            </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-[11px] font-extrabold uppercase text-slate-400">Objek</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200 font-medium">{{ $kerusakan->aset?->nama ?? $kerusakan->ruangan?->kode ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold uppercase text-slate-400">Ruangan</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $kerusakan->ruangan?->nama ?? $kerusakan->ruangan?->kode ?? '—' }}</dd>
            </div>
        </dl>

        <div>
            <p class="text-[11px] font-extrabold uppercase text-slate-400 mb-1.5">Deskripsi</p>
            <p class="text-sm text-slate-700 dark:text-slate-200 leading-relaxed">{{ $kerusakan->deskripsi }}</p>
        </div>

        @if ($kerusakan->alasan_tolak)
            <div class="rounded-2xl border border-rose-200 bg-rose-50 dark:bg-rose-950/30 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-sm p-4">
                <b>Ditolak:</b> {{ $kerusakan->alasan_tolak }}
            </div>
        @endif

        @if ($kerusakan->foto->count())
            <div>
                <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100 mb-2">Foto</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    @foreach ($kerusakan->foto as $f)
                        <a href="{{ $f->url }}" target="_blank" class="block group">
                            <img loading="lazy" src="{{ $f->url }}" class="w-full h-24 object-cover rounded-xl border border-slate-200 dark:border-slate-600 shadow-sm group-hover:ring-2 group-hover:ring-[#1a73e8]/40 transition">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="card !rounded-[24px] p-5 sm:p-6 space-y-4 h-fit">
        <h3 class="font-extrabold text-slate-800 dark:text-slate-100">Tindakan</h3>
        @can('sarpras.kerusakan.kelola')
            @if ($kerusakan->status === 'dilaporkan')
                <form method="POST" action="{{ route('sarpras.kerusakan.terima', $kerusakan) }}">
                    @csrf
                    <button class="sarpras-google-btn-success w-full px-4 py-2.5 text-sm">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Terima & Buat Order Perbaikan
                    </button>
                </form>
                <form method="POST" action="{{ route('sarpras.kerusakan.tolak', $kerusakan) }}" class="space-y-2">
                    @csrf
                    <textarea name="alasan_tolak" rows="2" placeholder="Alasan penolakan (wajib)" class="sarpras-field"></textarea>
                    <button class="sarpras-google-btn-danger w-full px-4 py-2.5 text-sm">
                        <i data-lucide="x" class="w-4 h-4"></i> Tolak
                    </button>
                </form>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">Laporan sudah diproses.</p>
            @endif
        @else
            <p class="text-sm text-slate-500 dark:text-slate-400">Anda dapat memantau status laporan. Tindak lanjut dilakukan oleh Waka Sarpras.</p>
        @endcan

        @if ($kerusakan->perbaikan->count())
            <div class="pt-2 border-t border-slate-100 dark:border-slate-700">
                <h4 class="text-xs font-extrabold uppercase text-slate-400 mb-2">Order Perbaikan</h4>
                <ul class="text-sm space-y-2">
                    @foreach ($kerusakan->perbaikan as $p)
                        <li>
                            <a href="{{ route('sarpras.perbaikan.show', $p) }}" class="inline-flex items-center gap-1.5 font-bold text-[#1a73e8] hover:underline">
                                {{ $p->kode }}
                            </a>
                            <span class="text-slate-400">· {{ $p->status }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
