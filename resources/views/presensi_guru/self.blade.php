@extends('layouts.app')
@section('title', 'Presensi Saya')

@section('content')
@php $hasFace = !empty($guru->face_descriptor); @endphp
<div class="max-w-4xl mx-auto space-y-5"
     x-data="{{ $bolehQr ? 'izinPulangQr(' . json_encode(['lat' => $qrLat, 'lng' => $qrLng, 'radius' => $qrRadius]) . ')' : 'izinPulang(' . json_encode($hasFace) . ')' }}">
    <div>
        <h1 class="page-title">Presensi Saya</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Riwayat jam masuk &amp; pulang, form keterlambatan, dan izin pulang awal.</p>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 p-3 text-sm text-emerald-700 dark:text-emerald-300 font-semibold">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 p-3 text-sm text-rose-700 dark:text-rose-300 font-semibold">{{ session('error') }}</div>
    @endif

    {{-- ===== Status hari ini ===== --}}
    <div class="card p-5 flex flex-wrap items-center gap-4">
        <div class="w-11 h-11 rounded-2xl grid place-items-center text-white shadow flex-shrink-0" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
            <i data-lucide="clock" class="w-5 h-5"></i>
        </div>
        <div class="flex-1 min-w-[200px]">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Hari ini &bull; {{ now()->isoFormat('dddd, D MMMM Y') }}</p>
            @if($today)
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-1">
                <span class="text-sm"><span class="text-slate-400">Masuk:</span> <span class="font-bold text-slate-700 dark:text-slate-200">{{ $today->jam_masuk ? substr($today->jam_masuk,0,5) : '—' }}</span></span>
                <span class="text-sm"><span class="text-slate-400">Pulang:</span> <span class="font-bold text-slate-700 dark:text-slate-200">{{ $today->jam_pulang ? substr($today->jam_pulang,0,5) : '—' }}</span></span>
                @if($today->status === 'hadir' && $today->terlambat($batas))
                <span class="badge bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300 font-bold">Terlambat</span>
                @endif
            </div>
            @else
            <p class="text-sm text-slate-400 mt-1">Belum ada catatan presensi hari ini.</p>
            @endif
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        {{-- ===== Form Keterlambatan ===== --}}
        @if($today && $today->status === 'hadir' && $today->terlambat($batas))
        <div class="card p-5 space-y-3">
            <div class="flex items-center gap-2">
                <i data-lucide="alarm-clock" class="w-4 h-4 text-rose-500"></i>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Form Keterlambatan</h2>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Anda tercatat masuk pukul {{ substr($today->jam_masuk,0,5) }} (batas {{ $batas }}). Jelaskan alasan keterlambatan Anda.</p>
            <form method="POST" action="{{ route('presensi-guru.keterlambatan.store') }}" class="space-y-2">
                @csrf
                <textarea name="keterangan" rows="3" class="form-input" placeholder="Contoh: Macet di jalan karena hujan deras." required>{{ old('keterangan') }}</textarea>
                <button type="submit" class="btn-primary w-full px-4 py-2.5 rounded-xl text-sm font-bold">Kirim Keterangan</button>
            </form>
        </div>
        @endif

        {{-- ===== Izin Pulang Awal ===== --}}
        @if($today && $today->jam_masuk && !$today->jam_pulang)
        <div class="card p-5 space-y-3">
            <div class="flex items-center gap-2">
                <i data-lucide="door-open" class="w-4 h-4 text-amber-500"></i>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Izin Pulang Awal</h2>
            </div>

            @if(!empty($belumAgenda))
            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 p-2.5 text-xs text-amber-700 dark:text-amber-300">
                Catatan: {{ count($belumAgenda) }} jam mengajar hari ini belum diisi agendanya ({{ implode(', ', array_slice($belumAgenda, 0, 3)) }}{{ count($belumAgenda) > 3 ? ', ...' : '' }}). Mohon isi begitu memungkinkan.
            </div>
            @endif

            @if($bolehQr)
                {{-- ===== Jalur QR (metode absensi aktif = Barcode/QR) ===== --}}
                @if(!$qrLat || !$qrLng)
                <p class="text-sm text-slate-500 dark:text-slate-400">Lokasi sekolah belum diatur admin, jadi izin pulang via QR belum bisa dipakai. Hubungi admin.</p>
                @else
                <template x-if="!scannedToken">
                    <div class="space-y-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Pastikan Anda berada di area sekolah, lalu pindai QR absensi untuk mencatat jam pulang.</p>
                        <div class="flex items-center justify-between text-sm px-1">
                            <span class="text-slate-500 flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4"></i> <span x-text="status"></span></span>
                            <button type="button" @click="locate()" class="text-xs text-primary font-semibold flex items-center gap-1"><i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Perbarui</button>
                        </div>
                        <div x-show="scanning" x-cloak class="space-y-2">
                            <div id="izinReader" class="rounded-2xl overflow-hidden"></div>
                            <button type="button" @click="stopScan()" class="w-full py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal Scan</button>
                        </div>
                        <button x-show="!scanning" type="button" @click="startScan()" class="btn-primary w-full px-4 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                            <i data-lucide="qr-code" class="w-4 h-4"></i> Pindai QR — Izin Pulang
                        </button>
                        <p class="text-center text-sm" :class="msgErr ? 'text-rose-500' : 'text-slate-500'" x-show="msg" x-text="msg"></p>
                    </div>
                </template>

                <template x-if="scannedToken">
                    <div class="space-y-2">
                        <div class="flex items-center gap-1.5 text-emerald-600 text-sm font-semibold"><i data-lucide="check-circle-2" class="w-4 h-4"></i> QR terverifikasi</div>
                        <form @submit.prevent="submitIzin()" class="space-y-2">
                            <textarea x-model="alasan" rows="3" class="form-input" placeholder="Alasan izin pulang awal (wajib diisi)" required></textarea>
                            <button type="submit" :disabled="sending || !alasan.trim()" class="btn-primary w-full px-4 py-2.5 rounded-xl text-sm font-bold disabled:opacity-50" x-text="sending ? 'Menyimpan...' : 'Catat Pulang Sekarang'"></button>
                        </form>
                        <p class="text-center text-sm text-rose-500" x-show="msg" x-text="msg"></p>
                    </div>
                </template>
                @endif
            @else
                {{-- ===== Jalur Wajah (metode absensi aktif = Scan Wajah) ===== --}}
                @if(!$hasFace)
                <p class="text-sm text-slate-500 dark:text-slate-400">Wajah Anda belum terdaftar, jadi izin pulang lewat kamera belum bisa dipakai. <a href="{{ route('face.self', ['ulang' => 1]) }}" class="text-primary font-semibold hover:underline">Daftarkan wajah</a> dulu.</p>
                @else
                <template x-if="!verified">
                    <div class="space-y-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Nyalakan kamera untuk memverifikasi wajah Anda sebelum mencatat jam pulang.</p>
                        <div class="relative rounded-2xl overflow-hidden bg-slate-900 aspect-video">
                            <video x-ref="video" autoplay muted playsinline class="w-full h-full object-cover" :class="streaming ? '' : 'opacity-0'"></video>
                            <div x-show="!streaming" class="absolute inset-0 grid place-items-center text-slate-300 text-sm px-4 text-center">
                                <div>
                                    <i data-lucide="loader-2" class="w-7 h-7 mx-auto animate-spin mb-2" x-show="loading"></i>
                                    <i data-lucide="camera" class="w-7 h-7 mx-auto mb-2 opacity-50" x-show="!loading"></i>
                                    <p x-text="status"></p>
                                </div>
                            </div>
                        </div>
                        <p class="text-center text-sm" :class="msgErr ? 'text-rose-500' : 'text-slate-500'" x-text="msg"></p>
                        <button x-show="!streaming" @click="openCam()" :disabled="loading" type="button" class="btn-primary w-full px-4 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                            <i data-lucide="video" class="w-4 h-4"></i> Nyalakan Kamera
                        </button>
                        <button x-show="streaming" @click="verify()" :disabled="verifying" type="button" class="btn-primary w-full px-4 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="verifying"></i>
                            <i data-lucide="scan-face" class="w-4 h-4" x-show="!verifying"></i>
                            <span x-text="verifying ? 'Memverifikasi...' : 'Verifikasi Wajah'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="verified">
                    <div class="space-y-2">
                        <div class="flex items-center gap-1.5 text-emerald-600 text-sm font-semibold"><i data-lucide="check-circle-2" class="w-4 h-4"></i> Wajah terverifikasi</div>
                        <form @submit.prevent="submitIzin()" class="space-y-2">
                            <textarea x-model="alasan" rows="3" class="form-input" placeholder="Alasan izin pulang awal (wajib diisi)" required></textarea>
                            <button type="submit" :disabled="sending || !alasan.trim()" class="btn-primary w-full px-4 py-2.5 rounded-xl text-sm font-bold disabled:opacity-50" x-text="sending ? 'Menyimpan...' : 'Catat Pulang Sekarang'"></button>
                        </form>
                    </div>
                </template>
                @endif
            @endif
        </div>
        @endif
    </div>

    {{-- ===== Riwayat ===== --}}
    <div class="card overflow-hidden">
        <form method="GET" action="{{ route('presensi-guru.self') }}" class="p-4 flex flex-wrap gap-3 items-end border-b border-slate-100 dark:border-slate-700">
            <div class="min-w-36">
                <label class="form-label">Dari tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}" class="form-input" onchange="this.form.submit()">
            </div>
            <div class="min-w-36">
                <label class="form-label">Sampai tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="form-input" onchange="this.form.submit()">
            </div>
        </form>

        @if($riwayat->isEmpty())
        <div class="p-12 text-center text-slate-400">
            <i data-lucide="calendar-x" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="font-medium text-sm">Tidak ada catatan presensi pada rentang ini.</p>
        </div>
        @else
        <div class="table-responsive overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-center">Jam Masuk</th>
                        <th class="text-center">Jam Pulang</th>
                        <th class="text-center">Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($riwayat as $r)
                    <tr>
                        <td class="font-medium text-slate-700 dark:text-slate-200">{{ $r->tanggal->isoFormat('D MMM Y') }}</td>
                        <td class="text-center">
                            {{ $r->jam_masuk ? substr($r->jam_masuk,0,5) : '—' }}
                            @if($r->status === 'hadir' && $r->terlambat($batas))
                            <span class="badge bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300 text-[10px] ml-1">Telat</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $r->jam_pulang ? substr($r->jam_pulang,0,5) : '—' }}</td>
                        <td class="text-center">
                            @php $statusColor = ['hadir'=>'emerald','izin'=>'blue','sakit'=>'amber','alpa'=>'rose'][$r->status] ?? 'slate'; @endphp
                            <span class="badge bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-900 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-300 capitalize">{{ $r->status }}</span>
                        </td>
                        <td class="text-slate-500 dark:text-slate-400 text-xs max-w-xs truncate" title="{{ $r->keterangan }}">{{ $r->keterangan ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@push('scripts')
@if($bolehQr)
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="{{ asset('js/geo-location.js') }}"></script>
<script>
function izinPulangQr(cfg){
    return {
        schoolLat: cfg.lat ? parseFloat(cfg.lat) : null,
        schoolLng: cfg.lng ? parseFloat(cfg.lng) : null,
        radius: cfg.radius,
        softTolerance: (window.SimsGeo && SimsGeo.defaults.softToleranceM) || 50,
        lat:null, lng:null, accuracy:null, dist:null, distMeters:null, status:'', locating:false, scanning:false,
        scannedToken:null, alasan:'', sending:false, msg:'', msgErr:false,
        scanner:null,

        get dalamArea(){
            if(this.distMeters===null) return false;
            return SimsGeo.withinRadius(this.distMeters, this.radius);
        },

        init(){
            this.status = this.schoolLat ? 'Tekan "Perbarui" untuk membaca lokasi Anda.' : 'Lokasi sekolah belum diatur admin.';
        },
        haversine(la1,ln1,la2,ln2){
            const R=6371000, dLa=(la2-la1)*Math.PI/180, dLn=(ln2-ln1)*Math.PI/180;
            const a=Math.sin(dLa/2)**2 + Math.cos(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.sin(dLn/2)**2;
            return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
        },
        applyFix(fix){
            this.lat = fix.lat; this.lng = fix.lng;
            this.accuracy = (typeof fix.accuracy === 'number' && isFinite(fix.accuracy)) ? fix.accuracy : null;
            this.distMeters = this.haversine(this.schoolLat, this.schoolLng, this.lat, this.lng);
            this.dist = Math.round(this.distMeters);
            const accTxt = this.accuracy!==null ? (' (±'+Math.round(this.accuracy)+' m)') : '';
            if(!SimsGeo.accuracyAcceptable(this.accuracy)){
                this.status = 'Akurasi GPS masih rendah'+accTxt+'. Pindah ke tempat terbuka, lalu Perbarui lagi.';
            } else if(this.dalamArea){
                this.status = 'Anda berada di area sekolah — siap pindai QR.'+accTxt;
            } else {
                this.status = 'Anda berada '+this.dist+' m dari sekolah (batas '+(this.radius + this.softTolerance)+' m).'+accTxt;
            }
        },
        async locate(){
            if(!this.schoolLat || this.locating || this.sending) return;
            this.locating = true;
            this.status = 'Sedang membaca lokasi (GPS sedang dipanaskan)…';
            try {
                const fix = await SimsGeo.getBestLocation({
                    watchMs: 10000,
                    targetAccuracy: 75,
                    onProgress: (msg)=> { this.status = msg; },
                });
                this.applyFix(fix);
            } catch(err){
                this.status = (err && err.message) || SimsGeo.pesanGagal(err);
            }
            this.locating = false;
        },
        startScan(){
            if(this.locating || this.sending) return;
            if(!this.lat){ this.locate(); showToast('Mengambil lokasi dulu, coba lagi sebentar','info'); return; }
            if(!SimsGeo.accuracyAcceptable(this.accuracy)){
                showToast('Akurasi GPS masih rendah. Perbarui lokasi di tempat lebih terbuka dulu.','error'); return;
            }
            if(!this.dalamArea){
                showToast('Anda di luar area sekolah. Mendekat ke lokasi sekolah dulu.','error'); return;
            }
            this.msg=''; this.msgErr=false; this.scanning=true;
            this.$nextTick(()=>{
                this.scanner = new Html5Qrcode('izinReader');
                this.scanner.start({ facingMode:'environment' }, { fps:10, qrbox:220 },
                    (text)=> this.onScan(text), ()=>{}).catch(e=>{ this.scanning=false; showToast('Tak bisa buka kamera: '+e,'error'); });
            });
        },
        stopScan(){
            if(this.scanner){ this.scanner.stop().then(()=>{ try{this.scanner.clear();}catch(e){} }).catch(()=>{}); this.scanner=null; }
            this.scanning=false;
        },
        onScan(token){
            if(this.sending) return;
            this.stopScan();
            this.scannedToken = token;
        },
        async submitIzin(){
            if(!this.alasan.trim() || !this.scannedToken || this.sending) return;
            this.sending=true; this.msgErr=false; this.msg='Menyempurnakan GPS…';
            try {
                // Fresh GPS saat submit — jangan pakai koordinat lama dari locate().
                const fix = await SimsGeo.getBestLocation({
                    watchMs: 8000,
                    targetAccuracy: 75,
                    onProgress: (msg)=> { this.msg = msg; this.msgErr = false; },
                });
                this.applyFix(fix);
                if(!SimsGeo.accuracyAcceptable(this.accuracy)){
                    this.msg = 'Akurasi GPS terlalu rendah (±'+Math.round(this.accuracy)+' m). Coba di tempat lebih terbuka.';
                    this.msgErr = true;
                    showToast(this.msg, 'error');
                    this.sending=false;
                    return;
                }
                if(!this.dalamArea){
                    this.msg = 'Anda di luar area sekolah (batas '+(this.radius + this.softTolerance)+' m).';
                    this.msgErr = true;
                    showToast(this.msg, 'error');
                    this.sending=false;
                    return;
                }
                const res = await fetch('{{ route('presensi-guru.izinPulang.qrStore') }}', {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({
                        token: this.scannedToken,
                        lat: this.lat,
                        lng: this.lng,
                        accuracy: this.accuracy,
                        alasan: this.alasan,
                    })
                });
                const d = await res.json();
                if(res.ok && d.ok){
                    showToast('Absen pulang tercatat pukul ' + d.jam + '.');
                    setTimeout(()=> window.location.reload(), 800);
                    return;
                }
                this.msg = d.message || 'Gagal mencatat izin pulang.'; this.msgErr = true;
                this.scannedToken = null;
                showToast(this.msg, 'error');
            } catch(e){
                this.msg = (e && e.message) || 'Gagal menghubungi server';
                this.msgErr = true;
                showToast(this.msg, 'error');
            }
            this.sending=false;
        }
    }
}
</script>
@else
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/human/dist/human.js"></script>
<script>
let humanIzin=null, humanIzinReady=false;
async function loadHumanIzin(){
    if(humanIzinReady) return humanIzin;
    const HumanLib = window.Human?.Human || window.Human?.default || window.Human;
    const backend = (typeof navigator!=='undefined' && navigator.gpu) ? 'webgpu' : 'webgl';
    humanIzin = new HumanLib({
        modelBasePath:'https://vladmandic.github.io/human-models/models/',
        backend: backend, cacheSensitivity: 0, warmup:'none',
        face:{ enabled:true, detector:{ maxDetected:1, minConfidence:0.45 }, mesh:{enabled:true}, iris:{enabled:false},
               description:{enabled:true}, emotion:{enabled:false}, antispoof:{enabled:false}, liveness:{enabled:false} },
        body:{enabled:false}, hand:{enabled:false}, object:{enabled:false}, gesture:{enabled:false},
        filter:{enabled:false}, segmentation:{enabled:false},
    });
    await humanIzin.load();
    humanIzinReady = true;
    return humanIzin;
}
function faceSimIzin(a, b){
    if(humanIzin && humanIzin.match && typeof humanIzin.match.similarity==='function'){ try { return humanIzin.match.similarity(a, b); } catch(e){} }
    let dot=0, na=0, nb=0; const n=Math.min(a.length,b.length);
    for(let i=0;i<n;i++){ dot+=a[i]*b[i]; na+=a[i]*a[i]; nb+=b[i]*b[i]; }
    return dot / (Math.sqrt(na*nb) + 1e-8);
}
function normalizeDescIzin(desc){
    if(!Array.isArray(desc)) return [];
    if(desc.length && typeof desc[0] === 'number') return [desc.map(Number)];
    return desc.filter(v => Array.isArray(v) && v.length >= 64).map(v => v.map(Number));
}

function izinPulang(hasFace){
    return {
        hasFace,
        loading:false, streaming:false, verifying:false, verified:false, sending:false,
        status:'Klik "Nyalakan Kamera" untuk memulai', msg:'', msgErr:false,
        alasan:'', stream:null,
        myDescriptors: normalizeDescIzin(@json($guru->face_descriptor ?? [])),
        threshold:0.66, confidentThreshold:0.80,

        async openCam(){
            this.loading=true; this.status='Mengaktifkan kamera & memuat model AI...';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:1280}, height:{ideal:720} } });
                this.$refs.video.srcObject = this.stream;
                this.streaming=true;
                await loadHumanIzin();
                this.loading=false;
                this.msg='Posisikan wajah Anda di tengah kamera, lalu klik Verifikasi Wajah.';
            } catch(e){
                this.loading=false;
                this.status='Gagal: ' + (e.name==='NotAllowedError' ? 'akses kamera ditolak' : e.message);
            }
        },
        async verify(){
            this.verifying=true; this.msg='Mendeteksi wajah...'; this.msgErr=false;
            try {
                const res = await humanIzin.detect(this.$refs.video);
                const face = (res.face||[])[0];
                if(!face || !face.embedding){
                    this.msg='Wajah tidak terdeteksi. Pastikan wajah masuk ke bingkai kamera.'; this.msgErr=true; this.verifying=false; return;
                }
                const sims = this.myDescriptors.map(d => faceSimIzin(Array.from(face.embedding), d)).sort((a,b)=>b-a);
                const top1 = sims[0] || 0, top2 = sims[1] || 0;
                const score = sims.length >= 2 ? (top1*0.58 + top2*0.42) : top1;
                if(score >= this.threshold){
                    this.verified = true;
                    if(this.stream){ this.stream.getTracks().forEach(t=>t.stop()); }
                } else {
                    this.msg='Wajah tidak cocok dengan data terdaftar. Coba lagi dengan pencahayaan lebih baik.'; this.msgErr=true;
                }
            } catch(e){ this.msg='Error: '+e.message; this.msgErr=true; }
            this.verifying=false;
        },
        async submitIzin(){
            if(!this.alasan.trim()) return;
            this.sending=true;
            try {
                const res = await fetch('{{ route('presensi-guru.izinPulang.store') }}', {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ alasan: this.alasan })
                });
                const d = await res.json();
                if(res.ok && d.success){
                    showToast('Absen pulang tercatat pukul ' + d.jam + '.');
                    setTimeout(()=> window.location.reload(), 800);
                    return;
                }
                showToast(d.message || 'Gagal mencatat izin pulang.', 'error');
            } catch(e){ showToast('Gagal menghubungi server', 'error'); }
            this.sending=false;
        }
    }
}
</script>
@endif
@endpush
@endsection
