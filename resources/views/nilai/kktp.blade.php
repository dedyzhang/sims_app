@extends('layouts.app')
@section('title', 'Atur KKTP')

@push('styles')
<style>
    /* Sticky columns */
    .sticky-col-no {
        position: sticky !important;
        left: 0 !important;
        z-index: 10 !important;
    }
    .sticky-col-nama {
        position: sticky !important;
        left: 48px; /* Matches w-12 */
        z-index: 10 !important;
    }
    th.sticky-col-no, th.sticky-col-nama {
        background-color: color-mix(in srgb, var(--cp) 4%, #f8fafc) !important;
    }
    .dark th.sticky-col-no, .dark th.sticky-col-nama {
        background-color: #0f172a !important;
    }
    td.sticky-col-no, td.sticky-col-nama {
        background-color: #fff !important;
    }
    .dark td.sticky-col-no, .dark td.sticky-col-nama {
        background-color: #1e293b !important;
    }
    .data-table tbody tr:hover td.sticky-col-no,
    .data-table tbody tr:hover td.sticky-col-nama {
        background-color: color-mix(in srgb, var(--cp) 5%, #fff) !important;
    }
    .dark .data-table tbody tr:hover td.sticky-col-no,
    .dark .data-table tbody tr:hover td.sticky-col-nama {
        background-color: color-mix(in srgb, var(--cp) 12%, #1e293b) !important;
    }
    th.sticky-col-nama, td.sticky-col-nama {
        border-right: 2px solid color-mix(in srgb, var(--cp) 16%, #d8dee9) !important;
    }
    .dark th.sticky-col-nama, .dark td.sticky-col-nama {
        border-right-color: #334155 !important;
    }

    /* Force centered headers to override the global text-align: left rule */
    .data-table thead th.text-center {
        text-align: center !important;
    }

    /* Mobile View overrides (screen size <= 640px) */
    @media (max-width: 640px) {
        /* Make table overall size smaller on mobile devices */
        .data-table thead th {
            padding: 8px 6px !important;
            font-size: 10px !important;
        }
        .data-table tbody td {
            padding: 8px 6px !important;
            font-size: 11px !important;
        }

        /* Adjust sticky offset for smaller No column */
        th.sticky-col-no, td.sticky-col-no {
            width: 36px !important;
            min-width: 36px !important;
            max-width: 36px !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
        }
        .sticky-col-nama {
            left: 36px !important;
        }

        /* Adjust padding of grading columns to make columns narrower */
        .data-table th.col-nilai, .data-table td.col-nilai {
            padding: 3px 2px !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('nilai.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Atur KKTP</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kriteria Ketercapaian Tujuan Pembelajaran (dulu KKM) per mata pelajaran &amp; kelas</p>
        </div>
    </div>

    @if($ngajars->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="book-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada penugasan mengajar.</p>
    </div>
    @else
    <form method="POST" action="{{ route('nilai.kktp.save') }}">
        @csrf
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                <p class="text-sm text-slate-500">KKTP dipakai untuk menentukan <b>Tuntas/Belum</b> &amp; predikat (A/B/C/D) di rapor. Kosongkan untuk pakai default.</p>
                <button type="submit" class="btn-primary flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold shadow-sm"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
            </div>
            <div class="table-responsive">
                <table class="data-table grid-bordered">
                    <thead>
                        <tr>
                            <th class="text-center w-12 sticky-col-no">No</th>
                            <th class="text-left sticky-col-nama">Mata Pelajaran</th>
                            <th class="text-center">Kelas</th>
                            @if($isAdmin)<th class="text-left">Guru</th>@endif
                            <th class="text-center w-28">KKTP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ngajars as $i => $n)
                        <tr>
                            <td class="text-center text-slate-400 sticky-col-no">{{ $i + 1 }}</td>
                            <td class="font-medium text-slate-700 dark:text-slate-200 sticky-col-nama">{{ $n->pelajaran?->nama }}<span class="text-slate-400 text-xs">@if($n->pelajaran?->kode) · {{ $n->pelajaran->kode }} @endif</span></td>
                            <td class="text-center"><span class="badge bg-primary/10 text-primary">{{ $n->kelas?->tingkat }}{{ $n->kelas?->kelas }}</span></td>
                            @if($isAdmin)<td class="text-slate-600 dark:text-slate-300 text-sm">{{ $n->guru?->nama }}</td>@endif
                            <td class="text-center">
                                <input type="number" min="0" max="100" name="kkm[{{ $n->uuid }}]"
                                       value="{{ $n->kkm }}" placeholder="{{ $n->kktp }}"
                                       class="w-20 text-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 py-1.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                <button type="submit" class="btn-primary flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm"><i data-lucide="save" class="w-4 h-4"></i> Simpan KKTP</button>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection
