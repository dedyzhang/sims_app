@extends('layouts.app')
@section('title', 'Guru Tidak Hadir')

@section('content')
<div class="space-y-5" x-data="tidakHadirKelola(
        {{ Illuminate\Support\Js::from($guruList->map(fn($g) => ['uuid' => $g->uuid, 'nama' => $g->nama])) }},
        {{ Illuminate\Support\Js::from(route('piket.tidak-hadir.store')) }}
    )">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Guru Tidak Hadir</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Otomatis dari Absensi PTK, dilengkapi input manual guru piket. Setiap guru tidak hadir menampilkan jam pelajaran yang kosong.</p>
        </div>
        <a href="{{ route('piket.jadwal') }}" class="btn-white flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="calendar-days" class="w-4 h-4"></i> Jadwal Piket
        </a>
    </div>

    {{-- Navigasi tanggal --}}
    <div class="card p-4 flex items-center justify-between flex-wrap gap-3">
        <form method="GET" action="{{ route('piket.tidak-hadir') }}" class="flex items-end gap-2">
            <div>
                <label class="form-label !mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input !py-2 text-sm" onchange="this.form.submit()">
            </div>
            @if($tanggal !== now()->toDateString())
            <a href="{{ route('piket.tidak-hadir') }}" class="px-3 py-2 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Hari ini</a>
            @endif
        </form>
        @if($bolehInput)
        <button type="button" @click="tambah()" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Catat Manual
        </button>
        @endif
    </div>

    {{-- Form input manual --}}
    <div class="card p-4 space-y-3 border-primary/40" x-show="formTambah" x-cloak>
        <p class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="user-plus" class="w-4 h-4"></i> Catat Guru Tidak Hadir (Manual)</p>
        <div class="grid sm:grid-cols-3 gap-3">
            <div>
                <label class="form-label !mb-1">Guru</label>
                <select x-model="baru.id_guru" class="form-input text-sm">
                    <option value="">Pilih guru...</option>
                    <template x-for="g in guruList" :key="g.uuid"><option :value="g.uuid" x-text="g.nama"></option></template>
                </select>
            </div>
            <div>
                <label class="form-label !mb-1">Alasan</label>
                <select x-model="baru.alasan" class="form-input text-sm">
                    <option value="sakit">Sakit</option>
                    <option value="izin">Izin</option>
                    <option value="dinas_luar">Dinas Luar</option>
                    <option value="alpa">Alpa</option>
                </select>
            </div>
            <div>
                <label class="form-label !mb-1">Keterangan (opsional)</label>
                <input type="text" x-model="baru.keterangan" class="form-input text-sm" placeholder="mis. rawat inap, urusan keluarga">
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="simpan('{{ $tanggal }}')" :disabled="!baru.id_guru || busy" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold disabled:opacity-40 disabled:cursor-not-allowed">Simpan</button>
            <button type="button" @click="formTambah = false" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600">Batal</button>
        </div>
    </div>

    {{-- Daftar --}}
    @if($daftar->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-check" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Tidak ada guru tidak hadir tercatat pada tanggal ini.</p>
    </div>
    @else
    <div class="space-y-3">
        @foreach($daftar as $item)
        @php $g = $item['model']; @endphp
        <div class="card p-4 space-y-3" x-data="{ buka: false }">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 grid place-items-center flex-shrink-0">
                        <i data-lucide="user-round" class="w-5 h-5 text-slate-400"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 dark:text-slate-100">{{ $g->guru?->nama ?? '-' }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                            <span class="badge {{ $g->sumber === 'manual_piket' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' : 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300' }}">{{ \App\Models\GuruTidakHadir::SUMBER[$g->sumber] }}</span>
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ \App\Models\GuruTidakHadir::ALASAN[$g->alasan] }}</span>
                        </div>
                    </div>
                </div>
                <button type="button" @click="buka = !buka" class="text-xs font-semibold text-primary flex items-center gap-1 flex-shrink-0">
                    <span x-text="buka ? 'Sembunyikan jam kosong' : '{{ count($item['jam_kosong']) }} jam kosong'"></span>
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5" :class="buka && 'rotate-180'"></i>
                </button>
            </div>
            @if($g->keterangan)
            <p class="text-xs text-slate-500 dark:text-slate-400 pl-13">{{ $g->keterangan }}</p>
            @endif
            <div x-show="buka" x-cloak class="pt-3 border-t border-slate-100 dark:border-slate-700">
                @if(empty($item['jam_kosong']))
                <p class="text-xs text-slate-400">Tidak ada jam mengajar terjadwal pada hari ini.</p>
                @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach($item['jam_kosong'] as $jk)
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-2.5 text-xs">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $jk['pelajaran'] }}</p>
                        <p class="text-slate-400 mt-0.5 flex items-center gap-1"><i data-lucide="door-open" class="w-3 h-3"></i> Kelas {{ $jk['kelas'] }}</p>
                        <p class="text-slate-400 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> {{ $jk['jam_mulai'] }}–{{ $jk['jam_selesai'] }}</p>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('piket.penugasan', ['tanggal' => $tanggal]) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary mt-3 hover:underline">
                    <i data-lucide="user-cog" class="w-3.5 h-3.5"></i> Tugaskan pengganti untuk jam-jam ini
                </a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function tidakHadirKelola(guruList, storeUrl){
    return {
        guruList: guruList || [],
        formTambah: false,
        busy: false,
        baru: { id_guru: '', alasan: 'sakit', keterangan: '' },
        tambah(){ this.formTambah = true; },
        async simpan(tanggal){
            if (!this.baru.id_guru) return;
            this.busy = true;
            try {
                const r = await fetch(storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content') },
                    body: JSON.stringify({ id_guru: this.baru.id_guru, tanggal: tanggal, alasan: this.baru.alasan, keterangan: this.baru.keterangan || null }),
                });
                if (!r.ok) {
                    const err = await r.json().catch(() => ({}));
                    showToast(err.message || 'Gagal menyimpan', 'error');
                    return;
                }
                showToast('Guru tidak hadir tercatat', 'success');
                window.location.reload();
            } catch (e) {
                showToast('Gagal terhubung ke server', 'error');
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endpush
