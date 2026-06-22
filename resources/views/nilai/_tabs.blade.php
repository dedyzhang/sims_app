@php
    $tabs = [
        ['nilai.materi',   'Materi & TP', 'book-open'],
        ['nilai.formatif', 'Formatif',    'pencil'],
        ['nilai.sumatif',  'Sumatif',     'clipboard-check'],
    ];
    // Tab Penjabaran hanya bila mapel ini dikonfigurasi punya komponen penjabaran (admin)
    if ($ngajar->pelajaran && $ngajar->pelajaran->penjabaranKomponen()->exists()) {
        $tabs[] = ['nilai.penjabaran', 'Penjabaran', 'list-tree'];
    }
    $tabs[] = ['nilai.pts',   'PTS',    'file-clock'];
    $tabs[] = ['nilai.pas',   'PAS',    'file-check-2'];
    $tabs[] = ['nilai.rapor', 'Rapor',  'file-text'];

    $otherClasses = \App\Models\Ngajar::with('kelas')
        ->where('id_guru', $ngajar->id_guru)
        ->where('id_pelajaran', $ngajar->id_pelajaran)
        ->get()
        ->sortBy(fn($n) => [$n->kelas?->tingkat, $n->kelas?->kelas]);
@endphp
@push('styles')
<style>
    /* Hide scrollbars but keep horizontal scroll functionality */
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

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

    /* Solid backgrounds to prevent underlying content from showing through during scroll */
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

    /* Synchronized row hovers for sticky cells */
    .data-table tbody tr:hover td.sticky-col-no,
    .data-table tbody tr:hover td.sticky-col-nama {
        background-color: color-mix(in srgb, var(--cp) 5%, #fff) !important;
    }
    .dark .data-table tbody tr:hover td.sticky-col-no,
    .dark .data-table tbody tr:hover td.sticky-col-nama {
        background-color: color-mix(in srgb, var(--cp) 12%, #1e293b) !important;
    }

    /* Border divider for the name column */
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

        /* Adjust padding of grading columns to make columns narrower and equal width */
        .data-table th.col-nilai, .data-table td.col-nilai {
            padding: 3px 2px !important;
            min-width: 36px !important;
            width: 36px !important;
        }

        /* Compact grading input cells */
        .nilai-cell {
            min-width: 36px !important;
            min-height: 28px !important;
            line-height: 18px !important;
            padding: 4px 2px !important;
            font-size: 11px !important;
            border-radius: 5px !important;
        }
    }
</style>
@endpush

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <a href="{{ route('nilai.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition flex-shrink-0">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div class="min-w-0">
                <h1 class="page-title text-lg sm:text-2xl font-bold truncate leading-tight">{{ $ngajar->pelajaran?->nama }}</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    Kelas {{ $ngajar->kelas?->tingkat }}{{ $ngajar->kelas?->kelas }}
                    <span class="text-slate-300 dark:text-slate-600">&bull;</span> {{ $ngajar->guru?->nama }}
                    @if($sem) <span class="text-slate-300 dark:text-slate-600">&bull;</span> {{ $sem->nama_lengkap }} @endif
                </p>
            </div>
        </div>

        @if($otherClasses->count() > 1)
        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3.5 py-1.5 rounded-2xl shadow-sm self-start sm:self-auto flex-shrink-0">
            <i data-lucide="shuffle" class="w-4 h-4 text-slate-400"></i>
            <select onchange="window.location.href = this.value" class="bg-transparent border-0 text-sm font-semibold text-slate-700 dark:text-slate-200 outline-none cursor-pointer pr-1 py-0.5">
                @foreach($otherClasses as $on)
                    <option value="{{ route(request()->route()->getName(), $on->uuid) }}" {{ $on->uuid === $ngajar->uuid ? 'selected' : '' }}>
                        Kelas {{ $on->kelas?->tingkat }}{{ $on->kelas?->kelas }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-700 -mb-px no-scrollbar">
        @foreach($tabs as [$route, $label, $icon])
        <a href="{{ route($route, $ngajar->uuid) }}"
           class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold whitespace-nowrap border-b-2 transition {{ request()->routeIs($route) ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
            <i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}
        </a>
        @endforeach
    </div>
</div>
