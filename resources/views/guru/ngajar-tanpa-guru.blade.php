@extends('layouts.app')
@section('title', 'Penugasan Mengajar Tanpa Guru')

@section('content')
@php
    $breadcrumbs = [
        ['label' => 'Data Guru', 'url' => route('guru.index')],
        ['label' => 'Penugasan Tanpa Guru', 'url' => '#']
    ];
@endphp

<div class="space-y-5"
     x-data="{
        selectedIds: [],
        selectAll: false,
        toggleAll(event) {
            if (event.target.checked) {
                this.selectedIds = Array.from(document.querySelectorAll('.item-checkbox')).map(el => el.value);
            } else {
                this.selectedIds = [];
            }
        },
        updateSelectAll() {
            const allCheckboxes = Array.from(document.querySelectorAll('.item-checkbox')).map(el => el.value);
            this.selectAll = allCheckboxes.length > 0 && allCheckboxes.every(val => this.selectedIds.includes(val));
        }
     }">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('guru.index') }}"
               class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="page-title text-2xl font-black text-slate-800 dark:text-slate-100 m-0">
                    Penugasan Mengajar Tanpa Guru
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Daftar mata pelajaran dan kelas yang kehilangan guru pengampu (guru telah dihapus dari sistem). Tentukan guru pengganti agar proses KBM, jadwal, dan e-learning tetap berjalan.
                </p>
            </div>
        </div>

        @if($ngajarTanpaGuru->isNotEmpty())
        <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 flex items-center gap-1.5 shadow-sm">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
            {{ $ngajarTanpaGuru->count() }} Penugasan Menggantung
        </span>
        @endif
    </div>

    {{-- Toolbar Aksi Massal (Bulk Transfer) --}}
    @if($ngajarTanpaGuru->isNotEmpty())
    <div x-show="selectedIds.length > 0"
         x-transition
         class="card p-4 bg-primary-50/70 dark:bg-slate-800 border border-primary/30 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 shadow-md rounded-2xl"
         x-cloak>
        <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-800 dark:text-slate-200 font-semibold">
            <i data-lucide="check-square" class="w-4 h-4 text-primary"></i>
            <span><strong x-text="selectedIds.length" class="text-primary font-black"></strong> penugasan dipilih</span>
        </div>

        <form method="POST" action="{{ route('guru.ngajar-tanpa-guru.bulk-ganti') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            @csrf
            {{-- Hidden inputs untuk id_ngajar yang dipilih --}}
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="id_ngajar[]" :value="id">
            </template>

            <select name="id_guru_tujuan" class="form-select text-xs py-2 pl-3 pr-8 rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 min-w-[200px]" required>
                <option value="">— Pilih Guru Pengganti —</option>
                @foreach($guruList as $g)
                <option value="{{ $g->uuid }}">{{ $g->nama }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn-primary text-xs font-bold px-4 py-2 rounded-xl shadow-sm flex items-center justify-center gap-1.5">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>Tugaskan ke Guru Pengganti</span>
            </button>
        </form>
    </div>
    @endif

    {{-- Konten Utama / Tabel --}}
    <div class="card overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-700/60 shadow-sm">
        @if($ngajarTanpaGuru->isEmpty())
            <div class="p-12 text-center space-y-3">
                <div class="w-16 h-16 rounded-3xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto shadow-inner">
                    <i data-lucide="check-circle-2" class="w-8 h-8"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="font-bold text-base text-slate-800 dark:text-slate-100">
                        Semua Penugasan Mengajar Memiliki Guru Pengampu
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                        Tidak ada data mengajar yang kehilangan guru. Seluruh mata pelajaran dan kelas saat ini telah ditugaskan kepada guru aktif.
                    </p>
                </div>
                <div class="pt-2">
                    <a href="{{ route('guru.index') }}" class="btn-outline text-xs font-semibold inline-flex items-center gap-1.5 px-4 py-2 rounded-xl">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        <span>Kembali ke Data Guru</span>
                    </a>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="w-10 text-center">
                                <input type="checkbox"
                                       class="accent-[color:var(--cp)] rounded"
                                       x-model="selectAll"
                                       @change="toggleAll($event)">
                            </th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Status Guru</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ngajarTanpaGuru as $ng)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/50 transition">
                            <td class="text-center">
                                <input type="checkbox"
                                       value="{{ $ng->uuid }}"
                                       x-model="selectedIds"
                                       @change="updateSelectAll()"
                                       class="item-checkbox accent-[color:var(--cp)] rounded">
                            </td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs shrink-0">
                                        <i data-lucide="book-open" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 dark:text-slate-200">
                                            {{ $ng->pelajaran?->nama ?? 'Pelajaran Tidak Ditemukan' }}
                                        </p>
                                        <p class="text-[11px] text-slate-400">
                                            Kode: {{ $ng->pelajaran?->kode ?? '—' }} &bull; KKM: {{ $ng->kktp }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($ng->kelas)
                                <span class="badge bg-primary-50 dark:bg-primary-950/40 text-primary border border-primary/20 font-bold">
                                    Kelas {{ $ng->kelas->tingkat }}{{ $ng->kelas->kelas }}
                                </span>
                                @else
                                <span class="badge bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    Semua Kelas
                                </span>
                                @endif
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                    <i data-lucide="user-x" class="w-3.5 h-3.5"></i>
                                    <span>Guru Telah Dihapus</span>
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1.5" x-data="{ modalGanti: false }">
                                    {{-- Tombol Buka Modal Ganti Guru --}}
                                    <button type="button"
                                            @click="modalGanti = true"
                                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex items-center gap-1 transition shadow-sm"
                                            title="Tugaskan Guru Pengganti">
                                        <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                                        <span>Ganti Guru</span>
                                    </button>

                                    {{-- Tombol Hapus Penugasan --}}
                                    <form method="POST"
                                          action="{{ route('guru.ngajar-tanpa-guru.hapus', $ng->uuid) }}"
                                          onsubmit="return confirmAction(this, 'Hapus penugasan mengajar {{ addslashes($ng->pelajaran?->nama ?? '') }} di kelas {{ addslashes($ng->kelas ? $ng->kelas->tingkat.$ng->kelas->kelas : 'semua') }}? Ruang kelas terkait juga akan dihapus.', 'red')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="p-1.5 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                                                title="Hapus Penugasan">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>

                                    {{-- Modal Ganti Guru Satuan --}}
                                    <template x-teleport="body">
                                        <div x-show="modalGanti"
                                             x-cloak
                                             class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                                             @keydown.escape.window="modalGanti = false">
                                            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
                                                 @click="modalGanti = false"
                                                 x-show="modalGanti"
                                                 x-transition.opacity></div>

                                            <div class="relative card w-full max-w-md p-6 text-left space-y-4"
                                                 x-show="modalGanti"
                                                 x-transition.scale.origin.center
                                                 @click.outside="modalGanti = false">
                                                <div>
                                                    <h3 class="font-bold text-lg text-slate-800 dark:text-slate-100 m-0">
                                                        Tugaskan Guru Pengganti
                                                    </h3>
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                        Alihkan penugasan <strong>{{ $ng->pelajaran?->nama }}</strong> ({{ $ng->kelas ? 'Kelas '.$ng->kelas->tingkat.$ng->kelas->kelas : 'Semua Kelas' }}) ke guru aktif.
                                                    </p>
                                                </div>

                                                <form method="POST" action="{{ route('guru.ngajar-tanpa-guru.ganti', $ng->uuid) }}" class="space-y-4">
                                                    @csrf
                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                                            Pilih Guru Pengganti <span class="text-rose-500">*</span>
                                                        </label>
                                                        <select name="id_guru_tujuan" class="form-select w-full text-sm" required>
                                                            <option value="">— Pilih Guru —</option>
                                                            @foreach($guruList as $g)
                                                            <option value="{{ $g->uuid }}">{{ $g->nama }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 space-y-1">
                                                        <p><i data-lucide="info" class="w-3 h-3 inline text-primary"></i> <strong>Yang akan otomatis dipindahkan:</strong></p>
                                                        <ul class="list-disc list-inside space-y-0.5 text-slate-400">
                                                            <li>Jadwal mengajar kelas ini</li>
                                                            <li>Ruang kelas e-learning (Classroom) &amp; materi</li>
                                                        </ul>
                                                    </div>

                                                    <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-700">
                                                        <button type="button" @click="modalGanti = false" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">
                                                            Batal
                                                        </button>
                                                        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-xs font-bold shadow-sm flex items-center gap-1.5">
                                                            <i data-lucide="check" class="w-4 h-4"></i>
                                                            <span>Simpan Penugasan</span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection