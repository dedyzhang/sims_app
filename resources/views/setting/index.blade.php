@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ tab: 'sekolah' }">
    <div class="mb-6">
        <h1 class="page-title">Pengaturan Sistem</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi sekolah, semester, dan penilaian</p>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 rounded-2xl p-1.5 mb-6 flex-wrap">
        @foreach(['sekolah'=>['Identitas','building-2'],'semester'=>['Semester','calendar-days'],'penilaian'=>['Penilaian','calculator'],'absensi'=>['Absensi','clock'],'sosmed'=>['Media Sosial','share-2']] as $key => [$label,$icon])
        <button @click="tab='{{ $key }}'"
                :class="tab==='{{ $key }}' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="seg flex-1 min-w-fit flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-semibold transition">
            <i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Identitas --}}
    <div x-show="tab==='sekolah'" x-transition>
        <form method="POST" action="{{ route('setting.identitas') }}" class="card p-6 space-y-4">
            @csrf
            <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-2">Identitas Sekolah</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach([
                    'nama_sekolah'=>'Nama Sekolah','npsn'=>'NPSN','kepala_sekolah'=>'Kepala Sekolah',
                    'nip_kepala'=>'NIP Kepala','kota'=>'Kota','provinsi'=>'Provinsi','telp_sekolah'=>'No. Telepon',
                ] as $key => $label)
                <div>
                    <label class="form-label">{{ $label }}</label>
                    <input type="text" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? '') }}" class="form-input">
                </div>
                @endforeach
                <div class="sm:col-span-2">
                    <label class="form-label">Alamat Sekolah</label>
                    <textarea name="alamat_sekolah" rows="2" class="form-input">{{ old('alamat_sekolah', $settings['alamat_sekolah'] ?? '') }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>
    </div>

    {{-- Media Sosial --}}
    <div x-show="tab==='sosmed'" x-transition>
        <form method="POST" action="{{ route('setting.mediaSosial') }}" class="card p-6 space-y-5"
              x-data="{ master: {{ ($settings['sosmed_aktif'] ?? '1')=='1' ? 'true' : 'false' }} }">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="share-2" class="w-4 h-4 text-primary"></i> Media Sosial Sekolah</h2>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Cukup tempel tautan — platform otomatis aktif dan ikonnya muncul di dashboard semua pengguna. Kosongkan tautan (atau matikan toggle) untuk menyembunyikan. <span class="font-semibold text-amber-600 dark:text-amber-400">Ikon tidak akan tampil bila tautan kosong.</span></p>
            </div>

            {{-- Master toggle: tampilkan di dashboard --}}
            <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tampilkan di Dashboard</p>
                    <p class="text-xs mt-1 font-semibold" :class="master ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="master ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="sosmed_aktif" value="1" class="sr-only peer" x-model="master">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            {{-- Daftar platform --}}
            <div class="space-y-3" :class="master ? '' : 'opacity-50 pointer-events-none'">
                @foreach(config('sosmed') as $key => $meta)
                @php $urlVal = old('sosmed_'.$key.'_url', $settings['sosmed_'.$key.'_url'] ?? ''); @endphp
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2.5"
                     x-data="{ url: @js($urlVal), on: {{ ($settings['sosmed_'.$key.'_on'] ?? '0')=='1' ? 'true' : 'false' }} }">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-primary/10 text-primary flex-shrink-0">
                        @include('partials.sosmed-icon', ['key' => $key, 'cls' => 'w-4 h-4'])
                    </span>
                    <div class="flex-1 min-w-0">
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $meta['label'] }}</label>
                        <input type="text" name="sosmed_{{ $key }}_url" x-model="url"
                               @input="if (url.trim() !== '') on = true"
                               placeholder="{{ $meta['ph'] }}" class="form-input mt-0.5 py-1.5 text-sm">
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" title="Tampilkan di dashboard">
                        <input type="checkbox" name="sosmed_{{ $key }}_on" value="1" class="sr-only peer" x-model="on">
                        <div class="relative w-9 h-5 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition peer-checked:after:translate-x-4"></div>
                    </label>
                </div>
                @endforeach
            </div>

            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>
    </div>

    {{-- Semester --}}
    <div x-show="tab==='semester'" x-transition class="space-y-4">
        <div class="card p-6 space-y-4">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Semester Aktif</h2>
            <form method="POST" action="{{ route('setting.semester') }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div class="flex-1 min-w-48">
                    <label class="form-label">Pilih Semester Aktif</label>
                    <select name="semester_id" class="form-select">
                        @foreach($semester as $s)
                        <option value="{{ $s->id }}" @selected($s->aktif)>Semester {{ $s->semester }} — {{ $s->tahun }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold">Set Aktif</button>
            </form>
        </div>
        <div class="card p-6 space-y-4">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Tambah Semester</h2>
            <form method="POST" action="{{ route('setting.semester.store') }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="1">Ganjil (1)</option>
                        <option value="2">Genap (2)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tahun Ajaran</label>
                    <input type="text" name="tahun" placeholder="2024/2025" class="form-input">
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold">Tambah</button>
            </form>
        </div>
    </div>

    {{-- Penilaian --}}
    <div x-show="tab==='penilaian'" x-transition>
        <form method="POST" action="{{ route('setting.rumusRapor') }}" class="card p-6 space-y-4">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Rumus Perhitungan Nilai Rapor</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pilih metode perhitungan nilai rapor akhir siswa secara universal di satu tempat saja.</p>
            </div>
            <div class="space-y-1.5">
                <label class="form-label">Metode Perhitungan Rapor</label>
                <select name="rumus_rapor" class="form-select">
                    @foreach(\App\Support\Penilaian::RUMUS as $key => $label)
                        <option value="{{ $key }}" @selected(($settings['rumus_rapor'] ?? 'bagi4') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Rumus Rapor</button>
        </form>

        <a href="{{ route('setting.penjabaran') }}" class="card p-6 flex items-center justify-between gap-3 hover:border-primary transition">
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="list-tree" class="w-[18px] h-[18px] text-primary"></i> Nilai Penjabaran</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Atur mata pelajaran yang punya nilai penjabaran &amp; komponen nilainya (mis. B. Inggris: Listening, Speaking, Reading, Writing).</p>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 flex-shrink-0"></i>
        </a>

        <a href="{{ route('setting.kopRapor') }}" class="card p-6 flex items-center justify-between gap-3 hover:border-primary transition">
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="stamp" class="w-[18px] h-[18px] text-primary"></i> Kop Surat Rapor</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Upload logo (kiri &amp; kanan), ubah teks kepala surat, dan ganti gambar latar (backdrop) pada cetak rapor.</p>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 flex-shrink-0"></i>
        </a>


        <form method="POST" action="{{ route('setting.tpRange') }}" class="card p-6 space-y-4">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="list-checks" class="w-[18px] h-[18px] text-primary"></i> Batas Tujuan Pembelajaran per Materi</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tentukan jumlah minimal &amp; maksimal TP yang boleh ditambahkan guru di tiap materi. Isi <b>0</b> untuk tanpa batas.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <div class="space-y-1.5">
                    <label class="form-label">Minimal TP</label>
                    <input type="number" name="tp_min" min="0" max="50" value="{{ $settings['tp_min'] ?? 0 }}" class="form-input w-28">
                </div>
                <div class="space-y-1.5">
                    <label class="form-label">Maksimal TP</label>
                    <input type="number" name="tp_max" min="0" max="50" value="{{ $settings['tp_max'] ?? 0 }}" class="form-input w-28">
                </div>
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Batas TP</button>
        </form>
    </div>

    {{-- Absensi --}}
    <div x-show="tab==='absensi'" x-transition class="space-y-4">
        <form method="POST" action="{{ route('setting.caraAbsensi') }}" class="card p-6 space-y-4">
            @csrf
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Cara Absensi (Guru &amp; Siswa)</h2>
            <p class="text-xs text-slate-400 -mt-1">Pilih metode absen mandiri. Metode yang tidak dipilih <span class="font-semibold">dikunci</span> untuk guru &amp; siswa. Admin tetap bisa <span class="font-semibold">mengoreksi manual</span> kapan saja.</p>
            @php $caraNow = in_array($settings['cara_absensi_guru'] ?? 'wajah', ['wajah','barcode']) ? ($settings['cara_absensi_guru'] ?? 'wajah') : 'wajah'; @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach(['wajah'=>['Scan Wajah','Pengenalan wajah (kiosk)','scan-face'],'barcode'=>['Barcode / QR','Scan QR Code','qr-code']] as $val => [$lbl,$desc,$icon])
                <label class="cursor-pointer">
                    <input type="radio" name="cara_absensi" value="{{ $val }}" @checked($caraNow===$val) class="sr-only peer">
                    <div class="border-2 rounded-xl p-4 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600 h-full">
                        <i data-lucide="{{ $icon }}" class="w-5 h-5 text-slate-400 peer-checked:text-primary mb-1.5"></i>
                        <p class="font-bold text-sm text-slate-700 dark:text-slate-200">{{ $lbl }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $desc }}</p>
                    </div>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>

        {{-- Wajib isi agenda sebelum absen pulang --}}
        <form method="POST" action="{{ route('setting.agendaWajibPulang') }}" class="card p-6"
              x-data="{ on: {{ ($settings['agenda_wajib_pulang'] ?? '1')=='1' ? 'true' : 'false' }} }">
            @csrf
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="clipboard-pen-line" class="w-4 h-4 text-amber-500"></i> Wajib Isi Agenda Sebelum Pulang</h2>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">Jika aktif, guru tidak dapat absen pulang (scan wajah maupun QR) sebelum seluruh agenda mengajar hari itu diisi.</p>
                    <p class="text-xs mt-2 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="agenda_wajib_pulang" value="1" class="sr-only peer" x-model="on" @change="$el.form.submit()">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>
        </form>

        <div class="grid sm:grid-cols-2 gap-4">
            <form method="POST" action="{{ route('setting.poinTerlambat') }}" class="card p-6 space-y-3">
                @csrf
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Poin Terlambat</h2>
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="form-label">Poin dikurangi</label>
                        <input type="number" name="poin_terlambat" value="{{ $settings['poin_terlambat'] ?? 5 }}" class="form-input">
                    </div>
                    <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-bold">OK</button>
                </div>
            </form>
            <form method="POST" action="{{ route('setting.waktuTerlambat') }}" class="card p-6 space-y-3">
                @csrf
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Batas Jam Terlambat</h2>
                <p class="text-xs text-slate-400 -mt-1">Hadir setelah jam ini dihitung <span class="font-semibold text-rose-500">Terlambat</span>.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Siswa</label>
                        <input type="time" name="waktu_terlambat" value="{{ $settings['waktu_terlambat'] ?? '07:30' }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Guru</label>
                        <input type="time" name="waktu_terlambat_guru" value="{{ $settings['waktu_terlambat_guru'] ?? ($settings['waktu_terlambat'] ?? '07:30') }}" class="form-input">
                    </div>
                </div>
                <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-bold w-full">Simpan</button>
            </form>
        </div>

        {{-- Lokasi & QR Absensi --}}
        <div class="card p-6" x-data="qrLokasi({ lat:@js($settings['sekolah_lat'] ?? ''), lng:@js($settings['sekolah_lng'] ?? '') })" x-init="init()">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="map-pin" class="w-[18px] h-[18px] text-primary"></i> Lokasi & QR Absensi</h2>
                <a href="{{ route('qr.absensi') }}" class="text-xs text-primary font-semibold flex items-center gap-1"><i data-lucide="qr-code" class="w-3.5 h-3.5"></i> Lihat QR hari ini</a>
            </div>
            <p class="text-xs text-slate-400 mb-4">Tetapkan titik sekolah & radius. Absen QR hanya berhasil di dalam radius ini.</p>
            <form method="POST" action="{{ route('setting.lokasiQr') }}" class="space-y-4">
                @csrf
                <div id="setMap" style="height:240px" class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 z-0"></div>
                <p class="text-xs text-slate-400">Klik di peta untuk menetapkan titik sekolah, atau gunakan tombol lokasi.</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <div>
                        <label class="form-label">Latitude</label>
                        <input type="text" name="sekolah_lat" x-model="lat" class="form-input font-mono" placeholder="-0.917">
                    </div>
                    <div>
                        <label class="form-label">Longitude</label>
                        <input type="text" name="sekolah_lng" x-model="lng" class="form-input font-mono" placeholder="104.46">
                    </div>
                    <div>
                        <label class="form-label">Radius (meter)</label>
                        <input type="number" name="absen_radius" value="{{ $settings['absen_radius'] ?? 100 }}" min="10" max="5000" class="form-input">
                    </div>
                </div>
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <button type="button" @click="useMyLocation()" class="text-sm font-semibold text-primary flex items-center gap-1.5"><i data-lucide="locate-fixed" class="w-4 h-4"></i> Gunakan lokasi saya sekarang</button>
                    <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                        <input type="checkbox" name="qr_absensi_aktif" value="1" @checked(($settings['qr_absensi_aktif'] ?? '1')=='1') class="accent-[color:var(--cp)] w-4 h-4"> Aktifkan Absen QR
                    </label>
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Lokasi</button>
            </form>
        </div>
    </div>
</div>

@push('styles')<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function qrLokasi(cfg){
    return {
        lat: cfg.lat || '', lng: cfg.lng || '', map:null, marker:null,
        init(){
            const has = this.lat && this.lng;
            const start = has ? [parseFloat(this.lat), parseFloat(this.lng)] : [-0.9177, 104.4602]; // default Tanjungpinang
            this.$nextTick(()=>{
                if(!document.getElementById('setMap')) return;
                this.map = L.map('setMap').setView(start, has?16:12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19, attribution:'&copy; OpenStreetMap' }).addTo(this.map);
                if(has) this.place(start[0], start[1], false);
                this.map.on('click', e=> this.place(e.latlng.lat, e.latlng.lng));
                // refresh ukuran saat container mendapat lebar penuh (anti peta cuma sebagian)
                try { new ResizeObserver(()=> this.map && this.map.invalidateSize()).observe(document.getElementById('setMap')); } catch(e){}
                [100,400,900,1500].forEach(t=> setTimeout(()=> this.map && this.map.invalidateSize(), t));
            });
        },
        place(la, ln, recenter=true){
            this.lat = (+la).toFixed(6); this.lng = (+ln).toFixed(6);
            if(this.marker) this.map.removeLayer(this.marker);
            this.marker = L.marker([la,ln]).addTo(this.map);
            if(recenter) this.map.setView([la,ln], 16);
        },
        useMyLocation(){
            if(!navigator.geolocation){ showToast('Perangkat tak mendukung lokasi','error'); return; }
            navigator.geolocation.getCurrentPosition(p=> this.place(p.coords.latitude, p.coords.longitude),
                ()=> showToast('Gagal baca lokasi','error'), { enableHighAccuracy:true });
        }
    }
}
</script>
@endpush
@endsection
