@extends('layouts.app')
@section('title', 'Upload Tugas — Jam Kosong Saya')

@php
    $rows = $slots->map(fn($p) => [
        'id' => $p->uuid,
        'kelas' => trim(($p->jadwal?->kelas?->tingkat ?? '').' '.($p->jadwal?->kelas?->kelas ?? '')) ?: '-',
        'pelajaran' => $p->jadwal?->pelajaran?->nama ?? $p->jadwal?->keterangan ?? '-',
        'jam_mulai' => $p->jadwal?->jam_mulai,
        'jam_selesai' => $p->jadwal?->jam_selesai,
        'judul' => $p->tugasKelas?->judul ?? '',
        'deskripsi' => $p->tugasKelas?->deskripsi ?? '',
        'ada_file' => $p->tugasKelas?->file_path !== null,
        'terkonfirmasi' => (bool) $p->tugasKelas?->id_agenda,
        'diterima_siswa' => (bool) $p->tugasKelas?->id_classroom_assignment,
        'tugas_id' => $p->tugasKelas?->uuid,
    ])->values();
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-5" x-data="tugasSaya({{ Illuminate\Support\Js::from($rows) }}, {{ Illuminate\Support\Js::from(route('piket.tugas.upload', ['penugasanPengganti' => '__ID__'])) }}, {{ Illuminate\Support\Js::from(route('piket.tugas.destroy', ['tugasKelas' => '__TUGASID__'])) }})">

    <div class="max-w-2xl">
        <h1 class="page-title">@if($absenHariIni) Upload Tugas @else Penugasan Ketidakhadiran @endif</h1>
        @if($absenHariIni)
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Anda tercatat tidak hadir hari ini ({{ \Carbon\Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') }}). Kirim materi/tugas untuk jam yang kosong, sebelum guru piket titip tugas manual.</p>
        @else
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Lengkapi form berikut untuk melaporkan ketidakhadiran Anda hari ini ({{ \Carbon\Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') }}). Setelah melapor, Anda dapat mengunggah tugas untuk kelas yang ditinggalkan.</p>
        @endif
    </div>

    @if(!$absenHariIni)
    <div class="card w-full max-w-4xl p-6" x-data="laporAbsen({{ Illuminate\Support\Js::from(route('piket.tugas.laporMandiri')) }})">
        <form @submit.prevent="kirimLaporan" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="lg:col-span-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Alasan Ketidakhadiran</label>
                <select x-model="form.alasan" class="form-input w-full" required>
                    <option value="" disabled>-- Pilih Alasan --</option>
                    <option value="sakit">Sakit</option>
                    <option value="izin">Izin</option>
                    <option value="dinas_luar">Dinas Luar</option>
                </select>
            </div>
            <div class="lg:col-span-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Keterangan (Opsional)</label>
                <textarea x-model="form.keterangan" rows="3" placeholder="Tambahan keterangan (opsional)" class="form-input w-full text-sm"></textarea>
            </div>
            <button type="submit" :disabled="!form.alasan || busy" class="btn-primary w-full lg:col-span-2 py-2.5 rounded-xl text-sm font-bold disabled:opacity-40 flex items-center justify-center gap-2">
                <span x-show="!busy">Lapor & Lanjut Unggah Tugas</span>
                <span x-show="busy"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Memproses...</span>
            </button>
        </form>
    </div>
    @else

    @if($rows->isEmpty())
    <div class="card p-8 text-center text-slate-400">
        <i data-lucide="calendar-check" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
        <p class="text-sm">Tidak ada jam mengajar terjadwal hari ini.</p>
    </div>
    @else
    <div class="grid grid-cols-1 gap-5 items-start">
        <template x-for="row in rows" :key="row.id">
            <div class="card p-5 space-y-4 relative flex flex-col justify-between">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100" x-text="row.pelajaran"></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-1"><i data-lucide="door-open" class="w-3.5 h-3.5"></i> Kelas <span x-text="row.kelas"></span></span>
                        <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i> <span x-text="row.jam_mulai"></span>–<span x-text="row.jam_selesai"></span></span>
                    </p>
                </div>

                <template x-if="row.terkonfirmasi && row.diterima_siswa">
                    <div>
                        <p class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 inline-flex items-center gap-1 w-fit"><i data-lucide="graduation-cap" class="w-3 h-3"></i> Sudah diterbitkan ke siswa</p>
                        <button type="button" @click="hapus(row)" class="mt-2 text-xs text-red-500 hover:underline flex items-center gap-1" :disabled="busy">
                            <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus Materi/Penugasan
                        </button>
                    </div>
                </template>
                <template x-if="row.terkonfirmasi && !row.diterima_siswa">
                    <div>
                        <p class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 inline-flex items-center gap-1 w-fit"><i data-lucide="check" class="w-3 h-3"></i> Sudah dikonfirmasi guru piket</p>
                        <button type="button" @click="hapus(row)" class="mt-2 text-xs text-red-500 hover:underline flex items-center gap-1" :disabled="busy">
                            <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus Materi/Penugasan
                        </button>
                    </div>
                </template>

                <template x-if="!row.terkonfirmasi">
                    <div class="space-y-2">
                        <input type="text" x-model="row.judul" placeholder="Judul tugas" class="form-input text-sm w-full text-slate-800 dark:text-slate-100 placeholder:text-slate-500 dark:placeholder:text-slate-400 font-medium">
                        <textarea x-model="row.deskripsi" rows="3" placeholder="Instruksi untuk siswa (mis. baca halaman 20-25, kerjakan latihan 1-5)" class="form-input text-sm w-full text-slate-800 dark:text-slate-100 placeholder:text-slate-500 dark:placeholder:text-slate-400 font-medium"></textarea>
                        <input type="file" @change="row._file = $event.target.files[0]" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" class="text-xs w-full text-slate-700 dark:text-slate-300 font-medium">
                        <span x-show="row._file" class="text-xs text-emerald-600 dark:text-emerald-400 block mt-1 font-semibold flex items-center gap-1"><i data-lucide="paperclip" class="w-3 h-3"></i> <span x-text="row._file.name || 'File dilampirkan'"></span></span>
                        
                        <button type="button" @click="kirim(row)" :disabled="!row.judul || !row.deskripsi || busy" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold disabled:opacity-40 mt-3 text-white shadow-md">
                            <span x-show="!row._sukses">Kirim</span>
                            <span x-show="row._sukses" class="flex items-center justify-center gap-1.5"><i data-lucide="check" class="w-4 h-4"></i> Tugas berhasil diterbitkan ke siswa</span>
                        </button>
                        
                        <template x-if="rows.length > 1">
                            <button type="button" @click="salinKeYangLain(row)" class="w-full py-2 rounded-xl text-xs font-bold mt-2 flex items-center justify-center gap-1.5 text-slate-700 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700/50 transition" :disabled="!row.judul || !row.deskripsi || busy">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i> Salin isian ini ke jadwal lain
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
    @endif
    @endif
</div>

<style>
/* Sembunyikan scrollbar untuk tampilan carousel yang lebih bersih */
.hide-scroll::-webkit-scrollbar { display: none; }
.hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }
</style>
@endsection

@push('scripts')
<script>
function tugasSaya(initialRows, urlTemplate, destroyUrlTemplate){
    return {
        rows: (initialRows || []).map(r => ({ ...r, _file: null, _sukses: r.judul !== '' })),
        busy: false,
        async kirim(row){
            this.busy = true;
            try {
                const fd = new FormData();
                fd.append('judul', row.judul);
                fd.append('deskripsi', row.deskripsi);
                if (row._file) fd.append('file', row._file);
                const url = urlTemplate.replace('__ID__', row.id);
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content') },
                    body: fd,
                });
                if (!r.ok) {
                    const err = await r.json().catch(() => ({}));
                    showToast(err.message || 'Gagal mengirim', 'error');
                    return;
                }
                const res = await r.json();
                row.tugas_id = res.id;
                row.terkonfirmasi = res.terkonfirmasi;
                row.diterima_siswa = res.diterima_siswa;
                row._sukses = true;
                showToast('Tugas terkirim', 'success');
            } catch (e) {
                showToast('Gagal terhubung ke server', 'error');
            } finally {
                this.busy = false;
            }
        },
        hapus(row) {
            $.confirm({
                title: 'Hapus Materi?',
                content: 'Yakin ingin menghapus materi/penugasan ini? Jika sudah masuk ke ruang kelas siswa, tugas akan ikut terhapus.',
                type: 'red',
                theme: 'material',
                buttons: {
                    hapus: {
                        text: 'Ya, Hapus',
                        btnClass: 'btn-red',
                        action: async () => {
                            this.busy = true;
                            try {
                                const url = destroyUrlTemplate.replace('__TUGASID__', row.tugas_id);
                                const r = await fetch(url, {
                                    method: 'DELETE',
                                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content') },
                                });
                                if (!r.ok) {
                                    const err = await r.json().catch(() => ({}));
                                    showToast(err.message || 'Gagal menghapus', 'error');
                                    return;
                                }
                                
                                row.judul = '';
                                row.deskripsi = '';
                                row._file = null;
                                row.terkonfirmasi = false;
                                row.diterima_siswa = false;
                                row._sukses = false;
                                row.tugas_id = null;
                                
                                showToast('Materi berhasil dihapus', 'success');
                            } catch (e) {
                                showToast('Gagal terhubung ke server', 'error');
                            } finally {
                                this.busy = false;
                            }
                        }
                    },
                    batal: {
                        text: 'Batal',
                        btnClass: 'btn-default'
                    }
                }
            });
        },
        salinKeYangLain(sourceRow){
            let count = 0;
            this.rows.forEach(r => {
                if (r.id !== sourceRow.id && !r.terkonfirmasi && !r._sukses) {
                    r.judul = sourceRow.judul;
                    r.deskripsi = sourceRow.deskripsi;
                    r._file = sourceRow._file;
                    count++;
                }
            });
            if (count > 0) {
                showToast(`Isian disalin ke ${count} jadwal lain`, 'success');
                // Re-init lucide icons incase new elements showed up
                setTimeout(() => typeof lucide !== 'undefined' && lucide.createIcons(), 50);
            } else {
                showToast('Tidak ada jadwal lain yang bisa disalin', 'info');
            }
        }
    };
}

function laporAbsen(url){
    return {
        form: { alasan: '', keterangan: '' },
        busy: false,
        async kirimLaporan(){
            this.busy = true;
            try {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json', 
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content') 
                    },
                    body: JSON.stringify(this.form),
                });
                if (!r.ok) {
                    const err = await r.json().catch(() => ({}));
                    showToast(err.message || 'Gagal melaporkan', 'error');
                    return;
                }
                showToast('Ketidakhadiran tercatat', 'success');
                window.location.reload();
            } catch (e) {
                showToast('Gagal terhubung ke server', 'error');
            } finally {
                this.busy = false;
            }
        }
    };
}
</script>
@endpush
