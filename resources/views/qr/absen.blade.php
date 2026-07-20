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
<div class="max-w-lg mx-auto space-y-4" x-data="qrAbsen({
    lat:@js($lat),
    lng:@js($lng),
    radius:{{ $radius }},
    points:@js($points ?? []),
    rushBonus:{{ (float) ($rushBonus ?? 0) }},
    aktif:{{ $aktif ? 'true':'false' }},
    isGuru:{{ ($isGuru ?? false) ? 'true':'false' }}
})" x-init="init()">

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
    <template x-if="aktif && !schoolLat && !(points && points.length)">
        <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300 flex items-center gap-2"><i data-lucide="map-pin-off" class="w-4 h-4"></i> Lokasi sekolah belum diatur admin. Hubungi admin.</div>
    </template>

    <div x-show="aktif && (schoolLat || (points && points.length))" class="space-y-4">
        {{-- Mode Masuk/Pulang (khusus guru) --}}
        <template x-if="isGuru">
            <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800">
                <button type="button" @click="mode='masuk'" :class="mode==='masuk' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2 rounded-lg text-sm font-bold transition flex items-center justify-center gap-1.5"><i data-lucide="log-in" class="w-4 h-4"></i> Absen Masuk</button>
                <button type="button" @click="mode='pulang'" :class="mode==='pulang' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-500'" class="flex-1 py-2 rounded-lg text-sm font-bold transition flex items-center justify-center gap-1.5"><i data-lucide="log-out" class="w-4 h-4"></i> Absen Pulang</button>
            </div>
        </template>

        {{-- Peta lokasi --}}
        <div class="card p-3 space-y-2">
            <div class="relative rounded-2xl overflow-hidden">
                <div id="absenMap"></div>
                <div class="absolute top-3 right-3 z-[1000] flex rounded-lg overflow-hidden shadow-md border border-white/40 bg-white/95 dark:bg-slate-800/95 text-xs font-bold">
                    <button type="button" @click="setBase('street')"
                        :class="baseMode==='street' ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-300'"
                        class="px-2.5 py-1.5 transition">Peta</button>
                    <button type="button" @click="setBase('satellite')"
                        :class="baseMode==='satellite' ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-300'"
                        class="px-2.5 py-1.5 transition">Satelit</button>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm px-1">
                <span class="text-slate-500 flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4"></i> <span x-text="status || 'Menunggu lokasi...'"></span></span>
                <button @click="locate()" :disabled="locating || loading || scanning" class="text-xs text-primary font-semibold flex items-center gap-1 disabled:opacity-50"><i data-lucide="refresh-cw" class="w-3.5 h-3.5" :class="locating && 'animate-spin'"></i> Perbarui</button>
            </div>
            <template x-if="dist!==null">
                <div class="flex flex-wrap items-center gap-2 px-1">
                    <span class="badge font-bold" :class="dalamArea ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300'">
                        <span x-text="dist"></span> m dari <span x-text="nearestLabel || 'sekolah'"></span>
                    </span>
                    <span class="text-xs text-slate-400">batas <span x-text="Math.round(effectiveBound)"></span> m</span>
                    <span x-show="rushBonus>0" class="text-xs text-amber-600 font-semibold">+jam sibuk <span x-text="Math.round(rushBonus)"></span> m</span>
                    <span x-show="accuracy!==null" class="text-xs text-slate-400">akurasi GPS ±<span x-text="Math.round(accuracy)"></span> m</span>
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
        <button x-show="!scanning && !(result && result.ok)" @click="startScan()" :disabled="loading || locating" class="btn-primary w-full px-5 py-3.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="loading || locating"></i>
            <i data-lucide="qr-code" class="w-5 h-5" x-show="!loading && !locating"></i>
            <span x-text="loading ? 'Memproses...' : (locating ? 'Membaca GPS...' : (isGuru && mode==='pulang' ? 'Scan QR — Absen Pulang' : 'Scan QR — Absen' + (isGuru ? ' Masuk' : '')))"></span>
        </button>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="{{ asset('js/geo-location.js') }}"></script>
<script src="{{ asset('js/geo-map-layers.js') }}"></script>
<script>
function qrAbsen(cfg){
    return {
        schoolLat: cfg.lat ? parseFloat(cfg.lat) : null,
        schoolLng: cfg.lng ? parseFloat(cfg.lng) : null,
        radius: cfg.radius, aktif: cfg.aktif, isGuru: cfg.isGuru || false, mode:'masuk',
        points: Array.isArray(cfg.points) ? cfg.points : [],
        rushBonus: cfg.rushBonus || 0,
        softTolerance: (window.SimsGeo && SimsGeo.defaults.softToleranceM) || 50,
        lat:null, lng:null, accuracy:null, dist:null, distMeters:null, nearestLabel:null, nearestRadius:null,
        status:'', locating:false, loading:false, scanning:false, result:null,
        map:null, uMarker:null, uAccuracy:null, scanner:null,
        baseMode:'street', baseCtrl:null,

        get effectiveBound(){
            const r = this.nearestRadius != null ? this.nearestRadius : this.radius;
            return SimsGeo.effectiveRadius(r, this.softTolerance, this.rushBonus);
        },
        get dalamArea(){
            if(this.distMeters===null) return false;
            const r = this.nearestRadius != null ? this.nearestRadius : this.radius;
            return SimsGeo.withinRadius(this.distMeters, r, this.softTolerance, this.rushBonus);
        },

        init(){
            this.$nextTick(()=>{ lucide.createIcons(); this.ensureMap(); });
            try { if('speechSynthesis' in window) speechSynthesis.getVoices(); } catch(e){}
            if(this.aktif && (this.schoolLat || (this.points && this.points.length))) this.status = 'Tekan "Perbarui" untuk menampilkan posisi Anda di peta.';
            // Segarkan geofence dari server agar bonus jam sibuk / titik tidak stale di tab lama.
            this.refreshGeoConfig();
            this._geoPoll = setInterval(()=> this.refreshGeoConfig(true), 60000);
            window.addEventListener('beforeunload', ()=> { if(this._geoPoll) clearInterval(this._geoPoll); });
        },

        async refreshGeoConfig(silent){
            try {
                const res = await fetch('{{ route('absen.qr.geoConfig') }}', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if(!res.ok) return;
                const d = await res.json();
                if(!d || !d.ok) return;
                if(Array.isArray(d.points)) this.points = d.points;
                if(d.rush_bonus != null) this.rushBonus = Number(d.rush_bonus) || 0;
                if(d.radius != null) this.radius = Number(d.radius) || this.radius;
                if(d.soft_tolerance != null) this.softTolerance = Number(d.soft_tolerance) || this.softTolerance;
                if(d.lat) this.schoolLat = parseFloat(d.lat);
                if(d.lng) this.schoolLng = parseFloat(d.lng);
                // Rehitung badge bila sudah ada fix GPS.
                if(this.lat != null && this.lng != null){
                    this.applyFix({ lat: this.lat, lng: this.lng, accuracy: this.accuracy });
                }
            } catch(e){
                if(!silent) { /* diam saat poll berkala */ }
            }
        },

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
        primeSpeech(){
            try { if('speechSynthesis' in window){ speechSynthesis.cancel(); speechSynthesis.speak(new SpeechSynthesisUtterance(' ')); } } catch(e){}
        },

        zonePoints(){
            if(this.points && this.points.length) return this.points;
            if(this.schoolLat != null && this.schoolLng != null){
                return [{ label:'Utama', lat:this.schoolLat, lng:this.schoolLng, radius:this.radius }];
            }
            return [];
        },
        applyFix(fix){
            this.lat = fix.lat; this.lng = fix.lng;
            this.accuracy = (typeof fix.accuracy === 'number' && isFinite(fix.accuracy)) ? fix.accuracy : null;
            const match = SimsGeo.nearestMatch(this.lat, this.lng, this.zonePoints(), this.rushBonus);
            if(match){
                this.distMeters = match.dist;
                this.dist = Math.round(match.dist);
                this.nearestLabel = match.label;
                this.nearestRadius = match.radius;
            } else {
                this.distMeters = null; this.dist = null; this.nearestLabel = null; this.nearestRadius = null;
            }
            const accTxt = this.accuracy!==null ? (' (±'+Math.round(this.accuracy)+' m)') : '';
            if(!SimsGeo.accuracyAcceptable(this.accuracy)){
                this.status = 'Akurasi GPS masih rendah'+accTxt+'. Pindah ke tempat terbuka, lalu Perbarui lagi.';
            } else if(this.dalamArea){
                this.status = 'Anda berada di area «'+(this.nearestLabel||'sekolah')+'» — siap absen.'+accTxt;
            } else {
                this.status = 'Anda berada di luar area sekolah.'+accTxt;
            }
            this.$nextTick(()=> this.renderMap());
        },
        async locate(){
            if(this.locating || this.loading || this.scanning) return;
            this.locating = true;
            this.status = 'Sedang membaca lokasi (GPS sedang dipanaskan)…';
            try {
                await this.refreshGeoConfig(true);
                const fix = await SimsGeo.getBestLocation({
                    watchMs: 16000,
                    targetAccuracy: 35,
                    onProgress: (msg)=> { this.status = msg; },
                });
                this.applyFix(fix);
            } catch(err){
                // Invalidate stale fix so badge tidak tetap "siap absen" setelah gagal.
                this.lat = null; this.lng = null; this.accuracy = null;
                this.dist = null; this.distMeters = null;
                this.nearestLabel = null; this.nearestRadius = null;
                this.status = (err && err.message) || SimsGeo.pesanGagal(err);
            }
            this.locating = false;
            this.$nextTick(()=> lucide.createIcons());
        },
        setBase(mode){
            if(!this.baseCtrl) return;
            this.baseCtrl.setMode(mode);
            this.baseMode = this.baseCtrl.mode;
        },
        ensureMap(){
            if(this.map) return;
            const zones = this.zonePoints();
            if(!zones.length) return;
            const elMap = document.getElementById('absenMap');
            if(!elMap) return;
            const center = zones[0];
            this.map = L.map('absenMap').setView([center.lat, center.lng], 16);
            this.baseCtrl = SimsMapLayers.attach(this.map, this.baseMode);
            this.baseMode = this.baseCtrl.mode;
            zones.forEach((z, i)=>{
                const color = i === 0 ? '#10b981' : '#f59e0b';
            L.marker([z.lat, z.lng]).addTo(this.map).bindPopup(SimsGeo.escapeHtml(z.label || ('Titik '+(i+1))));
                L.circle([z.lat, z.lng],{ radius:z.radius, color, weight:2, fillColor:color, fillOpacity:0.10 }).addTo(this.map);
                L.circle([z.lat, z.lng],{ radius:SimsGeo.effectiveRadius(z.radius, this.softTolerance, this.rushBonus), color, weight:1, fillOpacity:0, dashArray:'6 4' }).addTo(this.map);
            });
            try { new ResizeObserver(()=> this.map && this.map.invalidateSize()).observe(elMap); } catch(e){}
            [100,400,900].forEach(t=> setTimeout(()=> this.map && this.map.invalidateSize(), t));
        },
        renderMap(){
            this.ensureMap();
            if(!this.map || this.lat===null) return;
            if(this.uMarker) this.map.removeLayer(this.uMarker);
            if(this.uAccuracy) this.map.removeLayer(this.uAccuracy);
            if(this.accuracy && this.accuracy > 0){
                this.uAccuracy = L.circle([this.lat,this.lng],{
                    radius: this.accuracy, color:'#3b82f6', weight:1,
                    fillColor:'#3b82f6', fillOpacity:0.12, dashArray:'4 4'
                }).addTo(this.map);
            }
            this.uMarker = L.circleMarker([this.lat,this.lng],{ radius:7, color:'#fff', weight:2, fillColor:'#3b82f6', fillOpacity:1 }).addTo(this.map)
                .bindPopup('Lokasi Anda'+(this.accuracy!==null ? ' (±'+Math.round(this.accuracy)+' m)' : ''));
            this.map.setView([this.lat,this.lng], 17);
            [100,400,900].forEach(t=> setTimeout(()=> this.map && this.map.invalidateSize(), t));
        },
        startScan(){
            this.primeSpeech();
            if(this.loading || this.locating) return;
            if(!this.lat){ this.locate(); showToast('Mengambil lokasi dulu, coba lagi sebentar','info'); return; }
            if(!SimsGeo.accuracyAcceptable(this.accuracy)){
                showToast('Akurasi GPS masih rendah. Perbarui lokasi di tempat lebih terbuka dulu.','error'); return;
            }
            if(!this.dalamArea){
                showToast('Anda di luar area sekolah. Mendekat ke lokasi sekolah dulu.','error'); return;
            }
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
        async onScan(token){
            if(this.loading || this.locating) return;
            this.loading=true;
            this.stopScan();
            this.status='Menyempurnakan GPS & memverifikasi QR…';
            try {
                await this.refreshGeoConfig(true);
                const fix = await SimsGeo.getBestLocation({
                    watchMs: 12000,
                    targetAccuracy: 35,
                    onProgress: (msg)=> { this.status = msg; },
                });
                this.applyFix(fix);
                if(!SimsGeo.accuracyAcceptable(this.accuracy)){
                    showToast('Akurasi GPS terlalu rendah (±'+Math.round(this.accuracy)+' m). Coba di tempat lebih terbuka.','error');
                    this.loading=false;
                    return;
                }
                if(!this.dalamArea){
                    showToast('Anda di luar area sekolah (batas '+Math.round(this.effectiveBound)+' m).','error');
                    this.loading=false;
                    return;
                }
                const res = await fetch('{{ route('absen.qr.mark') }}', {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ token, lat:this.lat, lng:this.lng, accuracy:this.accuracy, mode:this.mode })
                });
                const d = await res.json();
                this.result = d; this.dist = d.jarak ?? this.dist;
                if(d.jarak!=null){ this.distMeters = d.jarak; this.dist = Math.round(d.jarak); }
                if(d.accuracy!=null) this.accuracy = d.accuracy;
                if(d.titik) this.nearestLabel = d.titik;
                if(d.radius!=null) this.nearestRadius = d.radius;
                if(d.bonus!=null) this.rushBonus = d.bonus;
                this.renderMap();
                if(d.ok){ showToast(d.message); this.speak(d.label, d.nama); } else showToast(d.message,'error');
            } catch(err){
                showToast((err && err.message) || SimsGeo.pesanGagal(err),'error');
            }
            this.loading=false;
            this.$nextTick(()=> lucide.createIcons());
        }
    }
}
</script>
@endpush
@endsection
