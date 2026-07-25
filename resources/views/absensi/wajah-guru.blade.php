@extends('layouts.app')
@section('title', 'Registrasi Wajah Guru')

@section('content')
<div class="space-y-5" x-data="faceEnrollGuru(@js(['faceEngine' => $faceEngine]))" @keydown.space.window="onSpace($event)">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Registrasi Wajah Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Daftarkan wajah tiap guru untuk presensi otomatis</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('absensi.wajah') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="users" class="w-4 h-4"></i> Registrasi Wajah Siswa
            </a>
            <a href="{{ route('wajah.galeri') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="images" class="w-4 h-4"></i> Validasi Wajah
            </a>
            <a href="{{ route('absensi.scan') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
                <i data-lucide="scan-face" class="w-4 h-4"></i> Mulai Absen Scan
            </a>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label">Cari nama guru</label>
            <input type="text" x-model="q" placeholder="Ketik nama..." class="form-input">
        </div>
        @php $descCol = \App\Support\FaceEngine::kolomDescriptor(); $terdaftar = $gurus->whereNotNull($descCol)->count(); @endphp
        <div class="text-sm text-slate-500">
            <span class="font-bold text-primary">{{ $terdaftar }}</span> / {{ $gurus->count() }} wajah terdaftar
        </div>
    </div>

    @if($gurus->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada data guru.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($gurus as $g)
        <div class="card p-3.5 flex items-center gap-3" x-show="q==='' || @js(strtolower($g->nama)).includes(q.toLowerCase())">
            <div class="w-11 h-11 rounded-full grid place-items-center text-white font-bold flex-shrink-0 relative overflow-hidden" style="background:{{ $g->jk==='P' ? '#ec4899' : 'var(--cp)' }}">
                @if($g->face_photo)
                <img src="{{ $g->face_photo_url }}" class="w-full h-full object-cover cursor-zoom-in" @click="zoom('{{ $g->face_photo_url }}', @js($g->nama))" alt="wajah">
                @else
                {{ strtoupper(substr($g->nama,0,1)) }}
                @endif
                @if($g->{$descCol})
                <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-emerald-500 grid place-items-center ring-2 ring-white"><i data-lucide="check" class="w-2.5 h-2.5 text-white"></i></span>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $g->nama }}</p>
                @if($g->{$descCol})
                <p class="text-xs text-emerald-600 flex items-center gap-1"><i data-lucide="badge-check" class="w-3 h-3"></i> Terdaftar</p>
                @else
                <p class="text-xs text-slate-400">{{ $g->nip ?: $g->nik ?: 'Belum daftar wajah' }}</p>
                @endif
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
                <button @click="openFor('{{ $g->uuid }}', @js($g->nama))" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-primary-50 text-primary hover:bg-primary-100 transition flex items-center gap-1">
                    <i data-lucide="camera" class="w-3.5 h-3.5"></i> {{ $g->{$descCol} ? 'Ulangi' : 'Daftar' }}
                </button>
                @if($g->{$descCol})
                <button @click="hapus('{{ $g->uuid }}')" class="p-1.5 rounded-lg hover:bg-rose-50 text-rose-500" title="Hapus wajah"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ===== Modal kamera registrasi ===== --}}
    <div x-show="modal" class="modal-backdrop" x-transition style="display:none" @click.self="close()">
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Daftar Wajah</h3>
                    <p class="text-xs text-slate-400" x-text="nama"></p>
                </div>
                <button @click="close()" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5 space-y-4">
                {{-- Tutorial 3 posisi --}}
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-900/40 p-3 space-y-4">
                    @include('partials.face-tutorial')
                    @include('partials.face-registration-rules')
                </div>
                <div class="relative rounded-2xl overflow-hidden bg-slate-900 aspect-[3/4] sm:aspect-[4/3]">
                    <video x-ref="video" autoplay muted playsinline class="w-full h-full object-cover" :class="streaming ? '' : 'opacity-0'"></video>
                    <div x-show="!streaming" class="absolute inset-0 grid place-items-center text-slate-400 text-sm">
                        <div class="text-center">
                            <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-2" x-show="loading"></i>
                            <p x-text="status"></p>
                        </div>
                    </div>
                    {{-- oval guide --}}
                    <div x-show="streaming" class="absolute inset-0 grid place-items-center pointer-events-none">
                        <div class="w-40 h-52 rounded-[50%] border-2 border-white/70 border-dashed"></div>
                    </div>
                    {{-- sample badges --}}
                    <div x-show="streaming" class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                        <template x-for="i in 5"><span class="w-3 h-3 rounded-full transition" :class="samples.length>=i ? 'bg-emerald-400' : 'bg-white/40'"></span></template>
                    </div>
                    {{-- indikator pencahayaan rendah --}}
                    <div x-show="streaming && lowLight" x-cloak class="absolute top-3 left-3 flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-500/85 backdrop-blur text-white text-xs font-semibold">
                        <i data-lucide="sun" class="w-3.5 h-3.5"></i> Pencahayaan rendah — kecerahan otomatis aktif
                    </div>
                </div>
                {{-- Variasi sudut wajib: 5 zona (tengah/kiri/kanan/atas/bawah) harus lengkap sebelum bisa disimpan --}}
                <div x-show="streaming" class="flex flex-wrap items-center justify-center gap-1.5 text-xs font-semibold">
                    <template x-for="z in ANGLE_ZONES" :key="z">
                        <span class="px-2.5 py-1 rounded-full border transition"
                              :class="zoneCounts[z]>0 ? 'bg-emerald-50 border-emerald-300 text-emerald-600 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-300' : 'bg-slate-50 border-slate-200 text-slate-400 dark:bg-slate-800 dark:border-slate-600'">
                            <span x-text="(zoneCounts[z]>0 ? '✓ ' : '') + zoneLabel(z)"></span>
                        </span>
                    </template>
                </div>
                <p class="text-center text-sm" :class="msgErr ? 'text-rose-500' : 'text-slate-500'" x-text="msg"></p>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-700 flex flex-wrap sm:flex-nowrap gap-2 justify-end">
                <button @click="close()" class="flex-1 sm:flex-none justify-center px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Batal</button>
                <button @click="capture()" :disabled="!streaming || capturing || samples.length>=5" class="w-full sm:w-auto order-first sm:order-none justify-center px-4 py-2 rounded-xl text-sm font-semibold border border-primary text-primary hover:bg-primary-50 transition flex items-center gap-1.5 disabled:opacity-40">
                    <i data-lucide="aperture" class="w-4 h-4"></i> Ambil Sampel (<span x-text="samples.length"></span>/5)
                    <kbd class="text-[10px] px-1.5 py-0.5 rounded bg-primary-50 border border-primary/30">Spasi</kbd>
                </button>
                <button @click="save()" :disabled="samples.length<3 || saving || Object.values(zoneCounts).some(n => !n)" class="flex-1 sm:flex-none justify-center btn-primary px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 disabled:opacity-40">
                    <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin" x-show="saving"></i><span x-text="saving?'Menyimpan...':'Simpan'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Zoom foto wajah --}}
    <div x-show="zoomSrc" class="modal-backdrop" style="display:none" @click="closeZoom()" x-transition>
        <div class="text-center" @click.stop>
            <img :src="zoomSrc" class="max-h-[72vh] max-w-[90vw] rounded-2xl shadow-2xl ring-4 ring-white/20">
            <p class="text-white mt-3 font-semibold text-lg" x-text="zoomNama"></p>
            <p class="text-white/60 text-xs mt-1">Klik di mana saja untuk menutup</p>
        </div>
    </div>
</div>

@push('scripts')
@if(\App\Support\FaceEngine::isInsightFace())
@include('absensi._insightface_engine')
@else
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/human@3.3.6/dist/human.js"></script>
@endif
@include('absensi._face_enroll_shared')
<script>
let humanGuru=null, humanGuruReady=false;
async function loadHumanGuru(){
    if(humanGuruReady) return humanGuru;
    const HumanLib = window.Human?.Human || window.Human?.default || window.Human;
    const backend = (typeof navigator!=='undefined' && navigator.gpu) ? 'webgpu' : 'webgl';
    humanGuru = new HumanLib({
        modelBasePath:'https://vladmandic.github.io/human-models/models/',
        backend: backend, cacheSensitivity: 0, warmup:'none',
        face:{ enabled:true, detector:{ maxDetected:1, minConfidence:0.45 }, mesh:{enabled:true}, iris:{enabled:false},
               description:{enabled:true}, emotion:{enabled:false}, antispoof:{enabled:false}, liveness:{enabled:false} },
        body:{enabled:false}, hand:{enabled:false}, object:{enabled:false}, gesture:{enabled:false},
        filter:{enabled:false}, segmentation:{enabled:false},
    });
    await humanGuru.load();
    humanGuruReady = true;
    return humanGuru;
}

function faceEnrollGuru(opts){
    opts = opts || {};
    return Object.assign(faceEnrollShared(), {
        modal:false, loading:false, streaming:false, capturing:false, saving:false, lowLight:false, q:'',
        faceEngine: opts.faceEngine || 'human',
        uuid:null, nama:'', samples:[], photo:null, _bestYaw:Infinity, stream:null, status:'', msg:'', msgErr:false,
        zoomSrc:null, zoomNama:'',
        zoom(src, nama){ this.zoomSrc=src; this.zoomNama=nama; },
        closeZoom(){ this.zoomSrc=null; },

        // Pencerahan otomatis: gambar video digambar ke kanvas offscreen, dicerahkan bila gelap,
        // lalu KANVAS itu (bukan video mentah) yang dipakai utk deteksi wajah & snapshot foto.
        // Sengaja TIDAK dicampur dgn contrast() — contrast linear di sekitar titik tengah 128 justru
        // menekan piksel gelap balik ke bawah, melawan efek brightness yg baru dinaikkan.
        enhanceFrame(video){
            const w=video.videoWidth, h=video.videoHeight;
            if(!w || !h) return video;
            if(!this._ecv){ this._ecv=document.createElement('canvas'); this._ectx=this._ecv.getContext('2d', { willReadFrequently:true }); }
            const cv=this._ecv, ctx=this._ectx;
            cv.width=w; cv.height=h;
            ctx.filter='none';
            ctx.drawImage(video, 0, 0, w, h);
            const px=ctx.getImageData(0, 0, w, h).data;
            let sum=0, n=0;
            for(let i=0; i<px.length; i+=160){ sum += 0.299*px[i] + 0.587*px[i+1] + 0.114*px[i+2]; n++; }
            const avgLuma = n ? sum/n : 128;
            this.lowLight = avgLuma < 90;
            if(this.lowLight){
                const boost = Math.min(2.8, 1 + (90-avgLuma)/50).toFixed(2);
                ctx.filter = `brightness(${boost})`;
                ctx.drawImage(video, 0, 0, w, h);
                ctx.filter = 'none';
            }
            return cv;
        },

        faceQuality(face){
            if(!face || !face.embedding || !face.box) return { ok:false, msg:'Wajah tidak terdeteksi. Pastikan wajah masuk bingkai.' };
            const v=this.$refs.video;
            const b=face.box;
            const score = face.faceScore ?? face.score ?? face.boxScore ?? 1;
            const minSide = Math.min(b[2] || 0, b[3] || 0);
            if(score < 0.5) return { ok:false, msg:'Wajah kurang jelas. Tambah cahaya dan tahan posisi sebentar.' };
            if(v.videoHeight && minSide < v.videoHeight * 0.18) return { ok:false, msg:'Wajah terlalu jauh dari kamera. Dekatkan sedikit lalu ambil ulang.' };
            return { ok:true };
        },

        // Rata-rata kecerahan (luma) sebuah area kotak pada kanvas — dipakai checkOcclusion().
        regionLuma(ctx, x, y, w, h){
            x=Math.max(0,Math.round(x)); y=Math.max(0,Math.round(y));
            w=Math.max(1,Math.round(w)); h=Math.max(1,Math.round(h));
            try {
                const px = ctx.getImageData(x, y, w, h).data;
                let sum=0, n=0;
                for(let i=0; i<px.length; i+=40){ sum += 0.299*px[i] + 0.587*px[i+1] + 0.114*px[i+2]; n++; }
                return n ? sum/n : null;
            } catch(e){ return null; }
        },
        // Fitur cek penutup wajah (mis. hijab/topi) + pencerahan LOKAL: bandingkan kecerahan
        // dahi & rahang terhadap area tengah wajah (pipi/hidung — hampir selalu terlihat jelas).
        // Kalau dahi/rahang jauh lebih gelap → dicoba cerahkan HANYA area itu dulu (bukan
        // seluruh gambar, beda dari enhanceFrame yg global). Kalau tetap gelap/rata setelah
        // dicerahkan, kemungkinan besar memang tertutup kain, bukan sekadar bayangan.
        checkOcclusion(cv, ctx, box, source){
            source = source || this.$refs.video;
            const [bx,by,bw,bh] = box;
            const center   = { x:bx+bw*0.25, y:by+bh*0.35, w:bw*0.50, h:bh*0.30 };
            const forehead = { x:bx+bw*0.20, y:by+bh*0.00, w:bw*0.60, h:bh*0.16 };
            const jaw      = { x:bx+bw*0.25, y:by+bh*0.80, w:bw*0.50, h:bh*0.18 };

            const centerLuma = this.regionLuma(ctx, center.x, center.y, center.w, center.h);
            let foreheadLuma = this.regionLuma(ctx, forehead.x, forehead.y, forehead.w, forehead.h);
            let jawLuma      = this.regionLuma(ctx, jaw.x, jaw.y, jaw.w, jaw.h);
            if(centerLuma===null || foreheadLuma===null || jawLuma===null || centerLuma < 5){
                return { forehead:true, jaw:true, boosted:false }; // data tak cukup → jangan blokir pengguna
            }

            const RATIO_OK = 0.6; // dahi/rahang minimal 60% sekuat cahaya area tengah wajah
            let foreheadOk = (foreheadLuma / centerLuma) >= RATIO_OK;
            let jawOk = (jawLuma / centerLuma) >= RATIO_OK;
            let boosted = false;

            const boostRegion = (r, ratio) => {
                const boost = Math.min(3.0, 1 + (RATIO_OK - ratio) * 4).toFixed(2);
                ctx.save();
                ctx.beginPath(); ctx.rect(r.x, r.y, r.w, r.h); ctx.clip();
                ctx.filter = `brightness(${boost})`;
                ctx.drawImage(source, 0, 0, cv.width, cv.height);
                ctx.filter = 'none';
                ctx.restore();
            };
            if(!foreheadOk){ boostRegion(forehead, foreheadLuma/centerLuma); boosted=true; }
            if(!jawOk){ boostRegion(jaw, jawLuma/centerLuma); boosted=true; }

            if(boosted){
                foreheadLuma = this.regionLuma(ctx, forehead.x, forehead.y, forehead.w, forehead.h);
                jawLuma = this.regionLuma(ctx, jaw.x, jaw.y, jaw.w, jaw.h);
                foreheadOk = (foreheadLuma / centerLuma) >= RATIO_OK;
                jawOk = (jawLuma / centerLuma) >= RATIO_OK;
            }
            return { forehead:foreheadOk, jaw:jawOk, boosted };
        },

        cropFace(box, source){
            try {
                const src = source || this.$refs.video;
                const vw = src.videoWidth || src.width, vh = src.videoHeight || src.height;
                const [x,y,w,h]=box, cx=x+w/2, cy=y+h/2;
                let side=Math.max(w,h)*1.7;
                side=Math.min(side, vw, vh);
                let sx=Math.max(0, Math.min(cx-side/2, vw-side));
                let sy=Math.max(0, Math.min(cy-side/2, vh-side));
                const size=480;
                const cv=document.createElement('canvas'); cv.width=size; cv.height=size;
                cv.getContext('2d').drawImage(src, sx,sy,side,side, 0,0,size,size);
                return cv.toDataURL('image/jpeg', 0.95);
            } catch(e){ return null; }
        },

        async openFor(uuid, nama){
            this.uuid=uuid; this.nama=nama; this.samples=[]; this.photo=null; this._bestYaw=Infinity; this.msg=''; this.msgErr=false; this.lowLight=false;
            this.zoneCounts={tengah:0, kiri:0, kanan:0, atas:0, bawah:0};
            this.modal=true; this.streaming=false; this.loading=true; this.status='Memuat model AI (pertama kali agak lama)...';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:1280}, height:{ideal:720} } });
                this.stream = stream;
                this.$refs.video.srcObject = this.stream;
                this.streaming=true;
                if(this.faceEngine === 'insightface') await loadInsightFace(); else await loadHumanGuru();
                this.status='Menyiapkan model (sekali saja)...';
                try {
                    const cv=document.createElement('canvas'); cv.width=256; cv.height=256; cv.getContext('2d').fillRect(0,0,256,256);
                    if(this.faceEngine === 'insightface') await ifDetect(cv); else await humanGuru.detect(cv);
                } catch(e){}
                this.loading=false;
                this.msg='Posisikan wajah dalam bingkai, lalu tekan Spasi / klik Ambil Sampel.';
            } catch(e){
                this.loading=false;
                this.status='Gagal: ' + (e.name==='NotAllowedError' ? 'akses kamera ditolak' : e.message);
            }
        },
        onSpace(e){
            if(this.modal && this.streaming && !this.capturing && !this.saving && this.samples.length < 5){
                e.preventDefault();
                this.capture();
            }
        },
        async capture(){
            this.capturing=true; this.msg='Mendeteksi wajah...'; this.msgErr=false;
            try {
                const frame = this.enhanceFrame(this.$refs.video); // pencerahan otomatis sebelum deteksi (aman di tempat gelap)
                const res = this.faceEngine === 'insightface' ? await ifDetect(frame) : await humanGuru.detect(frame);
                let face = (res.face||[])[0];
                const quality = this.faceQuality(face);
                if(quality.ok){
                    // Cek wajah di dalam lingkaran panduan (posisi & jarak) SEBELUM cek lain.
                    const framed = this.checkFramed(face, this.$refs.video);
                    if(!framed.ok){
                        this.msg = framed.msg;
                        this.msgErr = true;
                        this.capturing = false;
                        return;
                    }
                    // Cek dahi/rahang tertutup (mis. hijab) + coba cerahkan lokal dulu sebelum menolak.
                    const occ = this.checkOcclusion(this._ecv, this._ectx, face.box);
                    if(!occ.forehead || !occ.jaw){
                        const bagian = (!occ.forehead && !occ.jaw) ? 'Dahi dan rahang'
                            : (!occ.forehead ? 'Dahi' : 'Rahang/dagu');
                        this.msg = bagian + ' belum cukup terlihat (mungkin tertutup hijab/topi atau bayangan). Sesuaikan sedikit ke belakang lalu ambil ulang.';
                        this.msgErr = true;
                        this.capturing = false;
                        return;
                    }
                    if(occ.boosted){
                        const res2 = this.faceEngine === 'insightface' ? await ifDetect(this._ecv) : await humanGuru.detect(this._ecv);
                        const face2 = (res2.face||[])[0];
                        if(face2 && face2.embedding) face = face2;
                    }
                    // Yaw & pitch BERTANDA — lihat komentar sama di wajah.blade.php.
                    let signedYaw, signedPitch;
                    if(face.kps && face.kps.length >= 5){
                        const [le, re, nose, lm, rm] = face.kps;
                        const midX = (le[0]+re[0])/2;
                        const eyeDist = Math.abs(re[0]-le[0]) || 1;
                        signedYaw = (nose[0]-midX) / eyeDist;
                        const eyeLineY = (le[1]+re[1])/2, mouthLineY = (lm[1]+rm[1])/2;
                        const faceH = Math.abs(mouthLineY-eyeLineY) || 1;
                        signedPitch = ((nose[1]-eyeLineY)/faceH) - 0.55;
                    } else {
                        signedYaw = face.rotation?.angle?.yaw ?? 0;
                        signedPitch = face.rotation?.angle?.pitch ?? 0;
                    }
                    // Paksa variasi sudut — lihat komentar sama di wajah.blade.php.
                    const zone = this.classifyAngle(signedYaw, signedPitch);
                    if((this.zoneCounts[zone]||0) >= 1){
                        this.msg = 'Sudah dapat sampel dari sisi ' + this.zoneLabel(zone) + '. Ganti pose ke sisi lain sebelum ambil sampel berikutnya.';
                        this.msgErr = true;
                        this.capturing = false;
                        return;
                    }
                    this.samples.push(Array.from(face.embedding));
                    this.zoneCounts[zone] = (this.zoneCounts[zone]||0) + 1;
                    // foto profil = pose paling menghadap depan (gabungan yaw+pitch terkecil)
                    const frontalness = Math.hypot(signedYaw, signedPitch);
                    if(face.box && frontalness < this._bestYaw){ this.photo = this.cropFace(face.box, this._ecv); this._bestYaw = frontalness; }
                    const sisaZona = ANGLE_ZONES.filter(z => !this.zoneCounts[z]);
                    this.msg = 'Sampel ' + this.samples.length + ' tersimpan. ' + (sisaZona.length ? ('Ambil juga dari sisi ' + sisaZona.map(z=>this.zoneLabel(z)).join(', ') + '.') : 'Lengkap (5). Klik Simpan.');
                    this.msgErr=false;
                } else {
                    this.msg=quality.msg || 'Wajah tidak terdeteksi. Pastikan pencahayaan cukup & wajah menghadap kamera.';
                    this.msgErr=true;
                }
            } catch(e){ this.msg='Error: '+e.message; this.msgErr=true; }
            this.capturing=false;
        },
        async save(force=false){
            if(this.samples.length < 3){ this.msg='Ambil minimal 3 sampel wajah dulu (disarankan 5).'; this.msgErr=true; return; }
            const sisaZona = ANGLE_ZONES.filter(z => !this.zoneCounts[z]);
            if(sisaZona.length){ this.msg='Variasi sudut belum lengkap. Ambil juga sampel dari sisi ' + sisaZona.map(z=>this.zoneLabel(z)).join(', ') + ' sebelum Simpan.'; this.msgErr=true; return; }
            this.saving=true;
            try {
                const res = await fetch(`/guru/${this.uuid}/wajah`, {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ descriptors: this.samples, photo: this.photo, force })
                });
                if(res.ok){ const data = await res.json(); showToast(data.message||'Wajah terdaftar.'); this.close(); setTimeout(()=>location.reload(),700); return; }
                if(res.status===422){
                    const d = await res.json();
                    if(d.duplicate){
                        this.saving=false;
                        const self=this;
                        $.confirm({
                            title:'Wajah mirip terdeteksi',
                            content:'<div class="text-slate-600 dark:text-slate-300">'+d.message+' Kemiripan <b class="text-rose-600">'+d.similarity+'%</b>.<br><br>Tetap daftarkan untuk <b>'+self.nama+'</b>?</div>',
                            type:'orange', icon:'',
                            buttons:{
                                ya:{ text:'Ya, tetap daftarkan', btnClass:'btn-warning', keys:['enter'], action:()=>{ self.save(true); } },
                                batal:{ text:'Batal', btnClass:'btn-default' }
                            }
                        });
                        return;
                    }
                }
                showToast('Gagal menyimpan','error'); this.saving=false;
            } catch { showToast('Gagal menghubungi server','error'); this.saving=false; }
        },
        hapus(uuid){
            $.confirm({ title:'Hapus data wajah?', content:'Guru perlu daftar ulang untuk absen scan.', type:'red',
                buttons:{ hapus:{ text:'Hapus', btnClass:'btn-red', action: async ()=>{
                    const res = await fetch(`/guru/${uuid}/wajah`, { method:'DELETE', headers:{'X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'} });
                    if(res.ok){ showToast('Data wajah dihapus.'); setTimeout(()=>location.reload(),600); }
                } }, batal:{text:'Batal'} } });
        },
        close(){
            this.modal=false;
            if(this.stream){ this.stream.getTracks().forEach(t=>t.stop()); this.stream=null; }
            this.streaming=false;
        }
    });
}
</script>
@endpush
@endsection
