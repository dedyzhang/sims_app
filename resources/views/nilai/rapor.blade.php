@extends('layouts.app')
@section('title', 'Rapor')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .rapor-nilai { font-weight: 700; cursor: pointer; }
    /* segitiga penanda diubah manual (pojok kanan atas) — utk nilai & deskripsi */
    .ada-manual { position: relative; }
    .ada-manual::after { content: ''; position: absolute; top: 0; right: 0;
        border-top: 11px solid #f59e0b; border-left: 11px solid transparent; }
    .rapor-red { background: rgba(239,68,68,.14) !important; color: #dc2626; }
    .dark .rapor-red { background: rgba(239,68,68,.24) !important; color: #fca5a5; }
    .deskripsi-cell { white-space: normal !important; min-width: 210px; max-width: 340px; line-height: 1.4; cursor: pointer; }
    .deskripsi-cell:hover { background: color-mix(in srgb, var(--cp) 6%, transparent); }
    /* mode terkunci: sel tidak interaktif */
    .rapor-locked .rapor-nilai, .rapor-locked .deskripsi-cell { cursor: default; }
    .rapor-locked .deskripsi-cell:hover { background: transparent; }
</style>
@endpush

@section('content')
@include('nilai._tabs')

@php
    $rowsJs = [];
    foreach ($baris as $b) {
        $sid = $b['siswa']->uuid;
        $auto = (int) $b['hitung']['rapor'];
        $ov = $b['nilaiFinal'];
        $rowsJs[$sid] = [
            'auto'     => $auto,
            'nilai'    => $ov !== null ? (int) $ov : $auto,
            'override' => $ov !== null && (int) $ov !== $auto,
            'pos'      => $b['desPos'] ?? '',
            'posAuto'  => $b['desPosAuto'] ?? '',
            'posOv'    => (bool) ($b['desPosOv'] ?? false),
            'neg'      => $b['desNeg'] ?? '',
            'negAuto'  => $b['desNegAuto'] ?? '',
            'negOv'    => (bool) ($b['desNegOv'] ?? false),
        ];
    }
@endphp

<div class="space-y-4 mt-5"
     :class="{ 'rapor-locked': terkunci }"
     x-data="raporPage(@js($rowsJs), {{ $kktp }}, '{{ route('nilai.rapor.nilai', $ngajar->uuid) }}', '{{ route('nilai.rapor.desk', $ngajar->uuid) }}', @js($tupeList), {{ $terkunci ? 'true' : 'false' }})">

    @include('nilai._terkunci')

    {{-- Rumus + konfirmasi --}}
    <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm">
            <span class="text-slate-500">Rumus nilai rapor:</span>
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $rumusList[$rumus] ?? $rumus }}</span>
            <span class="text-slate-400">&bull; KKTP {{ $kktp }}</span>
        </div>
        <div class="flex items-center gap-2">
            @if($terkunci || !empty($readOnly))
                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 flex items-center gap-1"><i data-lucide="lock" class="w-3.5 h-3.5"></i> Terkunci</span>
                @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('nilai.rapor.batal', $ngajar->uuid) }}" onsubmit="return confirmAction(this, 'Batalkan konfirmasi? Nilai akan bisa diubah lagi.', 'orange')">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold border border-rose-200 dark:border-rose-700/40 text-rose-600 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition"><i data-lucide="unlock" class="w-4 h-4"></i> Batalkan Konfirmasi</button>
                </form>
                @endif
            @else
                <button type="button" @click="modalKonfirmasi = true" class="btn-primary flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold shadow-sm"><i data-lucide="lock" class="w-4 h-4"></i> Konfirmasi Nilai</button>
            @endif
        </div>
    </div>

    @if(empty($baris))
    <div class="card p-12 text-center text-slate-400"><i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada siswa di kelas ini.</p></div>
    @else
    <div class="card overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 text-xs text-slate-400 flex items-start gap-1.5">
            <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i>
            <span>Klik sel <b>Nilai</b> untuk ubah manual (muncul segitiga ⬛). Klik baris deskripsi untuk mengeditnya.</span>
        </div>
        <div class="table-responsive">
            <table class="data-table grid-bordered">
                <thead>
                    <tr>
                        <th class="text-center w-12 sticky-col-no">No</th>
                        <th class="text-left sticky-col-nama">Nama Siswa</th>
                        <th class="text-center col-nilai" title="Rata-rata Formatif">Form.</th>
                        <th class="text-center col-nilai" title="Rata-rata Sumatif">Sum.</th>
                        <th class="text-center col-nilai">PAS</th>
                        <th class="text-center col-nilai" title="PTS — tidak masuk rumus rapor">PTS</th>
                        <th class="text-center col-nilai">Nilai</th>
                        <th class="text-left">Deskripsi Capaian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($baris as $i => $b)
                    @php $sid = $b['siswa']->uuid; $h = $b['hitung']; @endphp
                    <tr>
                        <td rowspan="2" class="text-center text-slate-400 sticky-col-no">{{ $i + 1 }}</td>
                        <td rowspan="2" class="font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap sticky-col-nama">{{ $b['siswa']->nama }}</td>
                        <td rowspan="2" class="text-center col-nilai {{ $h['rataFormatif'] > 0 && $h['rataFormatif'] < $kktp ? 'rapor-red' : '' }}">{{ $h['rataFormatif'] ?: '·' }}</td>
                        <td rowspan="2" class="text-center col-nilai {{ $h['rataSumatif'] > 0 && $h['rataSumatif'] < $kktp ? 'rapor-red' : '' }}">{{ $h['rataSumatif'] ?: '·' }}</td>
                        <td rowspan="2" class="text-center col-nilai {{ $h['pas'] > 0 && $h['pas'] < $kktp ? 'rapor-red' : '' }}">{{ $h['pas'] ?: '·' }}</td>
                        <td rowspan="2" class="text-center col-nilai text-slate-500 {{ ($b['pts'] ?? null) !== null && $b['pts'] < $kktp ? 'rapor-red' : '' }}">{{ $b['pts'] ?? '·' }}</td>
                        <td rowspan="2" class="text-center col-nilai rapor-nilai"
                            @click="openNilai('{{ $sid }}')"
                            :class="{ 'ada-manual': rows['{{ $sid }}'].override, 'rapor-red': rows['{{ $sid }}'].nilai < kktp }">
                            <span x-text="rows['{{ $sid }}'].nilai"></span>
                        </td>
                        <td class="text-xs text-slate-600 dark:text-slate-300 deskripsi-cell" @click="openDesk('{{ $sid }}','positif')" :class="{ 'ada-manual': rows['{{ $sid }}'].posOv }">
                            <span x-text="rows['{{ $sid }}'].pos || '— klik untuk isi deskripsi positif —'" :class="rows['{{ $sid }}'].pos ? '' : 'text-slate-300 dark:text-slate-600 italic'"></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-xs text-slate-600 dark:text-slate-300 deskripsi-cell" @click="openDesk('{{ $sid }}','negatif')" :class="{ 'ada-manual': rows['{{ $sid }}'].negOv }">
                            <span x-text="rows['{{ $sid }}'].neg || '— klik untuk isi deskripsi (perlu ditingkatkan) —'" :class="rows['{{ $sid }}'].neg ? '' : 'text-slate-300 dark:text-slate-600 italic'"></span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-slate-400">
            <span><b class="text-rose-500">Merah</b> = di bawah KKTP ({{ $kktp }})</span>
            <span>⬛ segitiga = nilai diubah manual</span>
            <span>Deskripsi: baris atas = capaian tertinggi, bawah = perlu ditingkatkan</span>
        </div>
    </div>

    {{-- Modal ubah nilai --}}
    <div x-show="modal==='nilai'" x-cloak @keydown.escape.window="tutup()" @click.self="tutup()"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(15,12,10,.55)">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm p-5 space-y-4">
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Ubah Nilai Rapor</h3>
            <p class="text-xs text-slate-400">Ubah manual hanya bila nilai sudah final. Nilai otomatis: <b x-text="autoNilai"></b>.</p>
            <input type="number" min="0" max="100" x-model="nilaiBaru" @keydown.enter="simpanNilai()"
                   class="form-input text-center text-xl font-bold" autofocus>
            <div class="flex items-center justify-between gap-2 pt-1">
                <button type="button" @click="resetNilai()" class="text-xs font-semibold text-slate-500 hover:text-primary">↺ Kembalikan otomatis</button>
                <div class="flex gap-2">
                    <button type="button" @click="tutup()" class="px-3 py-2 rounded-lg text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</button>
                    <button type="button" @click="simpanNilai()" class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal ubah deskripsi (2 mode: Pilih TP / Ketik sendiri) --}}
    <div x-show="modal==='desk'" x-cloak @keydown.escape.window="tutup()" @click.self="tutup()"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(15,12,10,.55)">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-4">
            <h3 class="font-bold text-slate-800 dark:text-slate-100" x-text="deskJenis==='positif' ? 'Deskripsi Capaian (Positif)' : 'Deskripsi — Perlu Ditingkatkan'"></h3>

            {{-- toggle mode --}}
            <div class="flex gap-1 p-1 bg-slate-100 dark:bg-slate-700 rounded-xl text-sm">
                <button type="button" @click="deskMode='pilih'" class="flex-1 py-1.5 rounded-lg transition" :class="deskMode==='pilih' ? 'bg-white dark:bg-slate-800 shadow-sm font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-500'">Pilih TP</button>
                <button type="button" @click="deskMode='ketik'" class="flex-1 py-1.5 rounded-lg transition" :class="deskMode==='ketik' ? 'bg-white dark:bg-slate-800 shadow-sm font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-500'">Ketik sendiri</button>
            </div>

            {{-- Mode Pilih --}}
            <div x-show="deskMode==='pilih'" class="space-y-3">
                <div x-show="deskJenis==='positif'">
                    <label class="form-label">Tingkat penguasaan</label>
                    <select x-model="deskLevel" class="form-select">
                        <option value="amat baik">amat baik</option>
                        <option value="baik">baik</option>
                        <option value="cukup baik">cukup baik</option>
                        <option value="perlu bimbingan">perlu bimbingan</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tujuan Pembelajaran</label>
                    <select x-model="deskTp" class="form-select">
                        <option value="">— pilih TP —</option>
                        <template x-for="tp in tupeList" :key="tp"><option :value="tp" x-text="tp"></option></template>
                    </select>
                    <p x-show="!tupeList.length" class="text-xs text-amber-600 mt-1">Belum ada TP. Pakai mode "Ketik sendiri".</p>
                </div>
                <div class="text-sm bg-slate-50 dark:bg-slate-700/40 rounded-lg p-3 text-slate-600 dark:text-slate-300 italic" x-text="previewDesk() || 'Pilih TP untuk melihat kalimatnya.'"></div>
            </div>

            {{-- Mode Ketik --}}
            <div x-show="deskMode==='ketik'">
                <textarea x-model="deskBaru" rows="4" class="form-input text-sm" placeholder="Tulis deskripsi capaian…"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" @click="tutup()" class="px-3 py-2 rounded-lg text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</button>
                <button type="button" @click="simpanDesk()" class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold">Simpan</button>
            </div>
        </div>
    </div>

    {{-- Modal konfirmasi nilai (pernyataan tanggung jawab) --}}
    <div x-show="modalKonfirmasi" x-cloak @keydown.escape.window="modalKonfirmasi=false" @click.self="modalKonfirmasi=false"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(15,12,10,.55)">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-4">
            <div class="flex items-center gap-2">
                <i data-lucide="shield-check" class="w-5 h-5 text-primary"></i>
                <h3 class="font-bold text-slate-800 dark:text-slate-100">Konfirmasi Nilai Rapor</h3>
            </div>

            <div class="text-sm bg-slate-50 dark:bg-slate-700/40 rounded-xl p-3.5">
                <p class="text-slate-500 mb-2">Dengan ini saya yang bertanggung jawab atas penilaian:</p>
                <div class="grid grid-cols-[120px_1fr] gap-y-1 text-slate-700 dark:text-slate-200">
                    <span class="text-slate-400">Guru</span><span class="font-medium">: {{ $ngajar->guru?->nama }}</span>
                    <span class="text-slate-400">Mata Pelajaran</span><span class="font-medium">: {{ $ngajar->pelajaran?->nama }}</span>
                    <span class="text-slate-400">Kelas</span><span class="font-medium">: {{ $ngajar->kelas?->tingkat }}{{ $ngajar->kelas?->kelas }}</span>
                    <span class="text-slate-400">Semester</span><span class="font-medium">: {{ $sem?->nama_lengkap ?? '-' }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('nilai.rapor.konfirmasi', $ngajar->uuid) }}" class="space-y-3">
                @csrf
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Menyatakan dengan sesungguhnya bahwa:</p>
                <label class="flex items-start gap-2.5 text-sm cursor-pointer text-slate-600 dark:text-slate-300">
                    <input type="checkbox" x-model="setuju1" class="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary">
                    <span>Penilaian untuk kelas &amp; mata pelajaran ini sudah <b>selesai</b> dan <b>siap dikirimkan</b> sebagai nilai rapor.</span>
                </label>
                <label class="flex items-start gap-2.5 text-sm cursor-pointer text-slate-600 dark:text-slate-300">
                    <input type="checkbox" x-model="setuju2" class="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary">
                    <span>Saya <b>bertanggung jawab</b> atas kebenaran nilai yang telah dibuat sesuai standar penilaian.</span>
                </label>
                <p class="text-[11px] text-amber-600 flex items-center gap-1.5"><i data-lucide="alert-triangle" class="w-3.5 h-3.5 flex-shrink-0"></i> Setelah dikonfirmasi, nilai terkunci dan hanya admin yang bisa membukanya kembali.</p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="modalKonfirmasi=false" class="px-3 py-2 rounded-lg text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</button>
                    <button type="submit" :disabled="!(setuju1 && setuju2)"
                            class="px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-1.5 transition"
                            :class="(setuju1 && setuju2) ? 'btn-primary' : 'bg-slate-300 dark:bg-slate-600 text-white cursor-not-allowed'">
                        <i data-lucide="lock" class="w-4 h-4"></i> Konfirmasi &amp; Kunci
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function raporPage(rows, kktp, urlNilai, urlDesk, tupeList, terkunci) {
        return {
            rows: rows, kktp: kktp, urlNilai: urlNilai, urlDesk: urlDesk, tupeList: tupeList || [], terkunci: !!terkunci,
            modal: null, sid: null,
            modalKonfirmasi: false, setuju1: false, setuju2: false,
            nilaiBaru: '', autoNilai: 0,
            deskBaru: '', deskJenis: 'positif',
            deskMode: 'pilih', deskLevel: 'amat baik', deskTp: '',

            openNilai(sid) { if (this.terkunci) return; this.sid = sid; this.autoNilai = this.rows[sid].auto; this.nilaiBaru = this.rows[sid].nilai; this.modal = 'nilai'; },
            openDesk(sid, jenis) {
                if (this.terkunci) return;
                this.sid = sid; this.deskJenis = jenis;
                this.deskBaru = this.rows[sid][jenis === 'positif' ? 'pos' : 'neg'] || '';
                this.deskLevel = jenis === 'positif' ? 'amat baik' : 'perlu bimbingan';
                this.deskTp = this.tupeList.length ? this.tupeList[0] : '';
                // ada teks lama atau tak ada TP → default ke mode Ketik
                this.deskMode = (this.deskBaru || !this.tupeList.length) ? 'ketik' : 'pilih';
                this.modal = 'desk';
            },
            previewDesk() {
                if (!this.deskTp) return '';
                const tp = this.deskTp.trim().replace(/\.+$/, '');
                const low = tp.charAt(0).toLowerCase() + tp.slice(1);
                if (this.deskJenis === 'negatif') return 'Perlu bimbingan dalam ' + low + '.';
                return 'Menunjukkan penguasaan yang ' + this.deskLevel + ' dalam ' + low + '.';
            },
            tutup() { this.modal = null; },

            _post(url, body) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(body),
                }).then(r => { if (!r.ok) throw new Error(); return r.json(); });
            },
            simpanNilai() {
                const v = (this.nilaiBaru === '' || this.nilaiBaru === null) ? null : Math.max(0, Math.min(100, parseInt(this.nilaiBaru, 10)));
                this._post(this.urlNilai, { id_siswa: this.sid, nilai: v }).then(d => {
                    const r = this.rows[this.sid];
                    r.nilai = (d.nilai === null) ? r.auto : d.nilai;
                    r.override = (d.nilai !== null && d.nilai !== r.auto);
                    this.tutup();
                }).catch(() => showToast('Gagal menyimpan nilai.', 'error'));
            },
            resetNilai() {
                this._post(this.urlNilai, { id_siswa: this.sid, nilai: null }).then(() => {
                    const r = this.rows[this.sid];
                    r.nilai = r.auto; r.override = false;
                    this.tutup();
                }).catch(() => showToast('Gagal.', 'error'));
            },
            simpanDesk() {
                const teks = this.deskMode === 'pilih' ? this.previewDesk() : (this.deskBaru || '').trim();
                this._post(this.urlDesk, { id_siswa: this.sid, jenis: this.deskJenis, teks: teks }).then(() => {
                    const r = this.rows[this.sid];
                    const isPos = this.deskJenis === 'positif';
                    if (teks === '') {
                        // kosong → kembali ke deskripsi otomatis, segitiga hilang
                        if (isPos) { r.pos = r.posAuto; r.posOv = false; }
                        else { r.neg = r.negAuto; r.negOv = false; }
                    } else {
                        if (isPos) { r.pos = teks; r.posOv = true; }
                        else { r.neg = teks; r.negOv = true; }
                    }
                    this.tutup();
                }).catch(() => showToast('Gagal menyimpan deskripsi.', 'error'));
            },
        };
    }
</script>
@endpush
