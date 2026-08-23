@extends('layouts.app')
@section('title', 'Monitor — ' . $ruangan->nama)

@section('content')
<div class="max-w-4xl mx-auto space-y-5"
     x-data="ruanganMonitor({{ Js::from(route('ujian.ruangan.poll', $ruangan)) }}, {{ Js::from(route('ujian.ruangan.bukaKunci', [$ruangan, '__ATTEMPT__'])) }})"
     x-init="init()">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.ruangan.saya') }}" class="hover:underline">Ruang Ujian Hari Ini</a> / {{ $ruangan->nama }}
        </nav>
        <div class="flex items-center gap-2">
            <h1 class="page-title">{{ $ruangan->nama }}</h1>
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="Diperbarui otomatis"></span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $ruangan->paket?->nama }} · Siswa yang sudah mulai mengerjakan · Diperbarui otomatis tiap 5 detik.</p>
        @unless($adaJadwalHariIni)
        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1"><i data-lucide="info" class="w-3.5 h-3.5 inline"></i> Belum ada jadwal ujian untuk hari ini di paket ini — status pengerjaan siswa tidak ditampilkan sampai jadwal ditambahkan.</p>
        @endunless
    </div>

    {{-- Status live — HANYA siswa yg sudah mulai mengerjakan (roster lengkap ada di Daftar Hadir di bawah) --}}
    <div x-show="mapelOpsi.length > 1" class="flex items-center gap-2">
        <label class="text-xs font-semibold text-slate-500">Mata Pelajaran:</label>
        <select class="form-select py-1.5 text-sm w-auto" x-model="mapelFilter" @change="muat()">
            <option value="">Semua mata pelajaran</option>
            <template x-for="m in mapelOpsi" :key="m.uuid">
                <option :value="m.uuid" x-text="m.label"></option>
            </template>
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="text-left px-4 py-2.5">Siswa</th>
                    <th class="text-left px-4 py-2.5">Kelas</th>
                    <th class="text-left px-4 py-2.5">Mata Pelajaran</th>
                    <th class="text-left px-4 py-2.5">Status</th>
                    <th class="text-left px-4 py-2.5">Sisa Waktu</th>
                    <th class="text-left px-4 py-2.5">Pelanggaran</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <template x-for="p in peserta" :key="p.id_peserta">
                    <tr>
                        <td class="px-4 py-2.5 font-medium" x-text="p.nama"></td>
                        <td class="px-4 py-2.5 text-slate-500" x-text="p.kelas"></td>
                        <td class="px-4 py-2.5 text-slate-500" x-text="p.mapel || '—'"></td>
                        <td class="px-4 py-2.5">
                            <span class="badge"
                                  :class="p.dikunci ? 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400' : (p.status==='in_progress' ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' : 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300')"
                                  x-text="p.dikunci ? 'Terkunci' : p.status_label"></span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs" x-text="p.status==='in_progress' ? formatSisa(p.batas_waktu_pada) : '—'"></td>
                        <td class="px-4 py-2.5">
                            <span x-show="p.pelanggaran > 0" class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400" x-text="p.pelanggaran"></span>
                            <span x-show="p.pelanggaran === 0" class="text-slate-300">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            <button type="button" x-show="p.dikunci" @click="bukaKunci(p)" class="text-xs text-primary hover:underline">Buka Kunci</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="peserta.length === 0">
                    <td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada siswa yang mulai mengerjakan.</td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>

    {{-- Berita Acara + Daftar Hadir — SATU modal per sesi (gabungan; guru centang mapel yg
         dicakup, lalu cek/koreksi daftar hadir, satu tombol Simpan utk semuanya). --}}
    <div class="space-y-4" x-data="{ modalTerbuka: null }">
        <div class="flex items-center justify-between px-1 flex-wrap gap-2">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Berita Acara &amp; Daftar Hadir</h2>
            <button type="button" @click="modalTerbuka = 'adhoc-new'" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-1">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Berita Acara (Tanpa Sesi)
            </button>
        </div>

        <div class="grid gap-3">
            @forelse($sesiHariIni as $sesi)
            @php $ba = $sesi->beritaAcara; @endphp
            <div class="card p-4 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100">{{ $sesi->mapelNama() ?: '—' }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ \App\Support\TanggalIndo::panjang($sesi->tanggal) }} &middot;
                        {{ substr($sesi->jam_mulai, 0, 5) }}&ndash;{{ substr($sesi->jam_selesai, 0, 5) }}
                        @if($sesi->label) &middot; Sesi {{ $sesi->label }} @endif
                        &middot; {{ $sesi->jumlahSeharusnya }} peserta seharusnya
                        @php
                            $tokens = $sesi->jadwal
                                ->flatMap(fn($j) => $j->ujian?->kelas ?? collect())
                                ->pluck('token_masuk')
                                ->unique()
                                ->filter()
                                ->values();
                        @endphp
                        @if($tokens->isNotEmpty())
                            &middot; Token: <span class="font-mono font-bold text-slate-700 dark:text-slate-300">{{ $tokens->join(', ') }}</span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    @if($ba->exists)
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Sudah diisi
                    </span>
                    <a href="{{ route('ujian.ruangan.beritaAcara.cetak', [$ruangan, $ba]) }}" target="_blank" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak BA
                    </a>
                    <a href="{{ route('ujian.ruangan.sesi.hadir.cetak', [$ruangan, $sesi]) }}" target="_blank" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak Hadir
                    </a>
                    @endif
                    <button type="button" @click="modalTerbuka = '{{ $sesi->uuid }}'" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold">
                        {{ $ba->exists ? 'Edit' : 'Tambahkan' }} Berita Acara &amp; Daftar Hadir
                    </button>
                </div>
            </div>
            @empty
            <div class="card p-6 text-center text-slate-400 text-sm" x-show="{{ $baAdhocList->isEmpty() ? 'true' : 'false' }}">Belum ada sesi ujian dijadwalkan hari ini di ruangan ini.</div>
            @endforelse

            {{-- Menampilkan Berita Acara Ad-hoc --}}
            @foreach($baAdhocList as $baAdhoc)
            <div class="card p-4 flex items-center justify-between gap-3 flex-wrap border-dashed border-2 border-primary/20">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100">{{ $baAdhoc->mapelNama() ?: '—' }} <span class="badge bg-primary/10 text-primary ml-2">Ad-hoc</span></p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ \App\Support\TanggalIndo::panjang($baAdhoc->tanggal) }} &middot;
                        {{ substr($baAdhoc->sesi?->jam_mulai ?? $baAdhoc->jam_mulai_aktual, 0, 5) }}&ndash;{{ substr($baAdhoc->sesi?->jam_selesai ?? $baAdhoc->jam_selesai_aktual, 0, 5) }}
                        &middot; {{ $baAdhoc->jumlahSeharusnya }} peserta seharusnya
                        @php
                            $adhocTokens = $baAdhoc->ujianList
                                ->flatMap(fn($u) => $u->kelas)
                                ->pluck('token_masuk')
                                ->unique()
                                ->filter()
                                ->values();
                        @endphp
                        @if($adhocTokens->isNotEmpty())
                            &middot; Token: <span class="font-mono font-bold text-slate-700 dark:text-slate-300">{{ $adhocTokens->join(', ') }}</span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Sudah diisi
                    </span>
                    <a href="{{ route('ujian.ruangan.beritaAcara.cetak', [$ruangan, $baAdhoc]) }}" target="_blank" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak BA
                    </a>
                    <a href="{{ route('ujian.ruangan.beritaAcara.hadir.cetak', [$ruangan, $baAdhoc]) }}" target="_blank" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak Hadir
                    </a>
                    <button type="button" @click="modalTerbuka = 'adhoc-{{ $baAdhoc->uuid }}'" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold">
                        Edit Berita Acara &amp; Daftar Hadir
                    </button>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Wadah modal — konten tiap sesi dirender server-side (Blade), ditampilkan/disembunyikan via x-show --}}
        @foreach($sesiHariIni as $sesi)
        @php $ba = $sesi->beritaAcara; @endphp
        <template x-teleport="body">
            <div x-show="modalTerbuka === '{{ $sesi->uuid }}'" x-cloak
                 class="fixed inset-0 z-[100] bg-black/60 flex items-start sm:items-center justify-center p-4 overflow-y-auto"
                 @keydown.escape.window="modalTerbuka = null">
                <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl my-8" @click.outside="modalTerbuka = null">
                    <div class="p-5 flex items-center justify-between border-b border-slate-100 dark:border-slate-700">
                        <div>
                            <p class="font-bold text-slate-800 dark:text-slate-100">Berita Acara &amp; Daftar Hadir</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ $ruangan->nama }} · {{ substr($sesi->jam_mulai, 0, 5) }}–{{ substr($sesi->jam_selesai, 0, 5) }}
                                @if($sesi->label) · Sesi {{ $sesi->label }} @endif
                            </p>
                        </div>
                        <button type="button" @click="modalTerbuka = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('ujian.ruangan.sesi.simpan', [$ruangan, $sesi]) }}" class="p-5 space-y-5 max-h-[75vh] overflow-y-auto">
                        @csrf

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Mata Pelajaran di Sesi Ini</label>
                            <div class="grid gap-2">
                                @forelse($sesi->jadwal as $j)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" name="id_ujian[]" value="{{ $j->id_ujian }}"
                                           @checked($ba->ujianList->contains('uuid', $j->id_ujian))
                                           class="rounded text-primary focus:ring-primary">
                                    {{ $j->ujian?->pelajaran?->nama ?? $j->ujian?->judul }}
                                </label>
                                @empty
                                <p class="text-xs text-slate-400">Tidak ada mapel terjadwal di sesi ini.</p>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Pengawas</label>
                            <select name="id_guru_pengawas" class="form-select">
                                <option value="">— belum ada yang scan / pilih manual —</option>
                                @foreach($guruList as $g)
                                <option value="{{ $g->uuid }}" @selected($ba->id_guru_pengawas === $g->uuid)>{{ $g->nama }}{{ $g->nik ? ' — NIK ' . $g->nik : '' }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Terisi otomatis dari guru yang scan QR ruangan saat sesi ini berlangsung — bisa dikoreksi manual di sini.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jam Mulai Aktual</label>
                                <input type="time" name="jam_mulai_aktual" value="{{ $ba->jam_mulai_aktual ? substr($ba->jam_mulai_aktual,0,5) : substr($sesi->jam_mulai,0,5) }}" class="form-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jam Selesai Aktual</label>
                                <input type="time" name="jam_selesai_aktual" value="{{ $ba->jam_selesai_aktual ? substr($ba->jam_selesai_aktual,0,5) : substr($sesi->jam_selesai,0,5) }}" class="form-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jumlah Hadir</label>
                                <input type="number" min="0" name="jumlah_hadir" value="{{ $ba->jumlah_hadir }}" class="form-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jumlah Tidak Hadir</label>
                                <input type="number" min="0" name="jumlah_tidak_hadir" value="{{ $ba->jumlah_tidak_hadir }}" class="form-input">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Catatan Kejadian</label>
                            <textarea name="catatan_kejadian" rows="3" placeholder="Catatan kejadian selama ujian berlangsung (opsional)..." class="form-input">{{ $ba->catatan_kejadian }}</textarea>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-700 pt-4">
                            <div class="flex items-center justify-between mb-2 flex-wrap gap-1">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Daftar Hadir</label>
                                <p class="text-xs text-slate-400">Terisi otomatis "Hadir" begitu siswa scan QR ruangan — sesuaikan kalau perlu.</p>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-700 border border-slate-100 dark:border-slate-700 rounded-xl overflow-hidden">
                                @forelse($ruangan->peserta->sortBy(fn($p) => $p->siswa?->nama) as $p)
                                @php $existing = $sesi->hadirMap->get($p->id_siswa); @endphp
                                @if($p->siswa)
                                <div class="p-2.5 flex items-center gap-3 flex-wrap">
                                    <input type="hidden" name="hadir[{{ $loop->index }}][id_siswa]" value="{{ $p->id_siswa }}">
                                    <span class="text-sm font-medium flex-1 min-w-32">{{ $p->siswa->nama }}</span>
                                    <select name="hadir[{{ $loop->index }}][status]" class="form-select py-1 text-xs w-auto">
                                        <option value="hadir" @selected(($existing->status ?? 'hadir') === 'hadir')>Hadir</option>
                                        <option value="izin" @selected(($existing->status ?? '') === 'izin')>Izin</option>
                                        <option value="sakit" @selected(($existing->status ?? '') === 'sakit')>Sakit</option>
                                        <option value="alpa" @selected(($existing->status ?? '') === 'alpa')>Alpa</option>
                                    </select>
                                    <input type="text" name="hadir[{{ $loop->index }}][keterangan]" value="{{ $existing->keterangan ?? '' }}" placeholder="Keterangan (opsional)" class="form-input py-1 text-xs flex-1 min-w-40">
                                </div>
                                @endif
                                @empty
                                <p class="text-sm text-slate-400 p-3">Belum ada peserta di ruangan ini.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modalTerbuka = null" class="px-5 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</button>
                            <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
        @endforeach

        {{-- Modal Ad-hoc Baru & Edit --}}
        @php
            // Gabungkan baAdhocList dengan satu entitas kosong untuk modal "Baru"
            $adhocModals = $baAdhocList->concat([new \App\Models\UjianBeritaAcara()]);
        @endphp
        @foreach($adhocModals as $baAdhocModal)
        @php $modalId = $baAdhocModal->exists ? $baAdhocModal->uuid : 'new'; @endphp
        <template x-teleport="body">
            <div x-show="modalTerbuka === 'adhoc-{{ $modalId }}'" x-cloak
                 class="fixed inset-0 z-[100] bg-black/60 flex items-start sm:items-center justify-center p-4 overflow-y-auto"
                 @keydown.escape.window="modalTerbuka = null">
                <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl my-8" @click.outside="modalTerbuka = null">
                    <div class="p-5 flex items-center justify-between border-b border-slate-100 dark:border-slate-700">
                        <div>
                            <p class="font-bold text-slate-800 dark:text-slate-100">{{ $baAdhocModal->exists ? 'Edit Berita Acara Ad-hoc' : 'Berita Acara Baru (Tanpa Sesi)' }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $ruangan->nama }}</p>
                        </div>
                        <button type="button" @click="modalTerbuka = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('ujian.ruangan.beritaAcara.adhoc', [$ruangan, $baAdhocModal->exists ? $baAdhocModal : null]) }}" class="p-5 space-y-5 max-h-[75vh] overflow-y-auto">
                        @csrf

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Mata Pelajaran (Bisa Pilih >1)</label>
                            <div class="grid gap-2 max-h-40 overflow-y-auto p-2 border border-slate-100 dark:border-slate-700 rounded-lg">
                                @forelse($allUjianList as $uj)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" name="id_ujian[]" value="{{ $uj->uuid }}"
                                           @checked($baAdhocModal->exists && $baAdhocModal->ujianList->contains('uuid', $uj->uuid))
                                           class="rounded text-primary focus:ring-primary">
                                    {{ $uj->pelajaran?->nama ?? $uj->judul }}
                                </label>
                                @empty
                                <p class="text-xs text-slate-400">Tidak ada mapel di paket ujian ini.</p>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Pengawas</label>
                            <select name="id_guru_pengawas" class="form-select">
                                <option value="">— pilih manual —</option>
                                @foreach($guruList as $g)
                                <option value="{{ $g->uuid }}" @selected($baAdhocModal->id_guru_pengawas === $g->uuid)>{{ $g->nama }}{{ $g->nik ? ' — NIK ' . $g->nik : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jam Mulai Aktual</label>
                                <input type="time" required name="jam_mulai_aktual" value="{{ $baAdhocModal->jam_mulai_aktual ? substr($baAdhocModal->jam_mulai_aktual,0,5) : ($baAdhocModal->sesi?->jam_mulai ? substr($baAdhocModal->sesi->jam_mulai,0,5) : '') }}" class="form-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jam Selesai Aktual</label>
                                <input type="time" required name="jam_selesai_aktual" value="{{ $baAdhocModal->jam_selesai_aktual ? substr($baAdhocModal->jam_selesai_aktual,0,5) : ($baAdhocModal->sesi?->jam_selesai ? substr($baAdhocModal->sesi->jam_selesai,0,5) : '') }}" class="form-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jumlah Hadir</label>
                                <input type="number" min="0" name="jumlah_hadir" value="{{ $baAdhocModal->jumlah_hadir }}" class="form-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Jumlah Tidak Hadir</label>
                                <input type="number" min="0" name="jumlah_tidak_hadir" value="{{ $baAdhocModal->jumlah_tidak_hadir }}" class="form-input">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Catatan Kejadian</label>
                            <textarea name="catatan_kejadian" rows="3" placeholder="Catatan kejadian selama ujian berlangsung (opsional)..." class="form-input">{{ $baAdhocModal->catatan_kejadian }}</textarea>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-700 pt-4">
                            <div class="flex items-center justify-between mb-2 flex-wrap gap-1">
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Daftar Hadir</label>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-700 border border-slate-100 dark:border-slate-700 rounded-xl overflow-hidden">
                                @forelse($ruangan->peserta->sortBy(fn($p) => $p->siswa?->nama) as $p)
                                @php $existing = $baAdhocModal->exists ? $baAdhocModal->hadirMap->get($p->id_siswa) : null; @endphp
                                @if($p->siswa)
                                <div class="p-2.5 flex items-center gap-3 flex-wrap">
                                    <input type="hidden" name="hadir[{{ $loop->index }}][id_siswa]" value="{{ $p->id_siswa }}">
                                    <span class="text-sm font-medium flex-1 min-w-32">{{ $p->siswa->nama }}</span>
                                    <select name="hadir[{{ $loop->index }}][status]" class="form-select py-1 text-xs w-auto">
                                        <option value="hadir" @selected(($existing->status ?? 'hadir') === 'hadir')>Hadir</option>
                                        <option value="izin" @selected(($existing->status ?? '') === 'izin')>Izin</option>
                                        <option value="sakit" @selected(($existing->status ?? '') === 'sakit')>Sakit</option>
                                        <option value="alpa" @selected(($existing->status ?? '') === 'alpa')>Alpa</option>
                                    </select>
                                    <input type="text" name="hadir[{{ $loop->index }}][keterangan]" value="{{ $existing->keterangan ?? '' }}" placeholder="Keterangan (opsional)" class="form-input py-1 text-xs flex-1 min-w-40">
                                </div>
                                @endif
                                @empty
                                <p class="text-sm text-slate-400 p-3">Belum ada peserta di ruangan ini.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modalTerbuka = null" class="px-5 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</button>
                            <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
function ruanganMonitor(urlPoll, urlUnlockTemplate) {
    return {
        peserta: [],
        mapelOpsi: [],
        mapelFilter: '',
        _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        _timer: null,

        init() {
            this.muat();
            this._timer = setInterval(() => this.muat(), 5000);
        },

        async muat() {
            try {
                const url = this.mapelFilter ? urlPoll + '?mapel=' + encodeURIComponent(this.mapelFilter) : urlPoll;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.peserta = data.peserta;
                this.mapelOpsi = data.mapelOpsi;
            } catch (e) {}
        },

        formatSisa(iso) {
            if (!iso) return '—';
            const detik = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
            if (detik <= 0) return 'Habis';
            const m = Math.floor(detik / 60), s = detik % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },

        async bukaKunci(p) {
            const self = this;
            $.confirm({
                title: 'Buka Kunci?',
                content: `Buka kunci ${p.nama}? Siswa akan bisa melanjutkan ujian dari titik terakhir.`,
                type: 'orange',
                buttons: {
                    ya: {
                        text: 'Ya, Buka Kunci', btnClass: 'btn-blue', keys: ['enter'],
                        action: async function () {
                            await fetch(urlUnlockTemplate.replace('__ATTEMPT__', p.attempt_uuid), {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': self._csrf, 'Accept': 'application/json' },
                            });
                            self.muat();
                        },
                    },
                    batal: { text: 'Batal' },
                },
            });
        },
    };
}
</script>
@endpush
