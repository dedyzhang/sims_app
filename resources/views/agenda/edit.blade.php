@extends('layouts.app')
@section('title', 'Edit Agenda')

@section('content')
<div class="max-w-4xl mx-auto space-y-5"
     x-data="agendaForm({
        mode: 'edit',
        tanggal: @js($agenda->tanggal->toDateString()),
        jadwal: @js($agenda->id_jadwal),
        updateUrl: @js(route('agenda.update', $agenda)),
        indexUrl: @js(route('agenda.index')),
        siswaUrl: @js(route('agenda.siswa')),
        siswaList: @js($siswaKelas->map(fn($s) => ['uuid' => $s->uuid, 'nama' => $s->nama, 'nis' => $s->nis])),
        form: {
            pembahasan: @js($agenda->pembahasan), metode: @js($agenda->metode),
            proses: @js($agenda->proses ?: 'belum'), kegiatan: @js($agenda->kegiatan), kendala: @js($agenda->kendala)
        },
        absensiRows: @js($agenda->absensi->map(fn($a) => ['id_siswa' => $a->id_siswa, 'nama' => $a->siswa?->nama ?? '-', 'absensi' => $a->absensi, 'keterangan' => $a->keterangan ?? '']))
     })" x-init="init()">

    <div class="flex items-center gap-2">
        <a href="{{ route('agenda.index', ['tanggal' => $agenda->tanggal->toDateString()]) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    </div>
    <div>
        <h1 class="page-title">Edit Agenda</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Perbarui agenda mengajar.</p>
    </div>

    {{-- A. Info (read-only) --}}
    <div class="card p-5">
        <p class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2 mb-3"><span class="w-6 h-6 rounded-lg grid place-items-center text-white text-xs font-bold" style="background:var(--cp)">A</span> Tanggal &amp; Jadwal</p>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300"><i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i> {{ $agenda->tanggal->isoFormat('dddd, D MMMM Y') }}</span>
            <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300"><i data-lucide="door-open" class="w-4 h-4 text-slate-400"></i> Kelas {{ $agenda->kelas ? $agenda->kelas->tingkat.$agenda->kelas->kelas : '-' }}</span>
            <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300"><i data-lucide="book-open" class="w-4 h-4 text-slate-400"></i> {{ $agenda->pelajaran?->nama ?? '-' }}</span>
        </div>
    </div>

    {{-- B. Agenda --}}
    <div class="card p-5 space-y-4">
        <p class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2"><span class="w-6 h-6 rounded-lg grid place-items-center text-white text-xs font-bold" style="background:var(--cp)">B</span> Agenda Pembelajaran</p>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2">
                <label class="form-label">Pembahasan</label>
                <textarea class="form-input" rows="4" x-model="form.pembahasan"></textarea>
            </div>
            <div>
                <label class="form-label">Metode Pembelajaran</label>
                <textarea class="form-input" rows="4" x-model="form.metode"></textarea>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label">Proses Pembelajaran</label>
                <select class="form-select" x-model="form.proses">
                    <option value="belum">Belum Selesai</option>
                    <option value="selesai">Selesai</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Kegiatan</label>
                <textarea class="form-input" rows="4" x-model="form.kegiatan"></textarea>
            </div>
            <div>
                <label class="form-label">Kendala &amp; Tindak Lanjut</label>
                <textarea class="form-input" rows="4" x-model="form.kendala"></textarea>
            </div>
        </div>
    </div>

    {{-- C. Ketidakhadiran Siswa --}}
    @include('agenda.partials.absensi')

    <div class="flex justify-end">
        <button type="button" @click="submit()" :disabled="loading" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
            <span x-show="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <i x-show="!loading" data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
        </button>
    </div>
</div>
@endsection

@push('scripts')
@include('agenda.partials.form_script')
@endpush
