@extends('layouts.app')
@section('title', 'Presensi Scan Wajah Bersama')

@push('styles')
<style>
    /* ===== Mode layar penuh (kiosk) ===== */
    .scan-stage:fullscreen { width:100vw; height:100vh; border-radius:0; background:#0b1220; }
    .scan-stage:fullscreen video { object-fit:contain; }
    .scan-stage:-webkit-full-screen { width:100vw; height:100vh; border-radius:0; }
    .flash-name { animation: flashName 1.6s ease-out forwards; }
    @keyframes flashName { 0%{opacity:0;transform:scale(.8)} 15%{opacity:1;transform:scale(1)} 80%{opacity:1} 100%{opacity:0;transform:scale(1)} }
    .chip-in { animation: chipIn .3s ease-out; }
    @keyframes chipIn { from{opacity:0;transform:translateY(8px) scale(.9)} to{opacity:1;transform:none} }
</style>
@endpush

@section('content')
<div class="space-y-5" x-data="faceScan(@js($payload))" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Presensi Scan Wajah (Siswa & Guru)</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Mode kiosk &mdash; siswa dan guru cukup menghadap kamera. Wajah yang dikenali akan otomatis tercatat <span class="font-semibold text-emerald-600">Hadir</span>.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('absensi.wajah') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Registrasi Wajah Siswa
            </a>
        </div>
    </div>

    @if($siswas->isEmpty() && $gurus->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada siswa atau guru yang mendaftarkan wajah.</p>
        <p class="text-sm mt-1">Daftarkan wajah terlebih dahulu untuk menggunakan fitur absensi scan wajah.</p>
    </div>
    @else
    <div class="grid lg:grid-cols-5 gap-5">
        {{-- Kamera --}}
        <div class="lg:col-span-3 space-y-3">
            <div x-ref="stage" class="scan-stage card overflow-hidden relative bg-slate-900 aspect-video">
                <video x-ref="video" autoplay muted playsinline style="width:100%;height:100%;object-fit:cover" :class="camOn?'':'opacity-0'"></video>
                <canvas x-ref="canvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>

                {{-- placeholder saat kamera mati --}}
                <div x-show="!camOn" class="absolute inset-0 grid place-items-center text-center text-slate-300">
                    <div>
                        <i data-lucide="loader-2" class="w-9 h-9 mx-auto animate-spin mb-2" x-show="loading"></i>
                        <i data-lucide="scan-face" class="w-12 h-12 mx-auto mb-2 opacity-50" x-show="!loading"></i>
                        <p class="text-sm px-6" x-text="status"></p>
                    </div>
                </div>

                {{-- HUD: status (kiri atas) --}}
                <div x-show="camOn && loading" class="absolute top-3 left-3 flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold">
                    <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Memuat model AI...
                </div>
                <div x-show="scanning" class="absolute top-3 left-3 flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Memindai...
                </div>

                {{-- HUD: mode aktif (Masuk/Pulang) --}}
                <div x-show="camOn" class="absolute top-3 left-1/2 -translate-x-1/2 px-3 py-1.5 rounded-full backdrop-blur text-white text-xs font-bold" :class="scanMode==='pulang' ? 'bg-amber-600/85' : 'bg-emerald-600/85'">
                    <span x-text="scanMode==='pulang' ? '🏠 Mode Pulang' : '🚪 Mode Masuk'"></span>
                </div>

                {{-- HUD: counter + tombol layar penuh (kanan atas) --}}
                <div class="absolute top-3 right-3 flex items-center gap-2">
                    <div x-show="camOn" class="px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold flex items-center gap-1">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i> <span x-text="totalHadir"></span>/<span x-text="totalEnrolled"></span> hadir
                    </div>
                    <button @click="toggleFs()" class="p-2 rounded-full bg-black/55 backdrop-blur text-white hover:bg-black/70 transition" :title="fs?'Keluar layar penuh':'Layar penuh'">
                        <i :data-lucide="fs?'minimize-2':'maximize-2'" class="w-4 h-4"></i>
                    </button>
                </div>

                {{-- Flash nama besar saat dikenali --}}
                <template x-if="lastMatch">
                    <div :key="lastMatch.key" class="flash-name absolute inset-x-0 top-1/2 -translate-y-1/2 flex flex-col items-center pointer-events-none">
                        <div class="px-6 py-3 rounded-2xl text-white text-center shadow-2xl" :class="lastMatch.mode==='pulang' ? 'bg-amber-500/90' : 'bg-emerald-500/90'">
                            <div class="flex items-center justify-center gap-2 text-2xl font-extrabold">
                                <i data-lucide="check-circle-2" class="w-7 h-7"></i>
                                <span x-text="lastMatch.nama"></span>
                            </div>
                            <div class="text-sm font-semibold opacity-90">
                                <span x-text="lastMatch.type === 'siswa' ? 'Siswa • Kelas ' + lastMatch.kelas : 'Guru'"></span> &bull;
                                <span x-text="lastMatch.mode==='pulang' ? 'Pulang' : 'Hadir'"></span>
                                <span class="font-mono" x-text="lastMatch.jam"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Strip hadir terakhir (bawah, terlihat di fullscreen) --}}
                <div x-show="camOn && recent.length" class="absolute bottom-3 left-3 right-3 flex gap-2 flex-nowrap overflow-hidden pointer-events-none">
                    <template x-for="r in recent" :key="r.key">
                        <div class="chip-in flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-xs font-semibold whitespace-nowrap flex-shrink-0" :class="r.mode==='pulang' ? 'bg-amber-500/90' : 'bg-emerald-500/90'">
                            <span class="font-mono font-bold bg-black/20 rounded px-1.5 py-0.5" x-text="r.jam"></span>
                            <span x-text="r.nama"></span>
                            <span class="opacity-80" x-text="r.type === 'siswa' ? '(' + r.kelas + ')' : (r.mode==='pulang' ? '(Pulang)' : '(Guru)')"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm text-slate-500">
                    <span class="font-bold text-emerald-600" x-text="totalHadir"></span> hadir /
                    <span x-text="totalEnrolled"></span> terdaftar wajah &bull;
                    {{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMM Y') }}
                </p>
                <div class="flex items-center gap-2 flex-wrap">
                    {{-- Toggle mode Masuk / Pulang (Pulang dilacak utk guru) --}}
                    <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800" title="Mode Pulang dicatat untuk guru">
                        <button @click="scanMode='masuk'" :class="scanMode==='masuk' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1">
                            <i data-lucide="log-in" class="w-3.5 h-3.5"></i> Masuk
                        </button>
                        <button @click="scanMode='pulang'" :class="scanMode==='pulang' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1">
                            <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Pulang
                        </button>
                    </div>
                    <button x-show="!camOn" @click="start()" :disabled="loading" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 disabled:opacity-50">
                        <i data-lucide="play" class="w-4 h-4"></i> Mulai Scan (Layar Penuh)
                    </button>
                    <button x-show="camOn" @click="stop()" class="px-5 py-2.5 rounded-xl text-sm font-bold border border-rose-200 text-rose-600 hover:bg-rose-50 transition flex items-center gap-2">
                        <i data-lucide="square" class="w-4 h-4"></i> Hentikan
                    </button>
                </div>
            </div>
        </div>

        {{-- Daftar hadir (Tabs Siswa & Guru) --}}
        <div class="lg:col-span-2">
            <div class="card flex flex-col h-full" style="max-height:72vh">
                {{-- Tab Navs --}}
                <div class="flex border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 rounded-t-xl overflow-hidden">
                    <button @click="activeTab = 'siswa'" :class="activeTab === 'siswa' ? 'bg-white dark:bg-slate-800 text-primary border-b-2 border-primary font-bold' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="flex-1 py-3 text-center text-sm transition focus:outline-none">
                        Siswa (<span x-text="hadirCountSiswa"></span>/<span x-text="enrolledCountSiswa"></span>)
                    </button>
                    <button @click="activeTab = 'guru'" :class="activeTab === 'guru' ? 'bg-white dark:bg-slate-800 text-primary border-b-2 border-primary font-bold' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="flex-1 py-3 text-center text-sm transition focus:outline-none">
                        Guru (<span x-text="hadirCountGuru"></span>/<span x-text="enrolledCountGuru"></span>)
                    </button>
                </div>

                {{-- Siswa Tab Panel --}}
                <div x-show="activeTab === 'siswa'" class="flex-1 flex flex-col min-h-0">
                    <div class="p-3 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <div class="relative">
                            <input type="text" x-model="siswaSearch" placeholder="Cari siswa..." class="form-input w-full pl-9 py-2 text-sm">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
                        <template x-for="s in filteredSiswa" :key="s.uuid">
                            <div class="flex items-center gap-3 p-2.5 rounded-xl transition"
                                 :class="s.marked ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800'">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0 transition"
                                     :class="s.justMarked ? 'ring-2 ring-emerald-400 scale-110' : ''"
                                     :style="'background:'+(s.jk==='L'?'var(--cp)':'#ec4899')" x-text="s.nama.charAt(0).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate" x-text="s.nama"></p>
                                    <p class="text-xs text-slate-400">Kelas <span x-text="s.kelas"></span> &bull; <span x-text="s.nis"></span></p>
                                </div>
                                <span x-show="s.marked" class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Hadir</span>
                                <span x-show="!s.marked" class="text-xs text-slate-300">—</span>
                            </div>
                        </template>
                        <div x-show="filteredSiswa.length === 0" class="text-center py-8 text-slate-400 text-sm">
                            Tidak ada siswa yang cocok.
                        </div>
                    </div>
                    <div class="p-3 border-t border-slate-100 dark:border-slate-700">
                        <a href="{{ route('absensi.index') }}" class="text-sm text-primary hover:underline flex items-center gap-1 justify-center">
                            <i data-lucide="list-checks" class="w-4 h-4"></i> Lengkapi manual (izin/sakit/alpa)
                        </a>
                    </div>
                </div>

                {{-- Guru Tab Panel --}}
                <div x-show="activeTab === 'guru'" class="flex-1 flex flex-col min-h-0">
                    <div class="p-3 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <div class="relative">
                            <input type="text" x-model="guruSearch" placeholder="Cari guru..." class="form-input w-full pl-9 py-2 text-sm">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
                        <template x-for="g in filteredGuru" :key="g.uuid">
                            <div class="flex items-center gap-3 p-2.5 rounded-xl transition"
                                 :class="g.marked ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800'">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0 transition"
                                     :class="g.justMarked ? 'ring-2 ring-emerald-400 scale-110' : ''"
                                     :style="'background:'+(g.jk==='L'?'var(--cp)':'#ec4899')" x-text="g.nama.charAt(0).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate" x-text="g.nama"></p>
                                    <p class="text-xs text-slate-400" x-text="g.nip || 'Guru'"></p>
                                </div>
                                <span x-show="g.marked" class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Hadir</span>
                                <span x-show="!g.marked" class="text-xs text-slate-300">—</span>
                            </div>
                        </template>
                        <div x-show="filteredGuru.length === 0" class="text-center py-8 text-slate-400 text-sm">
                            Tidak ada guru yang cocok.
                        </div>
                    </div>
                    <div class="p-3 border-t border-slate-100 dark:border-slate-700">
                        <a href="{{ route('presensi-guru.index') }}" class="text-sm text-primary hover:underline flex items-center gap-1 justify-center">
                            <i data-lucide="list-checks" class="w-4 h-4"></i> Lengkapi manual (izin/sakit/alpa)
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/human/dist/human.js"></script>
<script>
// ===== Human (WebGPU) — pengenalan wajah modern, asinkron, UI tak freeze =====
let human=null, humanReady=false, humanBackend='';
async function loadHuman(){
    if(humanReady) return human;
    const HumanLib = window.Human?.Human || window.Human?.default || window.Human;
    const backend = (typeof navigator!=='undefined' && navigator.gpu) ? 'webgpu' : 'webgl';
    human = new HumanLib({
        modelBasePath:'https://vladmandic.github.io/human-models/models/',
        backend: backend, cacheSensitivity: 0, warmup:'none',
        face:{ enabled:true, detector:{ maxDetected:5, minConfidence:0.25 }, mesh:{enabled:true}, iris:{enabled:false},
               description:{enabled:true}, emotion:{enabled:false}, antispoof:{enabled:false}, liveness:{enabled:false} },
        body:{enabled:false}, hand:{enabled:false}, object:{enabled:false}, gesture:{enabled:false},
        filter:{enabled:false}, segmentation:{enabled:false},
    });
    await human.load();
    humanBackend = human.tf.getBackend();
    humanReady = true;
    return human;
}
// kemiripan embedding — pakai Human (terkalibrasi 0..1), fallback cosine
function faceSim(a, b){
    if(human && human.match && typeof human.match.similarity==='function'){ try { return human.match.similarity(a, b); } catch(e){} }
    let dot=0, na=0, nb=0; const n=Math.min(a.length,b.length);
    for(let i=0;i<n;i++){ dot+=a[i]*b[i]; na+=a[i]*a[i]; nb+=b[i]*b[i]; }
    return dot / (Math.sqrt(na*nb) + 1e-8);
}

function faceScan(data){
    return {
        loading:false, camOn:false, scanning:false, busy:false, fs:false,
        status:'Klik "Mulai Scan" untuk mengaktifkan kamera',
        attendees: data.map(s=>({ ...s, marked: s.status==='hadir', justMarked:false, pulangMarked: !!s.pulangDone })),
        enrolled:[], stream:null, timer:null, threshold:0.5,
        recent:[], lastMatch:null, _seq:0, audioCtx:null,
        scanMode:'masuk',
        activeTab: 'siswa',
        siswaSearch: '',
        guruSearch: '',

        get hadirCountSiswa(){ return this.attendees.filter(s=>s.type==='siswa' && s.marked).length; },
        get enrolledCountSiswa(){ return this.attendees.filter(s=>s.type==='siswa' && s.desc && s.desc.length).length; },
        get hadirCountGuru(){ return this.attendees.filter(s=>s.type==='guru' && s.marked).length; },
        get enrolledCountGuru(){ return this.attendees.filter(s=>s.type==='guru' && s.desc && s.desc.length).length; },
        get totalHadir(){ return this.attendees.filter(s=>s.marked).length; },
        get totalEnrolled(){ return this.attendees.filter(s=>s.desc && s.desc.length).length; },

        get filteredSiswa(){
            return this.attendees.filter(s=>s.type==='siswa' && s.nama.toLowerCase().includes(this.siswaSearch.toLowerCase()));
        },
        get filteredGuru(){
            return this.attendees.filter(s=>s.type==='guru' && s.nama.toLowerCase().includes(this.guruSearch.toLowerCase()));
        },

        init(){
            document.addEventListener('fullscreenchange', ()=>{
                this.fs = !!document.fullscreenElement;
                setTimeout(()=> window.lucide && lucide.createIcons(), 60);
            });
        },

        enterFs(){
            const el=this.$refs.stage;
            if(el && el.requestFullscreen && !document.fullscreenElement){ el.requestFullscreen().catch(()=>{}); }
        },
        toggleFs(){
            if(document.fullscreenElement){ document.exitFullscreen?.(); }
            else { this.enterFs(); }
        },
        nowHM(){ const d=new Date(); return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2); },

        // bunyi "ting" sukses absen (Web Audio, tanpa file)
        playDing(){
            try {
                const ctx=this.audioCtx; if(!ctx) return;
                if(ctx.state==='suspended') ctx.resume();
                const now=ctx.currentTime;
                [ [880,0], [1318.5,0.12] ].forEach(([freq,at])=>{   // dua nada: A5 → E6 (chime ceria)
                    const osc=ctx.createOscillator(), gain=ctx.createGain();
                    osc.type='sine'; osc.frequency.value=freq;
                    gain.gain.setValueAtTime(0.0001, now+at);
                    gain.gain.exponentialRampToValueAtTime(0.25, now+at+0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now+at+0.35);
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.start(now+at); osc.stop(now+at+0.37);
                });
            } catch(e){}
        },

        // Suara sapaan: masuk → "Selamat datang, nama", pulang → "Terima kasih, nama"
        speak(label, nama){
            try {
                if(!('speechSynthesis' in window)) return;
                const panggil = (nama||'').split(',')[0].trim();
                const teks = (label === 'pulang' ? 'Terima kasih, ' : 'Selamat datang, ') + panggil;
                const u = new SpeechSynthesisUtterance(teks);
                u.lang='id-ID'; u.rate=0.97; u.pitch=1;
                const id = speechSynthesis.getVoices().find(v => v.lang && v.lang.toLowerCase().startsWith('id'));
                if(id) u.voice = id;
                speechSynthesis.speak(u);   // antre — tiap orang tersapa berurutan
            } catch(e){}
        },

        async start(){
            this.enterFs(); // panggil dalam gesture klik (sebelum await) agar fullscreen diizinkan
            try { this.audioCtx = this.audioCtx || new (window.AudioContext||window.webkitAudioContext)(); } catch(e){}
            try { if('speechSynthesis' in window){ speechSynthesis.getVoices(); speechSynthesis.speak(new SpeechSynthesisUtterance(' ')); } } catch(e){} // buka izin suara (gesture)
            this.enrolled = this.attendees.filter(s=>s.desc && s.desc.length);
            if(this.enrolled.length===0){ this.status='Belum ada wajah terdaftar.'; showToast('Daftarkan wajah siswa/guru dulu','error'); return; }
            this.loading=true; this.status='Mengaktifkan kamera...';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:1280}, height:{ideal:720} } });
                const v=this.$refs.video; v.srcObject=this.stream;
                await new Promise(r=> v.onloadedmetadata = r); v.play();
                this.camOn=true;
                this.status='Memuat model AI (pertama kali agak lama, lalu tersimpan)...';
                await loadHuman();
                this.loading=false; this.scanning=true;
                this.status='Memindai ('+humanBackend+')...';
                this.tick();
            } catch(e){
                this.loading=false; this.camOn=false;
                this.status='Gagal: '+(e.name==='NotAllowedError'?'akses kamera ditolak':e.message);
            }
        },

        async tick(){
            if(!this.scanning) return;
            if(this.busy){ this.timer=setTimeout(()=>this.tick(), 120); return; }
            const v=this.$refs.video;
            if(!v.videoWidth){ this.timer=setTimeout(()=>this.tick(), 300); return; }
            this.busy=true;
            const t0=performance.now();
            try {
                const res = await human.detect(v);   // WebGPU asinkron → UI tetap responsif
                this.render(res);
            } catch(e){ /* skip frame */ }
            this.busy=false;
            const dt = performance.now()-t0;

            // jeda kecil adaptif; selalu memindai (kiosk) selama masih ada yang belum hadir
            const allDone = this.enrolled.every(s=>s.marked);
            const delay = allDone ? 1500 : Math.min(1200, Math.max(200, Math.round(dt*0.7)));
            this.timer=setTimeout(()=>this.tick(), delay);
        },

        render(res){
            const v=this.$refs.video, c=this.$refs.canvas;
            c.width=v.videoWidth; c.height=v.videoHeight;
            const ctx=c.getContext('2d'); ctx.clearRect(0,0,c.width,c.height);
            (res.face||[]).forEach(f=>{
                if(!f.embedding || !f.box) return;
                let bestUuid=null, bestSim=0;
                for(const s of this.enrolled){ for(const e of s.desc){ const sim=faceSim(f.embedding, e); if(sim>bestSim){ bestSim=sim; bestUuid=s.uuid; } } }
                const matched = bestSim >= this.threshold;
                const b=f.box; // [x,y,w,h]
                let label='Tidak dikenal', color='#ef4444';
                if(matched){ const s=this.attendees.find(z=>z.uuid===bestUuid); label=(s?s.nama.split(' ')[0]:'?'); color='#10b981'; this.onMatch(bestUuid); }
                ctx.strokeStyle=color; ctx.lineWidth=3; ctx.strokeRect(b[0], b[1], b[2], b[3]);
                ctx.font='bold 20px sans-serif'; const tw=ctx.measureText(label).width+14;
                ctx.fillStyle=color; ctx.fillRect(b[0], b[1]-30, tw, 28);
                ctx.fillStyle='#fff'; ctx.fillText(label, b[0]+7, b[1]-10);
            });
        },

        onMatch(uuid){
            const s=this.attendees.find(x=>x.uuid===uuid);
            if(!s) return;

            // ===== Mode PULANG (khusus guru) =====
            if(this.scanMode==='pulang'){
                if(s.type!=='guru') return;        // pulang hanya dilacak untuk guru
                if(s.pulangMarked) return;
                s.pulangMarked=true; s.justMarked=true;
                this.playDing();
                this.speak('pulang', s.nama);
                const k=++this._seq;
                const jamK=this.nowHM();
                this.lastMatch={ key:k, nama:s.nama, type:s.type, kelas:'Guru', mode:'pulang', jam:jamK };
                this.recent.unshift({ key:k, nama:s.nama.split(' ')[0], type:s.type, kelas:'Pulang', mode:'pulang', jam:jamK });
                if(this.recent.length>5) this.recent.pop();
                setTimeout(()=> window.lucide && lucide.createIcons(), 40);
                setTimeout(()=>{ if(this.lastMatch && this.lastMatch.key===k) this.lastMatch=null; }, 1700);
                setTimeout(()=>{ s.justMarked=false; }, 1600);
                setTimeout(()=>{ this.recent = this.recent.filter(x=>x.key!==k); }, 6000); // auto-hilang
                fetch('{{ route('presensi-guru.mark') }}', {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ id_guru: uuid, tanggal: '{{ $tanggal }}', mode:'pulang' })
                }).then(r=>r.json()).then(d=>{
                    if(d&&d.jam){ const it=this.recent.find(x=>x.key===k); if(it) it.jam=d.jam; if(this.lastMatch&&this.lastMatch.key===k) this.lastMatch.jam=d.jam; }
                }).catch(()=>{});
                return;
            }

            // ===== Mode MASUK =====
            if(s.marked) return;
            s.marked=true; s.justMarked=true;
            this.playDing();   // bunyi sukses
            this.speak('masuk', s.nama);

            // feedback visual (langsung tampil dengan jam lokal, dikoreksi jam server)
            const key = ++this._seq;
            const jamNow = this.nowHM();
            const subKelas = s.type === 'siswa' ? s.kelas : 'Guru';
            this.lastMatch = { key, nama: s.nama, type: s.type, kelas: subKelas, mode:'masuk', jam: jamNow };
            this.recent.unshift({ key, nama: s.nama.split(' ')[0], type: s.type, kelas: subKelas, mode:'masuk', jam: jamNow });
            if(this.recent.length>5) this.recent.pop();
            setTimeout(()=> window.lucide && lucide.createIcons(), 40);
            setTimeout(()=>{ if(this.lastMatch && this.lastMatch.key===key) this.lastMatch=null; }, 1700);
            setTimeout(()=>{ s.justMarked=false; }, 1600);
            setTimeout(()=>{ this.recent = this.recent.filter(x=>x.key!==key); }, 6000); // auto-hilang

            // payload based on type
            let url, bodyData;
            if (s.type === 'siswa') {
                url = '{{ route('absensi.mark') }}';
                bodyData = { id_siswa: uuid, id_kelas: s.id_kelas, tanggal: '{{ $tanggal }}', status: 'hadir' };
            } else {
                url = '{{ route('presensi-guru.mark') }}';
                bodyData = { id_guru: uuid, tanggal: '{{ $tanggal }}', status: 'hadir', mode:'masuk' };
            }
            fetch(url, {
                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                body: JSON.stringify(bodyData)
            }).then(r=>r.json()).then(d=>{
                if(d && d.jam){ const it=this.recent.find(x=>x.key===key); if(it) it.jam=d.jam; if(this.lastMatch && this.lastMatch.key===key) this.lastMatch.jam=d.jam; }
                if(d && d.terlambat){ showToast(s.nama+' Terlambat (' + d.jam + ')', 'info'); }
            }).catch(()=>{});
        },

        stop(){
            this.scanning=false; this.camOn=false;
            if(this.timer) clearTimeout(this.timer);
            if(this.stream){ this.stream.getTracks().forEach(t=>t.stop()); this.stream=null; }
            if(document.fullscreenElement){ document.exitFullscreen?.(); }
            const c=this.$refs.canvas; if(c){ c.getContext('2d').clearRect(0,0,c.width,c.height); }
            this.recent=[]; this.lastMatch=null;
            this.status='Pemindaian dihentikan. '+this.totalHadir+' hadir. Klik Mulai Scan untuk lanjut.';
        }
    }
}
</script>
@endpush
@endsection
