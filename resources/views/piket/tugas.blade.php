@extends('layouts.app')
@section('title', 'Distribusi Tugas ke Kelas')

@php
    $rows = $slots->map(fn($p) => [
        'id' => $p->uuid,
        'guru_absen' => $p->guruTidakHadir?->guru?->nama ?? '-',
        'kelas' => trim(($p->jadwal?->kelas?->tingkat ?? '').' '.($p->jadwal?->kelas?->kelas ?? '')) ?: '-',
        'pelajaran' => $p->jadwal?->pelajaran?->nama ?? $p->jadwal?->keterangan ?? '-',
        'jam_mulai' => $p->jadwal?->jam_mulai,
        'jam_selesai' => $p->jadwal?->jam_selesai,
        'jenis' => $p->tugasKelas?->jenis,
        'judul' => $p->tugasKelas?->judul,
        'deskripsi' => $p->tugasKelas?->deskripsi,
        'ada_file' => $p->tugasKelas?->file_path !== null,
        'terkonfirmasi' => (bool) $p->tugasKelas?->id_agenda,
        'diterima_siswa' => (bool) $p->tugasKelas?->id_classroom_assignment,
    ])->values();
@endphp

@section('content')
<div class="space-y-5" x-data="tugasKelola({{ Illuminate\Support\Js::from($rows) }})">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Distribusi Tugas ke Kelas</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Konfirmasi tugas yang sudah diupload guru asli, atau titip tugas manual — otomatis tercatat ke Buku Agenda dan langsung diterbitkan ke Ruang Kelas siswa yang bersangkutan.</p>
        </div>
        <a href="{{ route('piket.penugasan', ['tanggal' => $tanggal]) }}" class="btn-white flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="user-cog" class="w-4 h-4"></i> Penugasan Pengganti
        </a>
    </div>

    <div class="card p-4">
        <form method="GET" action="{{ route('piket.tugas') }}" class="flex items-end gap-2">
            <div>
                <label class="form-label !mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input !py-2 text-sm" onchange="this.form.submit()">
            </div>
            @if($tanggal !== now()->toDateString())
            <a href="{{ route('piket.tugas') }}" class="px-3 py-2 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-300">Hari ini</a>
            @endif
        </form>
    </div>

    @if($rows->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="clipboard-check" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Tidak ada jam kosong yang perlu diisi tugas pada tanggal ini.</p>
    </div>
    @else
    <div class="space-y-3">
        <template x-for="row in rows" :key="row.id">
            <div class="card p-4 space-y-3">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <p class="font-bold text-slate-800 dark:text-slate-100" x-text="row.pelajaran"></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                            <span class="inline-flex items-center gap-1"><i data-lucide="user-round-x" class="w-3.5 h-3.5"></i> <span x-text="row.guru_absen"></span></span>
                            <span class="inline-flex items-center gap-1"><i data-lucide="door-open" class="w-3.5 h-3.5"></i> Kelas <span x-text="row.kelas"></span></span>
                            <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i> <span x-text="row.jam_mulai"></span>–<span x-text="row.jam_selesai"></span></span>
                        </p>
                    </div>
                    <template x-if="row.terkonfirmasi && row.diterima_siswa">
                        <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center gap-1"><i data-lucide="graduation-cap" class="w-3 h-3"></i> Diterima siswa di Ruang Kelas</span>
                    </template>
                    <template x-if="row.terkonfirmasi && !row.diterima_siswa">
                        <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Tercatat di Agenda</span>
                    </template>
                    <template x-if="!row.terkonfirmasi && row.jenis === 'upload_guru_asli'">
                        <span class="badge bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300">Ada upload guru asli — perlu dikonfirmasi</span>
                    </template>
                    <template x-if="!row.terkonfirmasi && !row.jenis">
                        <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">Belum ada tugas</span>
                    </template>
                </div>

                <template x-if="row.judul">
                    <div class="text-xs text-slate-600 dark:text-slate-300 border-t border-slate-100 dark:border-slate-700 pt-2">
                        <p class="font-semibold" x-text="row.judul"></p>
                        <p class="text-slate-400 mt-0.5" x-text="row.deskripsi"></p>
                    </div>
                </template>

                @if($bolehKelola)
                <div class="flex items-center gap-2 flex-wrap pt-1">
                    <template x-if="!row.terkonfirmasi && row.jenis === 'upload_guru_asli'">
                        <button type="button" @click="konfirmasi(row)" :disabled="busy" class="btn-primary px-4 py-2 rounded-lg text-xs font-bold">Konfirmasi ke Agenda</button>
                    </template>
                    <template x-if="!row.terkonfirmasi">
                        <button type="button" @click="row._tulis = !row._tulis" class="px-4 py-2 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600" x-text="row.jenis === 'upload_guru_asli' ? 'Tulis ulang manual' : 'Titip Tugas Manual'"></button>
                    </template>
                </div>
                <div x-show="row._tulis" x-cloak class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <input type="text" x-model="row._judul" placeholder="Judul tugas" class="form-input text-sm w-full">
                    <textarea x-model="row._deskripsi" rows="3" placeholder="Instruksi untuk siswa" class="form-input text-sm w-full"></textarea>
                    <button type="button" @click="titip(row)" :disabled="!row._judul || !row._deskripsi || busy" class="btn-primary px-4 py-2 rounded-lg text-xs font-bold disabled:opacity-40">Simpan & Kirim ke Siswa</button>
                </div>
                @endif
            </div>
        </template>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function tugasKelola(initialRows){
    return {
        rows: (initialRows || []).map(r => ({ ...r, _tulis: false, _judul: '', _deskripsi: '' })),
        busy: false,
        csrf(){ return document.querySelector('meta[name=csrf-token]').getAttribute('content'); },
        async call(url, body){
            this.busy = true;
            try {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: body ? JSON.stringify(body) : undefined,
                });
                if (!r.ok) {
                    const err = await r.json().catch(() => ({}));
                    showToast(err.message || 'Gagal menyimpan', 'error');
                    return null;
                }
                return await r.json();
            } catch (e) {
                showToast('Gagal terhubung ke server', 'error');
                return null;
            } finally {
                this.busy = false;
            }
        },
        async konfirmasi(row){
            const url = "{{ route('piket.tugas.konfirmasi', ['penugasanPengganti' => '__ID__']) }}".replace('__ID__', row.id);
            const result = await this.call(url);
            if (!result) return;
            Object.assign(row, result);
            showToast(result.diterima_siswa ? 'Tugas dikonfirmasi & diterbitkan ke siswa' : 'Tugas dikonfirmasi & tercatat di agenda', 'success');
        },
        async titip(row){
            const url = "{{ route('piket.tugas.titip', ['penugasanPengganti' => '__ID__']) }}".replace('__ID__', row.id);
            const result = await this.call(url, { judul: row._judul, deskripsi: row._deskripsi });
            if (!result) return;
            Object.assign(row, result);
            row._tulis = false;
            showToast(result.diterima_siswa ? 'Tugas dititip & diterbitkan ke siswa' : 'Tugas dititip & tercatat di agenda', 'success');
        },
    };
}
</script>
@endpush
