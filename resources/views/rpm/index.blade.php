@extends('layouts.app')
@section('title', 'RPM Digital (Rencana Pembelajaran Mendalam)')

@section('content')
<div class="space-y-6">
    {{-- Header & Aksi Utama --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary/10 text-primary">
                    <i data-lucide="scroll-text" class="w-5 h-5"></i>
                </span>
                <h1 class="page-title">RPM Digital</h1>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Perencanaan Pembelajaran Mendalam resmi sekolah: susun via AI, upload PDF buku teks, atau ambil dari riwayat Asisten Guru.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('rpm.create') }}" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-sm shadow-primary/20">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Buat RPM Digital</span>
            </a>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
    <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-center gap-3">
        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm flex items-center gap-3">
        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 dark:text-rose-400 flex-shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Statistik Ringkas --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <div class="card p-4 flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 grid place-items-center flex-shrink-0">
                <i data-lucide="files" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Tersimpan</p>
                <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['total'] }}</p>
            </div>
        </div>

        <div class="card p-4 flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 grid place-items-center flex-shrink-0">
                <i data-lucide="sparkles" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Murni AI</p>
                <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['ai'] }}</p>
            </div>
        </div>

        <div class="card p-4 flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 grid place-items-center flex-shrink-0">
                <i data-lucide="file-up" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Upload PDF</p>
                <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['pdf'] }}</p>
            </div>
        </div>

        <div class="card p-4 flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 grid place-items-center flex-shrink-0">
                <i data-lucide="history" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Dari Asisten Guru</p>
                <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['history'] }}</p>
            </div>
        </div>
    </div>

    {{-- Filter Pencarian --}}
    <div class="card p-4 sm:p-5">
        <form method="GET" action="{{ route('rpm.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div class="lg:col-span-2">
                <label class="form-label text-xs">Cari Judul / Topik</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Ketik topik atau materi..." class="form-input pl-9 text-xs">
                </div>
            </div>

            <div>
                <label class="form-label text-xs">Mata Pelajaran</label>
                <select name="mapel" class="form-select text-xs">
                    <option value="">Semua Mapel</option>
                    @foreach($mapelOptions as $m)
                        <option value="{{ $m }}" @selected(request('mapel') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label text-xs">Kelas</label>
                <select name="kelas" class="form-select text-xs">
                    <option value="">Semua Kelas</option>
                    <option value="VII" @selected(request('kelas') === 'VII')>Kelas VII</option>
                    <option value="VIII" @selected(request('kelas') === 'VIII')>Kelas VIII</option>
                    <option value="IX" @selected(request('kelas') === 'IX')>Kelas IX</option>
                </select>
            </div>

            <div>
                <label class="form-label text-xs">Sumber Pembuatan</label>
                <select name="source_type" class="form-select text-xs">
                    <option value="">Semua Sumber</option>
                    <option value="ai_direct" @selected(request('source_type') === 'ai_direct')>Murni AI</option>
                    <option value="pdf_upload" @selected(request('source_type') === 'pdf_upload')>Upload PDF Pelajaran</option>
                    <option value="asisten_guru" @selected(request('source_type') === 'asisten_guru')>Asisten Guru</option>
                </select>
            </div>

            @if($isManager)
            <div class="sm:col-span-2 lg:col-span-5 flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <a href="{{ route('rpm.index', array_merge(request()->except('scope', 'page'), ['scope' => 'all'])) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold border {{ request('scope', 'all') === 'all' ? 'bg-primary text-white border-primary' : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700' }}">
                        Semua Dokumen Sekolah ({{ $stats['total'] }})
                    </a>
                    <a href="{{ route('rpm.index', array_merge(request()->except('scope', 'page'), ['scope' => 'mine'])) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold border {{ request('scope') === 'mine' ? 'bg-primary text-white border-primary' : 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700' }}">
                        Dokumen Saya ({{ $stats['mine'] }})
                    </a>
                </div>

                <div class="flex items-center gap-2">
                    @if(request()->hasAny(['search', 'mapel', 'kelas', 'source_type', 'scope']))
                        <a href="{{ route('rpm.index') }}" class="btn-ghost px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                            Reset Filter
                        </a>
                    @endif
                    <button type="submit" class="btn-primary px-4 py-1.5 text-xs rounded-lg font-semibold flex items-center gap-1.5">
                        <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        <span>Terapkan</span>
                    </button>
                </div>
            </div>
            @else
            <div class="sm:col-span-2 lg:col-span-5 flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                @if(request()->hasAny(['search', 'mapel', 'kelas', 'source_type']))
                    <a href="{{ route('rpm.index') }}" class="btn-ghost px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        Reset Filter
                    </a>
                @endif
                <button type="submit" class="btn-primary px-4 py-1.5 text-xs rounded-lg font-semibold flex items-center gap-1.5">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Terapkan Filter</span>
                </button>
            </div>
            @endif
        </form>
    </div>

    {{-- Daftar Dokumen RPM --}}
    @if($rpms->isEmpty())
    <div class="card p-12 text-center text-slate-400 dark:text-slate-500">
        <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800 mx-auto grid place-items-center mb-4 text-slate-300 dark:text-slate-600">
            <i data-lucide="scroll-text" class="w-8 h-8"></i>
        </div>
        <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">Belum Ada Dokumen RPM Digital</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mt-1 mb-5">
            Anda dapat membuat dokumen RPM baru dengan AI langsung, mengunggah file materi/buku teks PDF pelajaran, atau mengambil dari hasil generate Asisten Guru.
        </p>
        <a href="{{ route('rpm.create') }}" class="btn-primary inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Mulai Buat RPM Digital</span>
        </a>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($rpms as $rpm)
        <div class="card p-5 flex flex-col justify-between hover:border-primary/40 dark:hover:border-primary/40 transition-all hover:shadow-md group">
            <div class="space-y-3">
                {{-- Badges Sumber & Mapel --}}
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border {{ $rpm->sourceTypeBadgeClass() }}">
                        @if($rpm->source_type === 'ai_direct')
                            <i data-lucide="sparkles" class="w-3 h-3"></i>
                        @elseif($rpm->source_type === 'pdf_upload')
                            <i data-lucide="file-up" class="w-3 h-3"></i>
                        @elseif($rpm->source_type === 'asisten_guru')
                            <i data-lucide="history" class="w-3 h-3"></i>
                        @else
                            <i data-lucide="file-text" class="w-3 h-3"></i>
                        @endif
                        {{ $rpm->sourceTypeLabel() }}
                    </span>

                    @if($rpm->kelas || $rpm->semester)
                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">
                        {{ $rpm->kelas ? 'Kls '.$rpm->kelas : '' }}{{ $rpm->semester ? ' • Sem '.$rpm->semester : '' }}
                    </span>
                    @endif
                </div>

                {{-- Judul & Topik --}}
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm leading-snug line-clamp-2 group-hover:text-primary transition-colors">
                        <a href="{{ route('rpm.show', $rpm) }}">{{ $rpm->title }}</a>
                    </h3>
                    @if($rpm->mapel)
                    <p class="text-xs font-semibold text-primary mt-1 flex items-center gap-1.5">
                        <i data-lucide="book-open" class="w-3 h-3"></i>
                        <span>{{ $rpm->mapel }}</span>
                        @if($rpm->alokasi_waktu)
                            <span class="text-slate-400 font-normal">• {{ $rpm->alokasi_waktu }}</span>
                        @endif
                    </p>
                    @endif
                </div>

                {{-- Excerpt Topik --}}
                @if($rpm->topik)
                <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="font-semibold text-slate-700 dark:text-slate-300">Topik:</span> {{ $rpm->topik }}
                </p>
                @endif
            </div>

            <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-800 space-y-3">
                {{-- Meta Pembuat & Tanggal --}}
                <div class="flex items-center justify-between text-[11px] text-slate-400">
                    <span class="truncate max-w-[140px]" title="{{ $rpm->user?->name }}">
                        <i data-lucide="user" class="w-3 h-3 inline mr-0.5"></i>
                        {{ $rpm->user?->name ?? 'Guru' }}
                    </span>
                    <span>{{ $rpm->created_at?->diffForHumans() }}</span>
                </div>

                {{-- Action Buttons --}}
                <div class="grid grid-cols-4 gap-1.5">
                    <a href="{{ route('rpm.show', $rpm) }}" class="btn-ghost py-1.5 px-2 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-primary hover:text-primary flex items-center justify-center gap-1" title="Lihat Dokumen Formal">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Lihat</span>
                    </a>

                    <a href="{{ route('rpm.export-pdf', $rpm) }}" class="btn-ghost py-1.5 px-2 rounded-lg text-xs font-medium text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center justify-center gap-1" title="Unduh PDF Resmi">
                        <i data-lucide="file-down" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">PDF</span>
                    </a>

                    <a href="{{ route('rpm.export-word', $rpm) }}" class="btn-ghost py-1.5 px-2 rounded-lg text-xs font-medium text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 hover:bg-blue-50 dark:hover:bg-blue-950/40 flex items-center justify-center gap-1" title="Unduh Word (.docx) Format Tabel">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Word</span>
                    </a>

                    <div class="flex items-center justify-end gap-1">
                        @can('update', $rpm)
                        <a href="{{ route('rpm.edit', $rpm) }}" class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-800" title="Edit Dokumen">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </a>
                        @endcan

                        @can('delete', $rpm)
                        <form method="POST" action="{{ route('rpm.destroy', $rpm) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen RPM ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40" title="Hapus Dokumen">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $rpms->links() }}
    </div>
    @endif
</div>
@endsection
