@extends('layouts.app')
@section('title', 'Absen QR')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #absenMap { height: 240px; border-radius: 16px; z-index:0; }
    #reader { width:100%; border-radius:16px; overflow:hidden; }
    #reader video { border-radius:16px; }
</style>
@endpush

@section('content')
<div class="max-w-lg mx-auto space-y-4" x-data="qrAbsen({ lat:@js($lat), lng:@js($lng), radius:{{ $radius }}, aktif:{{ $aktif ? 'true':'false' }}, isGuru:{{ ($isGuru ?? false) ? 'true':'false' }} })" x-init="init()">

    <div>
        <h1 class="page-title">Absen QR</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pindai QR absensi sekolah hari ini. Lokasi Anda harus berada di area sekolah.</p>
    </div>

    @if($kaihBelum)
    {{-- Wajib isi 7 KAIH dulu — tombol/scanner absen disembunyikan sampai kuesioner hari ini terisi. --}}
    <div class="card p-4 border-l-4 !border-l-amber-500 text-sm text-amber-700 dark:text-amber-300 flex items-center gap-2">
        <i data-lucide="clipboard-list" class="w-4 h-4 flex-shrink-0"></i> Isi kuesioner 7 KAIH dulu sebelum bisa absen hari ini.
    </div>
    @include('kaih._form', ['pertanyaans' => $kaihPertanyaans])
    @else
    {{-- status / kondisi --}}
    <template x-if="!aktif">
        <div class="card p-4 border-l-4 !border-l-amber-500 text-sm text-amber-700 dark:text-amber-300 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4"></i> Absen QR sedang dinonaktifkan oleh admin.</div>
    </template>
    <template x-if="aktif && !schoolLat">
        <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300 flex items-center gap-2"><i data-lucide="map-pin-off" class="w-4 h-4"></i> Lokasi sekolah belum diatur admin. Hubungi admin.</div>
    </template>

    <div x-show="aktif && schoolLat" class="space-y-4">
        {{-- Mode Masuk/Pulang (khusus guru) --}}
        <template x-if="isGuru">
            <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800">
                <button type="button" @click="mode='masuk'" :class="mode==='masuk' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2 rounded-lg text-sm font-bold transition flex items-center justify-center gap-1.5"><i data-lucide="log-in" class="w-4 h-4"></i> Absen Masuk</button>
                <button type="button" @click="mode='pulang'" :class="mode==='pulang' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2 rounded-lg text-sm font-bold transition flex items-center justify-center gap-1.5"><i data-lucide="log-out" class="w-4 h-4"></i> Absen Pulang</button>
            </div>
        </template>

        {{-- Peta lokasi --}}
        <div class="card p-3 space-y-2">
            <div id="absenMap"></div>
            <div class="flex items-center justify-between text-sm px-1">
                <span class="text-slate-500 flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4"></i> <span x-text="status || 'Menunggu lokasi...'"></span></span>
                <button @click="locate()" class="text-xs text-primary font-semibold flex items-center gap-1"><i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Perbarui</button>
            </div>
            <template x-if="dist!==null">
                <div class="flex items-center gap-2 px-1">
                    <span class="badge font-bold" :class="dist<=radius ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300'">
                        <span x-text="dist"></span> m dari sekolah
                    </span>
                    <span class="text-xs text-slate-400">batas <span x-text="radius"></span> m</span>
                </div>
            </template>
        </div>

        {{-- Scanner --}}
        <div x-show="scanning" class="card p-3">
            <div id="reader"></div>
            <button @click="stopScan()" class="w-full mt-3 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal Scan</button>
        </div>

        {{-- Hasil --}}
        <template x-if="result">
            <div class="card p-5 text-center" :class="result.ok ? 'border-l-4 !border-l-emerald-500' : 'border-l-4 !border-l-rose-500'">
                <i :data-lucide="result.ok ? 'check-circle-2' : 'x-circle'" class="w-12 h-12 mx-auto mb-2" :class="result.ok ? 'text-emerald-500' : 'text-rose-500'"></i>
                <p class="font-bold text-slate-800 dark:text-slate-100" x-text="result.message"></p>
                <p x-show="result.ok" class="text-sm text-slate-500 mt-1">Jam <span class="font-mono font-bold" x-text="result.jam"></span></p>
            </div>
        </template>

        {{-- Tombol absen --}}
        <button x-show="!scanning && !(result && result.ok)" @click="startScan()" :disabled="loading" class="btn-primary w-full px-5 py-3.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="loading"></i>
            <i data-lucide="qr-code" class="w-5 h-5" x-show="!loading"></i>
            <span x-text="loading ? 'Memproses...' : (isGuru && mode==='pulang' ? 'Scan QR — Absen Pulang' : 'Scan QR — Absen' + (isGuru ? ' Masuk' : ''))"></span>
        </button>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
function qrAbsen(cfg){
    return {
        schoolLat: cfg.lat ? parseFloat(cfg.lat) : null,
        schoolLng: cfg.lng ? parseFloat(cfg.lng) : null,
        radius: cfg.radius, aktif: cfg.aktif, isGuru: cfg.isGuru || false, mode:'masuk',
        lat:null, lng:null, dist:null, status:'', loading:false, scanning:false, result:null,
        map:null, uMarker:null, scanner:null,

        init(){
            // Peta dasar (lokasi sekolah + area) langsung ditampilkan tanpa perlu izin lokasi.
            // Lokasi PENGGUNA baru diminta saat menekan "Perbarui"/Scan (dipicu klik langsung
            // supaya prompt izin muncul & tidak keburu ditolak sebelum pengguna siap).
            this.$nextTick(()=>{ lucide.createIcons(); this.ensureMap(); });
            try { if('speechSynthesis' in window) speechSynthesis.getVoices(); } catch(e){}  // picu load voice
            if(this.aktif && this.schoolLat) this.status = 'Tekan "Perbarui" untuk menampilkan posisi Anda di peta.';
        },

        // suara sambutan: masuk → "Selamat datang, nama", pulang → "Terima kasih, nama"
        speak(label, nama){
            try {
                if(!('speechSynthesis' in window)) return;
                const teks = (label === 'Pulang' ? 'Terima kasih, ' : 'Selamat datang, ') + (nama || '');
                const u = new SpeechSynthesisUtterance(teks);
                u.lang = 'id-ID'; u.rate = 0.95; u.pitch = 1;
                const id = speechSynthesis.getVoices().find(v => v.lang && v.lang.toLowerCase().startsWith('id'));
                if(id) u.voice = id;
                speechSynthesis.cancel();
                speechSynthesis.speak(u);
            } catch(e){}
        },
        // buka izin audio TTS lewat gesture (tap tombol)
        primeSpeech(){
            try { if('speechSynthesis' in window){ speechSynthesis.cancel(); speechSynthesis.speak(new SpeechSynthesisUtterance(' ')); } } catch(e){}
        },

        haversine(la1,ln1,la2,ln2){
            const R=6371000, dLa=(la2-la1)*Math.PI/180, dLn=(ln2-ln1)*Math.PI/180;
            const a=Math.sin(dLa/2)**2 + Math.cos(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.sin(dLn/2)**2;
            return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
        },
        // Ubah kode error lokasi jadi kalimat jelas + langkah yang bisa langsung dilakukan pengguna.
        pesanLokasiGagal(err){
            // Penyebab paling umum "izin ditolak" padahal izin sudah aktif: halaman dibuka lewat
            // http:// (bukan https). Browser hanya mengizinkan lokasi di alamat aman (https) / localhost.
            if(typeof window.isSecureContext !== 'undefined' && !window.isSecureContext)
                return 'Lokasi hanya bisa dibaca lewat alamat aman (diawali https://). Buka aplikasi memakai alamat https, bukan http.';
            if(err && err.code===1) return 'Izin lokasi ditolak. Buka pengaturan izin — ikon gembok di address bar (di HP: izin Lokasi untuk aplikasi/browser ini) — aktifkan Lokasi, lalu tekan Perbarui.';
            if(err && err.code===2) return 'Lokasi belum ditemukan. Pastikan GPS/Layanan Lokasi menyala, lalu tekan Perbarui.';
            if(err && err.code===3) return 'Membaca lokasi terlalu lama. Pindah ke tempat lebih terbuka, lalu tekan Perbarui.';
            return 'Lokasi gagal dibaca. Tekan Perbarui untuk mencoba lagi.';
        },
        locate(){
            this.status='Sedang membaca lokasi Anda...';
            if(!navigator.geolocation){ this.status='Perangkat ini tidak mendukung deteksi lokasi. Coba buka lewat HP atau browser lain.'; return; }
            // Cegat lebih dulu bila konteks tidak aman → beri alasan sebenarnya, bukan "izin ditolak".
            if(typeof window.isSecureContext !== 'undefined' && !window.isSecureContext){ this.status = this.pesanLokasiGagal(null); return; }
            const onOk = p=>{
                this.lat=p.coords.latitude; this.lng=p.coords.longitude;
                this.dist=Math.round(this.haversine(this.schoolLat,this.schoolLng,this.lat,this.lng));
                this.status = this.dist<=this.radius ? 'Anda berada di area sekolah — siap absen.' : 'Anda berada di luar area sekolah.';
                this.$nextTick(()=> this.renderMap());
            };
            navigator.geolocation.getCurrentPosition(onOk, err=>{
                // Kalau gagal karena timeout (akurasi tinggi butuh sinyal GPS kuat), coba sekali lagi
                // dengan akurasi biasa — lebih cepat & sering berhasil di dalam ruangan / pakai WiFi.
                if(err && err.code===3){
                    navigator.geolocation.getCurrentPosition(onOk, e2=>{ this.status = this.pesanLokasiGagal(e2); },
                        { enableHighAccuracy:false, timeout:15000, maximumAge:60000 });
                    return;
                }
                this.status = this.pesanLokasiGagal(err);
            }, { enableHighAccuracy:true, timeout:12000, maximumAge:0 });
        },
        // Bangun peta dasar: penanda sekolah + lingkaran area radius. TIDAK butuh izin lokasi,
        // jadi peta tetap tampil walau lokasi pengguna belum/ tidak bisa dibaca.
        ensureMap(){
            if(this.map || !this.schoolLat) return;
            const elMap = document.getElementById('absenMap');
            if(!elMap) return;
            this.map = L.map('absenMap').setView([this.schoolLat, this.schoolLng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19, attribution:'&copy; OpenStreetMap' }).addTo(this.map);
            L.marker([this.schoolLat,this.schoolLng]).addTo(this.map).bindPopup('Sekolah');
            L.circle([this.schoolLat,this.schoolLng],{ radius:this.radius, color:'#10b981', weight:2, fillColor:'#10b981', fillOpacity:0.12 }).addTo(this.map);
            try { new ResizeObserver(()=> this.map && this.map.invalidateSize()).observe(elMap); } catch(e){}
            [100,400,900].forEach(t=> setTimeout(()=> this.map && this.map.invalidateSize(), t));
        },
        // Tandai posisi pengguna di peta (dipanggil setelah lokasi berhasil dibaca).
        renderMap(){
            this.ensureMap();
            if(!this.map || this.lat===null) return;
            if(this.uMarker) this.map.removeLayer(this.uMarker);
            this.uMarker = L.circleMarker([this.lat,this.lng],{ radius:7, color:'#fff', weight:2, fillColor:'#3b82f6', fillOpacity:1 }).addTo(this.map).bindPopup('Lokasi Anda');
            this.map.setView([this.lat,this.lng], 17);
            [100,400,900].forEach(t=> setTimeout(()=> this.map && this.map.invalidateSize(), t));
        },
        startScan(){
            this.primeSpeech();   // buka izin suara (dipicu tap tombol)
            if(!this.lat){ this.locate(); showToast('Mengambil lokasi dulu, coba lagi sebentar','info'); return; }
            this.result=null; this.scanning=true;
            this.$nextTick(()=>{
                this.scanner = new Html5Qrcode('reader');
                this.scanner.start({ facingMode:'environment' }, { fps:10, qrbox:240 },
                    (text)=> this.onScan(text), ()=>{}).catch(e=>{ this.scanning=false; showToast('Tak bisa buka kamera: '+e,'error'); });
            });
        },
        stopScan(){
            if(this.scanner){ this.scanner.stop().then(()=>{ try{this.scanner.clear();}catch(e){} }).catch(()=>{}); this.scanner=null; }
            this.scanning=false;
        },
        onScan(token){
            this.stopScan(); this.loading=true; this.status='Memverifikasi lokasi & QR...';
            navigator.geolocation.getCurrentPosition(async p=>{
                this.lat=p.coords.latitude; this.lng=p.coords.longitude;
                try {
                    const res = await fetch('{{ route('absen.qr.mark') }}', {
                        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                        body: JSON.stringify({ token, lat:this.lat, lng:this.lng, mode:this.mode })
                    });
                    const d = await res.json();
                    this.result = d; this.dist = d.jarak ?? this.dist;
                    this.renderMap();
                    if(d.ok){ showToast(d.message); this.speak(d.label, d.nama); } else showToast(d.message,'error');
                } catch { showToast('Gagal menghubungi server','error'); }
                this.loading=false;
                this.$nextTick(()=> lucide.createIcons());
            }, (err)=>{ this.loading=false; showToast(this.pesanLokasiGagal(err),'error'); }, { enableHighAccuracy:true, timeout:12000 });
        }
    }
}
</script>
@endpush
@endsection
