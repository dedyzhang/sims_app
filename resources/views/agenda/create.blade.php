@extends('layouts.app')
@section('title', 'Tambah Agenda')

@section('content')
<div class="max-w-4xl mx-auto space-y-5"
     x-data="agendaForm({
        presetTanggal: @js($presetTanggal),
        presetJadwal: @js($presetJadwal),
        storeUrl: @js(route('agenda.store')),
        slotsUrl: @js(route('agenda.slots')),
        siswaUrl: @js(route('agenda.siswa')),
        indexUrl: @js(route('agenda.index')),
        mode: 'create'
     })" x-init="init()">

    <div class="flex items-center gap-2">
        <a href="{{ route('agenda.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    </div>
    <div>
        <h1 class="page-title">Tambah Agenda</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Isi agenda harian mengajar: pembahasan, proses, hasil, dan tindak lanjut.</p>
    </div>

    {{-- A. Tanggal & Jadwal --}}
    <div class="card p-5 space-y-4">
        <p class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2"><span class="w-6 h-6 rounded-lg grid place-items-center text-white text-xs font-bold" style="background:var(--cp)">A</span> Tanggal &amp; Jadwal</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-input" x-model="tanggal" @change="loadSlots()">
            </div>
            <div>
                <label class="form-label">Jadwal Mengajar</label>
                <select class="form-select" x-model="jadwal" @change="loadSiswa()">
                    <option value="">— Pilih jadwal —</option>
                    <template x-for="s in slots" :key="s.id_jadwal">
                        <option :value="s.id_jadwal" :disabled="s.terisi" x-text="s.label + (s.terisi ? ' — sudah diisi' : '')"></option>
                    </template>
                </select>
                <p class="text-xs text-slate-400 mt-1" x-show="slotMsg" x-text="slotMsg"></p>
            </div>
        </div>
    </div>

    {{-- B. Agenda --}}
    <div class="card p-5 space-y-4">
        <p class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2"><span class="w-6 h-6 rounded-lg grid place-items-center text-white text-xs font-bold" style="background:var(--cp)">B</span> Agenda Pembelajaran</p>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2">
                <label class="form-label">Pembahasan</label>
                <textarea class="form-input" rows="4" x-model="form.pembahasan" placeholder="Materi/pokok bahasan yang diajarkan"></textarea>
            </div>
            <div>
                <label class="form-label">Metode Pembelajaran</label>
                <textarea class="form-input" rows="4" x-model="form.metode" placeholder="Mis. ceramah, diskusi, praktik"></textarea>
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
                <textarea class="form-input" rows="4" x-model="form.kegiatan" placeholder="Kegiatan selama pembelajaran"></textarea>
            </div>
            <div>
                <label class="form-label">Kendala &amp; Tindak Lanjut</label>
                <textarea class="form-input" rows="4" x-model="form.kendala" placeholder="Kendala yang dihadapi dan rencana tindak lanjut"></textarea>
            </div>
        </div>
    </div>

    {{-- C. Ketidakhadiran Siswa --}}
    @include('agenda.partials.absensi')

    {{-- Tombol simpan --}}
    <div class="flex justify-end">
        <button type="button" @click="submit()" :disabled="loading" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
            <span x-show="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <i x-show="!loading" data-lucide="save" class="w-4 h-4"></i> Simpan Agenda
        </button>
    </div>
</div>
@endsection

@push('scripts')
@include('agenda.partials.form_script')
@endpush
