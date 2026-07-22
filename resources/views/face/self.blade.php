@extends('layouts.app')
@section('title', 'Daftar Wajah')

@section('content')
<div class="max-w-3xl mx-auto" x-data="selfEnroll()" @keydown.space.window="onSpace($event)">

    <div class="card overflow-hidden">
        {{-- Header --}}
        <div class="p-5 sm:p-6 border-b border-slate-100 dark:border-slate-700 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl grid place-items-center text-white shadow mb-3" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
                <i data-lucide="scan-face" class="w-7 h-7"></i>
            </div>
            <h1 class="page-title">{{ ($ulang ?? false) ? 'Perbarui Wajah' : 'Daftarkan Wajah Anda' }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">
                @if($ulang ?? false)
                Hai <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $nama }}</span>, ambil ulang foto & data wajah Anda. Data wajah lama akan digantikan.
                @else
                Hai <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $nama }}</span>, sebelum melanjutkan Anda perlu mendaftarkan wajah untuk absensi otomatis. Cukup sekali saja.
                @endif
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-0">
            {{-- Kamera --}}
            <div class="p-5 sm:p-6 space-y-4 md:border-r border-slate-100 dark:border-slate-700">
                <div class="relative rounded-2xl overflow-hidden bg-slate-900 aspect-[3/4] sm:aspect-[4/3]">
                    <video x-ref="video" autoplay muted playsinline class="w-full h-full object-cover" :class="streaming ? '' : 'opacity-0'"></video>
                    <div x-show="!streaming" class="absolute inset-0 grid place-items-center text-slate-300 text-sm">
                        <div class="text-center px-6">
                            <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-2" x-show="loading"></i>
                            <i data-lucide="camera" class="w-8 h-8 mx-auto mb-2 opacity-50" x-show="!loading"></i>
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
                <p class="text-center text-sm" :class="msgErr ? 'text-rose-500' : 'text-slate-500'" x-text="msg"></p>

                <div class="flex gap-2">
                    <button x-show="!streaming" @click="openCam()" :disabled="loading" class="btn-primary flex-1 px-4 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                        <i data-lucide="video" class="w-4 h-4"></i> Nyalakan Kamera
                    </button>
                    <button x-show="streaming" @click="capture()" :disabled="capturing || samples.length>=5" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold border border-primary text-primary hover:bg-primary-50 transition flex items-center justify-center gap-1.5 disabled:opacity-40">
                        <i data-lucide="aperture" class="w-4 h-4"></i> Ambil Sampel (<span x-text="samples.length"></span>/5)
                        <kbd class="hidden sm:inline text-[10px] px-1.5 py-0.5 rounded bg-primary-50 border border-primary/30">Spasi</kbd>
                    </button>
                </div>
                <button x-show="streaming" @click="save()" :disabled="samples.length<3 || saving" class="btn-primary w-full px-5 py-3 rounded-xl text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-40">
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="saving"></i>
                    <i data-lucide="check" class="w-4 h-4" x-show="!saving"></i>
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan & Lanjutkan'"></span>
                </button>
            </div>

            {{-- Tutorial 3 posisi --}}
            <div class="p-5 sm:p-6 bg-slate-50/60 dark:bg-slate-900/30">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-3 text-center">Panduan Posisi Wajah</p>
                @include('partials.face-tutorial')
                <div class="mt-5">
                    @include('partials.face-registration-rules')
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-center">
            @if($ulang ?? false)
            <a href="{{ route('profile.index') }}" class="text-xs text-slate-400 hover:text-primary flex items-center gap-1"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Profil</a>
            @else
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-slate-400 hover:text-rose-500 flex items-center gap-1"><i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar</button>
            </form>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/human@3.3.6/dist/human.js"></script>
<script>
let human=null, humanReady=false;
async function loadHuman(){
    if(humanReady) return human;
    const HumanLib = window.Human?.Human || window.Human?.default || window.Human;
    const backend = (typeof navigator!=='undefined' && navigator.gpu) ? 'webgpu' : 'webgl';
    human = new HumanLib({
        modelBasePath:'https://vladmandic.github.io/human-models/models/',
        backend: backend, cacheSensitivity: 0, warmup:'none',
        face:{ enabled:true, detector:{ maxDetected:1, minConfidence:0.45 }, mesh:{enabled:true}, iris:{enabled:false},
               description:{enabled:true}, emotion:{enabled:false}, antispoof:{enabled:false}, liveness:{enabled:false} },
        body:{enabled:false}, hand:{enabled:false}, object:{enabled:false}, gesture:{enabled:false},
        filter:{enabled:false}, segmentation:{enabled:false},
    });
    await human.load();
    humanReady = true;
    return human;
}

function selfEnroll(){
    return {
        loading:false, streaming:false, capturing:false, saving:false, lowLight:false,
        samples:[], photo:null, _bestYaw:Infinity, stream:null, status:'Klik "Nyalakan Kamera" untuk memulai', msg:'', msgErr:false,

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

        // potong area wajah jadi kotak yang SELALU di dalam frame (anti bar hitam) + tajam.
        // `source` = frame yg sudah dicerahkan (kanvas) bila ada, supaya foto tersimpan konsisten
        // dgn frame yg dipakai model deteksi — bukan video mentah yg mungkin masih gelap.
        cropFace(box, source){
            try {
                const src = source || this.$refs.video;
                const vw = src.videoWidth || src.width, vh = src.videoHeight || src.height;
                const [x,y,w,h]=box, cx=x+w/2, cy=y+h/2;
                let side=Math.max(w,h)*1.7;              // kotak + ruang sekitar wajah
                side=Math.min(side, vw, vh);             // tak boleh lebih besar dari frame
                let sx=Math.max(0, Math.min(cx-side/2, vw-side));
                let sy=Math.max(0, Math.min(cy-side/2, vh-side));
                const size=480;
                const cv=document.createElement('canvas'); cv.width=size; cv.height=size;
                cv.getContext('2d').drawImage(src, sx,sy,side,side, 0,0,size,size);
                return cv.toDataURL('image/jpeg', 0.95);
            } catch(e){ return null; }
        },

        async openCam(){
            this.loading=true; this.status='Mengaktifkan kamera & memuat model AI...';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:1280}, height:{ideal:720} } });
                this.$refs.video.srcObject = this.stream;
                this.streaming=true;
                await loadHuman();
                // Warm-up: kompilasi shader GPU sekali (saat loading) agar capture pertama tak nge-lag
                this.status='Menyiapkan model (sekali saja)...';
                try { const cv=document.createElement('canvas'); cv.width=256; cv.height=256; cv.getContext('2d').fillRect(0,0,256,256); await human.detect(cv); } catch(e){}
                this.loading=false;
                this.msg='Ikuti panduan, lalu tekan Spasi / klik Ambil Sampel.';
                setTimeout(()=> window.lucide && lucide.createIcons(), 40);
            } catch(e){
                this.loading=false;
                this.status='Gagal: ' + (e.name==='NotAllowedError' ? 'akses kamera ditolak' : e.message);
            }
        },
        // Spasi = ambil sampel (saat kamera aktif)
        onSpace(e){
            if(this.streaming && !this.capturing && !this.saving && this.samples.length < 5){
                e.preventDefault();
                this.capture();
            }
        },
        async capture(){
            this.capturing=true; this.msg='Mendeteksi wajah...'; this.msgErr=false;
            try {
                const frame = this.enhanceFrame(this.$refs.video); // pencerahan otomatis sebelum deteksi (aman di tempat gelap)
                const res = await human.detect(frame);
                let face = (res.face||[])[0];
                const quality = this.faceQuality(face);
                if(quality.ok){
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
                    // Kalau tadi dicerahkan lokal, deteksi ulang di kanvas yg sudah diperbaiki agar
                    // embedding yg dipakai adalah versi kualitas terbaik, bukan versi sebelum dicerahkan.
                    if(occ.boosted){
                        const res2 = await human.detect(this._ecv);
                        const face2 = (res2.face||[])[0];
                        if(face2 && face2.embedding) face = face2;
                    }
                    this.samples.push(Array.from(face.embedding));
                    // simpan snapshot HANYA dari pose paling menghadap depan (yaw terkecil)
                    const yaw = Math.abs(face.rotation?.angle?.yaw ?? 0);
                    if(face.box && yaw < this._bestYaw){ this.photo = this.cropFace(face.box, this._ecv); this._bestYaw = yaw; }
                    this.msg = 'Sampel ' + this.samples.length + ' tersimpan. ' + (this.samples.length<5 ? 'Ubah posisi/jarak sedikit & ambil lagi (target 5).' : 'Lengkap (5)! Klik Simpan & Lanjutkan.');
                } else {
                    this.msg=quality.msg || 'Wajah tidak terdeteksi. Perbaiki posisi & pencahayaan.'; this.msgErr=true;
                }
            } catch(e){ this.msg='Error: '+e.message; this.msgErr=true; }
            this.capturing=false;
        },
        async save(){
            if(this.samples.length < 3){ this.msg='Ambil minimal 3 sampel wajah dulu (disarankan 5).'; this.msgErr=true; return; }
            this.saving=true;
            try {
                const res = await fetch('{{ route('face.self.store') }}', {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ descriptors: this.samples, photo: this.photo })
                });
                if(res.ok){
                    showToast('Wajah berhasil disimpan!');
                    if(this.stream){ this.stream.getTracks().forEach(t=>t.stop()); }
                    setTimeout(()=> window.location.href='{{ $redirectAfter ?? route('dashboard') }}', 800);
                    return;
                }
                if(res.status===422){
                    const d = await res.json();
                    if(d.duplicate){
                        this.msg = d.message + ' Hubungi admin jika perlu bantuan.';
                        this.msgErr = true;
                        this.saving=false;
                        return;
                    }
                }
                showToast('Gagal menyimpan, coba lagi','error'); this.saving=false;
            } catch { showToast('Gagal menghubungi server','error'); this.saving=false; }
        }
    }
}
</script>
@endpush
@endsection
