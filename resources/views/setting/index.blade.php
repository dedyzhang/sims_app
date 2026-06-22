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
        @foreach(['sekolah'=>['Identitas','building-2'],'semester'=>['Semester','calendar-days'],'penilaian'=>['Penilaian','calculator'],'absensi'=>['Absensi','clock']] as $key => [$label,$icon])
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
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Cara Absensi Guru</h2>
            <div class="grid grid-cols-2 gap-3">
                @foreach(['manual'=>['Manual','Admin input kehadiran'],'barcode'=>['Barcode / QR','Scan QR Code']] as $val => [$lbl,$desc])
                <label class="cursor-pointer">
                    <input type="radio" name="cara_absensi" value="{{ $val }}" @checked(($settings['cara_absensi_guru'] ?? 'manual')===$val) class="sr-only peer">
                    <div class="border-2 rounded-xl p-4 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600">
                        <p class="font-bold text-sm text-slate-700 dark:text-slate-200">{{ $lbl }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $desc }}</p>
                    </div>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
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
