@extends('layouts.app')
@section('title', $ruangan->nama)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('ujian.paket.index') }}" class="hover:underline">Paket Ujian</a> /
                <a href="{{ route('ujian.paket.show', $paket) }}" class="hover:underline">{{ $paket->nama }}</a> / {{ $ruangan->nama }}
            </nav>
            <h1 class="page-title">{{ $ruangan->nama }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $ruangan->peserta->count() }} peserta{{ $ruangan->kapasitas ? ' / ' . $ruangan->kapasitas . ' kapasitas' : '' }}
                @if($ruangan->keterangan) · {{ $ruangan->keterangan }} @endif
            </p>
        </div>
        <a href="{{ route('ujian.ruangan.monitor', $ruangan) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="radar" class="w-4 h-4 inline"></i> Buka Monitor Pengawas
        </a>
    </div>

    {{-- Peserta --}}
    <div class="card p-6 space-y-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100">Peserta ({{ $ruangan->peserta->count() }})</h2>
        <div class="table-responsive">
            <table class="data-table w-full text-sm">
                <thead><tr><th>Nama</th><th>NIS</th><th>Kelas</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse($ruangan->peserta->sortBy(fn($p) => $p->siswa?->nama) as $p)
                    <tr>
                        <td class="font-medium">{{ $p->siswa?->nama ?? '(siswa terhapus)' }}</td>
                        <td class="text-slate-500">{{ $p->siswa?->nis }}</td>
                        <td class="text-slate-500">{{ $p->siswa?->kelas?->tingkat }}{{ $p->siswa?->kelas?->kelas }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('ujian.paket.ruangan.peserta.destroy', [$paket, $ruangan, $p]) }}" onsubmit="return confirmAction(this, 'Lepas {{ addslashes($p->siswa?->nama ?? '') }} dari ruangan ini?', 'orange')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-rose-500 hover:underline">Lepas</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-6 text-slate-400">Belum ada peserta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($siswaTersedia->isNotEmpty())
        @php
            $groupedSiswa = $siswaTersedia->groupBy(fn($s) => $s->kelas ? $s->kelas->tingkat . $s->kelas->kelas : 'Lainnya')->sortKeys();
            $alpineData = [];
            foreach ($groupedSiswa as $kelas => $group) {
                $alpineData[$kelas] = $group->map(fn($s) => ['uuid' => $s->uuid, 'nama' => $s->nama, 'nis' => $s->nis, 'kelas' => $kelas])->values()->toArray();
            }
        @endphp
        <div x-data="dragDropRuangan(@js($alpineData))" class="pt-4 border-t border-slate-100 dark:border-slate-700">
            <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-3">Tambah Peserta Baru (Drag & Drop)</h3>
            <div class="flex flex-col md:flex-row gap-5">
                {{-- Kiri: Tersedia per kelas --}}
                <div class="flex-1 space-y-3 min-w-0">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Siswa Tersedia</p>
                    </div>
                    <div class="space-y-2.5 max-h-[500px] overflow-y-auto pr-1">
                        <template x-for="(grup, kelas) in tersedia" :key="kelas">
                            <div class="border border-slate-200 dark:border-slate-700/60 rounded-xl overflow-hidden bg-slate-50 dark:bg-slate-800/30">
                                <div class="flex items-center justify-between px-3 py-2 border-b border-slate-200 dark:border-slate-700/60 cursor-pointer select-none bg-white dark:bg-slate-800" @click="toggleExpand(kelas)">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <i data-lucide="chevron-down" class="w-4 h-4 flex-shrink-0 text-slate-400 transition-transform" :class="expanded[kelas] ? 'rotate-180' : ''"></i>
                                        <span class="font-bold text-sm text-slate-700 dark:text-slate-200 truncate" x-text="kelas"></span>
                                        <span class="text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 py-0.5 rounded-full flex-shrink-0" x-text="grup.length"></span>
                                    </div>
                                    <button type="button" @click.stop="pilihSemua(kelas)" class="text-[11px] flex-shrink-0 font-semibold text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/30 px-2 py-1 rounded transition-colors" x-show="grup.length > 0">
                                        Pilih Semua
                                    </button>
                                </div>
                                <div x-show="expanded[kelas]" x-collapse>
                                    <div class="p-2 space-y-1.5 min-h-[50px] drag-container" :data-grup="kelas">
                                        <template x-for="s in grup" :key="s.uuid">
                                            <div class="drag-item p-2.5 text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 cursor-grab active:cursor-grabbing flex items-center justify-between shadow-sm hover:border-primary-300 dark:hover:border-primary-700 transition-colors" :data-uuid="s.uuid">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <i data-lucide="grip-vertical" class="w-3.5 h-3.5 flex-shrink-0 text-slate-400"></i>
                                                    <span class="font-medium truncate" x-text="s.nama + ' (' + s.nis + ')'"></span>
                                                </div>
                                                <button type="button" @click="pilihSatu(s)" class="text-primary-500 hover:text-primary-700 p-1 flex-shrink-0 bg-primary-50 dark:bg-primary-900/30 rounded" title="Pilih"><i data-lucide="plus" class="w-3 h-3"></i></button>
                                            </div>
                                        </template>
                                        <div x-show="grup.length === 0" class="text-[11px] text-slate-400 p-3 text-center italic bg-transparent border border-dashed border-slate-300 dark:border-slate-600 rounded-lg">Kosong (Habis terpilih)</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Kanan: Terpilih --}}
                <div class="flex-1 flex flex-col min-w-0">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Akan Ditambahkan</p>
                        <span class="text-[10px] font-bold text-primary-600 bg-primary-50 dark:bg-primary-900/30 px-2 py-1 rounded-lg" x-show="terpilih.length > 0" x-text="terpilih.length + ' Peserta'"></span>
                    </div>
                    <div class="flex-1 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-3 bg-slate-50/50 dark:bg-slate-800/30 flex flex-col relative transition-colors" :class="terpilih.length === 0 ? 'bg-slate-50 dark:bg-slate-800/50' : 'bg-primary-50/20 dark:bg-primary-900/10 border-primary-300 dark:border-primary-700'">
                        <div class="flex-1 space-y-1.5 drag-container relative z-10" data-grup="terpilih" id="terpilih-container" style="min-h: 200px;">
                            <template x-for="s in terpilih" :key="s.uuid">
                                <div class="drag-item p-2.5 text-xs border border-primary-200 dark:border-primary-900/60 rounded-lg bg-white dark:bg-slate-900 cursor-grab active:cursor-grabbing flex items-center justify-between shadow-sm" :data-uuid="s.uuid">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <i data-lucide="grip-vertical" class="w-3.5 h-3.5 flex-shrink-0 text-slate-400"></i>
                                        <span class="font-semibold text-primary-800 dark:text-primary-100 truncate" x-text="s.nama + ' (' + s.nis + ') - ' + s.kelas"></span>
                                    </div>
                                    <button type="button" @click="kembalikan(s)" class="text-rose-500 hover:text-rose-700 flex-shrink-0 p-1 bg-rose-50 dark:bg-rose-900/30 rounded" title="Batal Pilih"><i data-lucide="x" class="w-3 h-3"></i></button>
                                </div>
                            </template>
                            <div x-show="terpilih.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 pointer-events-none gap-2">
                                <i data-lucide="move-right" class="w-8 h-8 opacity-50"></i>
                                <span class="text-sm font-medium">Tarik (Drag) siswa ke kotak ini</span>
                                <span class="text-[10px]">Atau klik tombol "+" pada nama siswa</span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('ujian.paket.ruangan.peserta', [$paket, $ruangan]) }}" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        @csrf
                        <template x-for="s in terpilih">
                            <input type="hidden" name="id_siswa[]" :value="s.uuid">
                        </template>
                        <button type="button" @click="kosongkan()" class="text-[11px] font-semibold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 px-3 py-1.5" x-show="terpilih.length > 0">Batalkan Semua</button>
                        <div class="flex-1"></div>
                        <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-sm font-bold bg-primary-600 hover:bg-primary-700 text-white shadow-lg shadow-primary-500/30 transition-all active:scale-95 disabled:opacity-50 disabled:shadow-none" :disabled="terpilih.length === 0">
                            Simpan ke Ruangan
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('dragDropRuangan', (initialData) => ({
                    tersedia: initialData,
                    terpilih: [],
                    expanded: {},
                    sortables: [],

                    init() {
                        const keys = Object.keys(this.tersedia);
                        for(let k of keys) this.expanded[k] = false;
                        if(keys.length > 0) this.expanded[keys[0]] = true;

                        this.$nextTick(() => {
                            this.initSortable();
                            if(window.lucide) window.lucide.createIcons();
                        });
                    },

                    toggleExpand(kelas) {
                        this.expanded[kelas] = !this.expanded[kelas];
                        this.$nextTick(() => {
                            if(this.expanded[kelas]) {
                                this.initSortable();
                                if(window.lucide) window.lucide.createIcons();
                            }
                        });
                    },

                    pilihSemua(kelas) {
                        let items = this.tersedia[kelas] || [];
                        if (items.length === 0) return;
                        this.terpilih.push(...items);
                        this.tersedia[kelas] = [];
                        this.$nextTick(() => { if(window.lucide) window.lucide.createIcons(); this.initSortable(); });
                    },
                    
                    pilihSatu(siswa) {
                        this.tersedia[siswa.kelas] = this.tersedia[siswa.kelas].filter(s => s.uuid !== siswa.uuid);
                        this.terpilih.push(siswa);
                        this.$nextTick(() => { if(window.lucide) window.lucide.createIcons(); this.initSortable(); });
                    },

                    kembalikan(siswa) {
                        this.terpilih = this.terpilih.filter(s => s.uuid !== siswa.uuid);
                        if (!this.tersedia[siswa.kelas]) this.tersedia[siswa.kelas] = [];
                        this.tersedia[siswa.kelas].push(siswa);
                        this.tersedia[siswa.kelas].sort((a, b) => a.nama.localeCompare(b.nama));
                        this.$nextTick(() => { if(window.lucide) window.lucide.createIcons(); this.initSortable(); });
                    },
                    
                    kosongkan() {
                        while(this.terpilih.length > 0) {
                            let s = this.terpilih.pop();
                            if (!this.tersedia[s.kelas]) this.tersedia[s.kelas] = [];
                            this.tersedia[s.kelas].push(s);
                            this.tersedia[s.kelas].sort((a, b) => a.nama.localeCompare(b.nama));
                        }
                        this.$nextTick(() => { if(window.lucide) window.lucide.createIcons(); this.initSortable(); });
                    },

                    initSortable() {
                        this.sortables.forEach(s => s.destroy());
                        this.sortables = [];

                        const containers = document.querySelectorAll('.drag-container');
                        const self = this;

                        containers.forEach(el => {
                            this.sortables.push(new Sortable(el, {
                                group: 'siswa',
                                animation: 150,
                                ghostClass: 'opacity-40',
                                dragClass: 'shadow-2xl',
                                onEnd: (evt) => {
                                    if (evt.from === evt.to) return;

                                    const itemEl = evt.item;
                                    const uuid = itemEl.getAttribute('data-uuid');
                                    const fromGroup = evt.from.getAttribute('data-grup');
                                    const toGroup = evt.to.getAttribute('data-grup');

                                    itemEl.remove();

                                    let movedItem = null;

                                    if (fromGroup === 'terpilih') {
                                        movedItem = self.terpilih.find(s => s.uuid === uuid);
                                        self.terpilih = self.terpilih.filter(s => s.uuid !== uuid);
                                    } else {
                                        movedItem = self.tersedia[fromGroup].find(s => s.uuid === uuid);
                                        self.tersedia[fromGroup] = self.tersedia[fromGroup].filter(s => s.uuid !== uuid);
                                    }

                                    if (movedItem) {
                                        if (toGroup === 'terpilih') {
                                            self.terpilih.push(movedItem);
                                        } else {
                                            if(!self.tersedia[toGroup]) self.tersedia[toGroup] = [];
                                            if (toGroup !== movedItem.kelas) {
                                                self.tersedia[movedItem.kelas].push(movedItem);
                                                self.tersedia[movedItem.kelas].sort((a, b) => a.nama.localeCompare(b.nama));
                                                self.expanded[movedItem.kelas] = true;
                                            } else {
                                                self.tersedia[toGroup].push(movedItem);
                                                self.tersedia[toGroup].sort((a, b) => a.nama.localeCompare(b.nama));
                                            }
                                        }
                                    }

                                    self.$nextTick(() => { if(window.lucide) window.lucide.createIcons(); self.initSortable(); });
                                }
                            }));
                        });
                    }
                }));
            });
        </script>
        @endpush
        @endif
    </div>

    {{-- QR Ruangan --}}
    <div class="card p-6 space-y-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100">QR Ruangan</h2>
        <p class="text-xs text-slate-400">
            Cetak &amp; tempel QR ini secara fisik di ruangan. Siswa scan untuk mencatat kehadiran sendiri.
            Guru scan untuk masuk halaman monitor — guru mana pun boleh masuk, asal ruangan ini punya ujian dijadwalkan pada hari itu.
        </p>
        <div class="flex flex-col sm:flex-row items-center gap-5">
            <img src="{{ $qrUri }}" alt="QR Ruangan {{ $ruangan->nama }}" class="w-44 h-44 flex-shrink-0 border border-slate-100 dark:border-slate-700 rounded-xl p-2">
            <div class="text-sm space-y-2 min-w-0">
                <p class="text-slate-500 dark:text-slate-400 break-all font-mono text-xs">{{ $urlScan }}</p>
                <a href="{{ route('ujian.paket.ruangan.cetak', [$paket, $ruangan]) }}" target="_blank" class="inline-block px-4 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i data-lucide="printer" class="w-3.5 h-3.5 inline"></i> Cetak Poster QR
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
