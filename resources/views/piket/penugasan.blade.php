@extends('layouts.app')
@section('title', 'Penugasan Guru Pengganti')

@php
    $rows = $daftar->map(fn($d) => [
        'id' => $d['model']->uuid,
        'id_guru_tidak_hadir' => $d['model']->id_guru_tidak_hadir,
        'guru_absen' => $d['model']->guruTidakHadir?->guru?->nama ?? '-',
        'jam_ke' => $d['model']->jadwal?->jam_ke,
        'jam_mulai' => $d['model']->jadwal?->jam_mulai,
        'jam_selesai' => $d['model']->jadwal?->jam_selesai,
        'kelas' => trim(($d['model']->jadwal?->kelas?->tingkat ?? '').' '.($d['model']->jadwal?->kelas?->kelas ?? '')) ?: '-',
        'pelajaran' => $d['model']->jadwal?->pelajaran?->nama ?? $d['model']->jadwal?->keterangan ?? '-',
        'status' => $d['model']->status,
        'guru_pengisi' => $d['model']->guru_pengisi,
        'guru_tersedia' => $d['guru_tersedia']->map(fn($g) => ['uuid' => $g->uuid, 'nama' => $g->nama])->values(),
    ])->values();
@endphp

@section('content')
<div class="space-y-5" x-data="penugasanKelola({{ Illuminate\Support\Js::from($rows) }})">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Penugasan Guru Pengganti</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tugaskan guru pengganti per jam kosong, atau tandai piket sendiri yang masuk.</p>
        </div>
        <a href="{{ route('piket.tidak-hadir', ['tanggal' => $tanggal]) }}" class="btn-white flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="user-x" class="w-4 h-4"></i> Guru Tidak Hadir
        </a>
    </div>

    {{-- Navigasi tanggal + ringkasan status --}}
    <div class="card p-4 flex items-center justify-between flex-wrap gap-4">
        <form method="GET" action="{{ route('piket.penugasan') }}" class="flex items-end gap-2">
            <div>
                <label class="form-label !mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input !py-2 text-sm" onchange="this.form.submit()">
            </div>
            @if($tanggal !== now()->toDateString())
            <a href="{{ route('piket.penugasan') }}" class="px-3 py-2 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Hari ini</a>
            @endif
        </form>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $ringkasan['menunggu'] }} Menunggu</span>
            <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">{{ $ringkasan['ditugaskan'] }} Ditugaskan</span>
            <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">{{ $ringkasan['selesai'] }} Selesai</span>
        </div>
    </div>

    @if($rows->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Tidak ada jam kosong yang perlu ditugaskan pada tanggal ini.</p>
    </div>
    @else
    <div class="space-y-3">
        <template x-for="row in rows" :key="row.id">
            <div class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-slate-800 dark:text-slate-100" x-text="row.pelajaran"></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                        <span class="inline-flex items-center gap-1"><i data-lucide="user-round-x" class="w-3.5 h-3.5"></i> <span x-text="row.guru_absen"></span> tidak hadir</span>
                        <span class="inline-flex items-center gap-1"><i data-lucide="door-open" class="w-3.5 h-3.5"></i> Kelas <span x-text="row.kelas"></span></span>
                        <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i> <span x-text="row.jam_mulai"></span>–<span x-text="row.jam_selesai"></span></span>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                    <span class="badge" :class="{
                        'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300': row.status === 'menunggu',
                        'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300': row.status === 'ditugaskan',
                        'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300': row.status === 'selesai',
                    }" x-text="row.status === 'menunggu' ? 'Menunggu' : (row.status === 'ditugaskan' ? 'Ditugaskan: ' + row.guru_pengisi : 'Selesai: ' + row.guru_pengisi)"></span>

                    @if($bolehKelola)
                    <template x-if="row.status === 'menunggu'">
                        <div class="flex items-center gap-1.5">
                            <select x-model="row._pilih" class="form-input !py-1.5 text-xs">
                                <option value="">Pilih pengganti...</option>
                                <template x-for="g in row.guru_tersedia" :key="g.uuid"><option :value="g.uuid" x-text="g.nama"></option></template>
                            </select>
                            <button type="button" @click="assign(row)" :disabled="!row._pilih || busy" class="btn-primary px-3 py-1.5 rounded-lg text-xs font-bold disabled:opacity-40">Tugaskan</button>
                            <button type="button" @click="ambilAlih(row)" :disabled="busy" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 whitespace-nowrap">Saya yang masuk</button>
                        </div>
                    </template>
                    <template x-if="row.status === 'ditugaskan'">
                        <button type="button" @click="selesai(row)" :disabled="busy" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-emerald-200 dark:border-emerald-800 text-emerald-600">Tandai Selesai</button>
                    </template>
                    @endif
                </div>
            </div>
        </template>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function penugasanKelola(initialRows){
    return {
        rows: (initialRows || []).map(r => ({ ...r, _pilih: '' })),
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
        async assign(row){
            const url = "{{ route('piket.penugasan.assign', ['penugasanPengganti' => '__ID__']) }}".replace('__ID__', row.id);
            const result = await this.call(url, { id_guru_pengganti: row._pilih });
            if (!result) return;
            Object.assign(row, result);
            showToast('Guru pengganti ditugaskan', 'success');
        },
        async ambilAlih(row){
            const url = "{{ route('piket.penugasan.ambil-alih', ['penugasanPengganti' => '__ID__']) }}".replace('__ID__', row.id);
            const result = await this.call(url);
            if (!result) return;
            Object.assign(row, result);
            showToast('Anda ditandai mengisi jam ini', 'success');
        },
        async selesai(row){
            const url = "{{ route('piket.penugasan.selesai', ['penugasanPengganti' => '__ID__']) }}".replace('__ID__', row.id);
            const result = await this.call(url);
            if (!result) return;
            Object.assign(row, result);
            showToast('Ditandai selesai', 'success');
        },
    };
}
</script>
@endpush
