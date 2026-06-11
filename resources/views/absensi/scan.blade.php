@extends('layouts.app')
@section('title', 'Absen Scan Wajah')

@section('content')
<div class="space-y-5" x-data="faceScan(@js($payload))">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Absen Scan Wajah</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Arahkan kamera ke siswa — yang dikenali otomatis tercatat <span class="font-semibold text-emerald-600">Hadir</span></p>
        </div>
        <a href="{{ route('absensi.wajah', ['kelas'=>$selectedKelas]) }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Registrasi Wajah
        </a>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('absensi.scan') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selectedKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-40">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input" onchange="this.form.submit()">
        </div>
    </form>

    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada siswa di kelas ini.</p>
    </div>
    @else
    <div class="grid lg:grid-cols-5 gap-5">
        {{-- Kamera --}}
        <div class="lg:col-span-3 space-y-3">
            <div class="card overflow-hidden">
                <div class="relative bg-slate-900 aspect-video">
                    <video x-ref="video" autoplay muted playsinline style="width:100%;height:100%;object-fit:fill" :class="camOn?'':'opacity-0'"></video>
                    <canvas x-ref="canvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
                    <div x-show="!camOn" class="absolute inset-0 grid place-items-center text-center text-slate-300">
                        <div>
                            <i data-lucide="loader-2" class="w-9 h-9 mx-auto animate-spin mb-2" x-show="loading"></i>
                            <i data-lucide="scan-face" class="w-12 h-12 mx-auto mb-2 opacity-50" x-show="!loading"></i>
                            <p class="text-sm px-6" x-text="status"></p>
                        </div>
                    </div>
                    {{-- loading model badge (kamera sudah nyala) --}}
                    <div x-show="camOn && loading" class="absolute top-3 left-3 flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold">
                        <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Memuat model AI...
                    </div>
                    {{-- live status pill --}}
                    <div x-show="scanning" class="absolute top-3 left-3 flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Memindai (worker)
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm text-slate-500">
                    <span class="font-bold text-emerald-600" x-text="hadirCount"></span> hadir /
                    {{ $siswas->count() }} siswa &bull;
                    <span x-text="enrolledCount"></span> wajah terdaftar
                </p>
                <div class="flex gap-2">
                    <button x-show="!camOn" @click="start()" :disabled="loading" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 disabled:opacity-50">
                        <i data-lucide="play" class="w-4 h-4"></i> Mulai Scan
                    </button>
                    <button x-show="camOn" @click="stop()" class="px-5 py-2.5 rounded-xl text-sm font-bold border border-rose-200 text-rose-600 hover:bg-rose-50 transition flex items-center gap-2">
                        <i data-lucide="square" class="w-4 h-4"></i> Hentikan
                    </button>
                </div>
            </div>
        </div>

        {{-- Daftar siswa --}}
        <div class="lg:col-span-2">
            <div class="card flex flex-col" style="max-height:70vh">
                <div class="p-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="font-bold text-slate-700 dark:text-slate-200">Daftar Hadir</h3>
                </div>
                <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
                    <template x-for="s in students" :key="s.uuid">
                        <div class="flex items-center gap-3 p-2.5 rounded-xl transition"
                             :class="s.marked ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800'">
                            <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0 transition"
                                 :class="s.justMarked ? 'ring-2 ring-emerald-400 scale-110' : ''"
                                 :style="'background:'+(s.jk==='L'?'var(--cp)':'#ec4899')" x-text="s.nama.charAt(0).toUpperCase()"></div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate" x-text="s.nama"></p>
                                <p class="text-xs" :class="(s.desc&&s.desc.length)?'text-slate-400':'text-amber-500'"
                                   x-text="(s.desc&&s.desc.length)?s.nis:'⚠ belum daftar wajah'"></p>
                            </div>
                            <span x-show="s.marked" class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Hadir</span>
                            <span x-show="!s.marked" class="text-xs text-slate-300">—</span>
                        </div>
                    </template>
                </div>
                <div class="p-3 border-t border-slate-100 dark:border-slate-700">
                    <a href="{{ route('absensi.index', ['kelas'=>$selectedKelas, 'tanggal'=>$tanggal]) }}" class="text-sm text-primary hover:underline flex items-center gap-1 justify-center">
                        <i data-lucide="list-checks" class="w-4 h-4"></i> Lengkapi manual (izin/sakit/alpa)
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
// ===== Kode Web Worker (proses AI di thread terpisah → UI tak pernah freeze) =====
const FACE_WORKER_CODE = `
var matcher=null, opts=null, ready=false;
try { importScripts('https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js'); }
catch(e){ self.postMessage({ type:'error', message:'gagal memuat face-api' }); }

self.onmessage = async function(e){
  var d = e.data;
  if(d.type === 'init'){
    try {
      try { await faceapi.tf.setBackend('webgl'); } catch(_){ }
      await faceapi.tf.ready();
      var url = d.modelUrl;
      await faceapi.nets.tinyFaceDetector.loadFromUri(url);
      await faceapi.nets.faceLandmark68Net.loadFromUri(url);
      await faceapi.nets.faceRecognitionNet.loadFromUri(url);
      var labeled = d.labeled.map(function(l){
        return new faceapi.LabeledFaceDescriptors(l.label, l.desc.map(function(a){ return new Float32Array(a); }));
      });
      matcher = new faceapi.FaceMatcher(labeled, 0.5);
      opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });
      ready = true;
      self.postMessage({ type:'ready', backend: faceapi.tf.getBackend() });
    } catch(err){ self.postMessage({ type:'error', message: String((err && err.message) || err) }); }
  } else if(d.type === 'frame'){
    if(!ready){ self.postMessage({ type:'result', faces: [] }); return; }
    try {
      var bmp = d.bitmap;
      var oc = new OffscreenCanvas(bmp.width, bmp.height);
      oc.getContext('2d').drawImage(bmp, 0, 0);
      if(bmp.close) bmp.close();
      var res = await faceapi.detectAllFaces(oc, opts).withFaceLandmarks().withFaceDescriptors();
      var faces = res.map(function(r){
        var m = matcher.findBestMatch(r.descriptor);
        var b = r.detection.box;
        return { x:b.x, y:b.y, width:b.width, height:b.height, label: (m.label !== 'unknown' ? m.label : null) };
      });
      self.postMessage({ type:'result', faces: faces });
    } catch(err){ self.postMessage({ type:'result', faces: [] }); }
  }
};
`;

function faceScan(data){
    return {
        loading:false, camOn:false, scanning:false, ready:false, busy:false,
        status:'Klik "Mulai Scan" untuk mengaktifkan kamera',
        students: data.map(s=>({ ...s, marked: s.status==='hadir', justMarked:false })),
        worker:null, stream:null, timer:null, scale:1,

        get hadirCount(){ return this.students.filter(s=>s.marked).length; },
        get enrolledCount(){ return this.students.filter(s=>s.desc && s.desc.length).length; },

        async start(){
            const enrolled = this.students.filter(s=>s.desc && s.desc.length);
            if(enrolled.length===0){ this.status='Belum ada siswa yang daftar wajah.'; showToast('Daftarkan wajah siswa dulu','error'); return; }
            this.loading=true; this.status='Mengaktifkan kamera...';
            try {
                // 1) kamera nyala dulu (resolusi rendah)
                this.stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:640}, height:{ideal:480}, frameRate:{ideal:15} } });
                const v=this.$refs.video; v.srcObject=this.stream;
                await new Promise(r=> v.onloadedmetadata = r); v.play();
                this.camOn=true;
                // 2) worker untuk AI
                this.status='Memuat model AI (sekali saja)...';
                const url = URL.createObjectURL(new Blob([FACE_WORKER_CODE], {type:'application/javascript'}));
                this.worker = new Worker(url);
                this.worker.onmessage = (e)=> this.onWorker(e.data);
                this.worker.onerror = ()=> { this.loading=false; this.status='Worker gagal dimuat.'; };
                this.worker.postMessage({ type:'init', modelUrl:'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model', labeled: enrolled.map(s=>({ label:s.uuid, desc:s.desc })) });
            } catch(e){
                this.loading=false; this.camOn=false;
                this.status='Gagal: '+(e.name==='NotAllowedError'?'akses kamera ditolak':e.message);
            }
        },

        onWorker(msg){
            if(msg.type==='ready'){
                this.loading=false; this.ready=true; this.scanning=true;
                this.status='Memindai (' + (msg.backend||'') + ')...';
                this.tick();
            } else if(msg.type==='error'){
                this.loading=false; this.status='Gagal model AI: '+msg.message;
                showToast('Gagal memuat AI: '+msg.message,'error');
            } else if(msg.type==='result'){
                this.busy=false;
                this.draw(msg.faces);
            }
        },

        async tick(){
            if(!this.scanning) return;
            if(!this.busy && this.ready){
                const v=this.$refs.video, vw=v.videoWidth, vh=v.videoHeight;
                if(vw){
                    try {
                        let bmp;
                        try { bmp = await createImageBitmap(v, { resizeWidth:320, resizeHeight:Math.round(vh*320/vw), resizeQuality:'low' }); }
                        catch(_){ bmp = await createImageBitmap(v); } // fallback bila opsi resize tak didukung
                        this.scale = vw / bmp.width;   // skala balik ke resolusi native
                        this.busy=true;
                        this.worker.postMessage({ type:'frame', bitmap:bmp }, [bmp]);
                    } catch(e){}
                }
            }
            const allDone = this.students.filter(s=>s.desc&&s.desc.length).every(s=>s.marked);
            this.timer=setTimeout(()=>this.tick(), allDone ? 1200 : 220);
        },

        draw(faces){
            const v=this.$refs.video, c=this.$refs.canvas, vw=v.videoWidth, vh=v.videoHeight;
            if(!vw) return;
            c.width=vw; c.height=vh;
            const ctx=c.getContext('2d'); ctx.clearRect(0,0,vw,vh);
            const sc=this.scale;
            faces.forEach(f=>{
                const x=f.x*sc, y=f.y*sc, bw=f.width*sc, bh=f.height*sc;
                let label='Tidak dikenal', color='#ef4444';
                if(f.label){ const s=this.students.find(z=>z.uuid===f.label); label=s?s.nama.split(' ')[0]:'?'; color='#10b981'; this.onMatch(f.label); }
                ctx.strokeStyle=color; ctx.lineWidth=3; ctx.strokeRect(x,y,bw,bh);
                ctx.font='bold 18px sans-serif'; const tw=ctx.measureText(label).width+12;
                ctx.fillStyle=color; ctx.fillRect(x, y-28, tw, 26);
                ctx.fillStyle='#fff'; ctx.fillText(label, x+6, y-9);
            });
        },

        onMatch(uuid){
            const s=this.students.find(x=>x.uuid===uuid);
            if(!s || s.marked) return;
            s.marked=true; s.justMarked=true;
            fetch('{{ route('absensi.mark') }}', {
                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                body: JSON.stringify({ id_siswa:uuid, id_kelas:'{{ $selectedKelas }}', tanggal:'{{ $tanggal }}', status:'hadir' })
            }).catch(()=>{});
            showToast(s.nama + ' ✓ Hadir');
            setTimeout(()=>{ s.justMarked=false; }, 1600);
        },

        stop(){
            this.scanning=false; this.ready=false; this.camOn=false;
            if(this.timer) clearTimeout(this.timer);
            if(this.worker){ this.worker.terminate(); this.worker=null; }
            if(this.stream){ this.stream.getTracks().forEach(t=>t.stop()); this.stream=null; }
            const c=this.$refs.canvas; if(c){ c.getContext('2d').clearRect(0,0,c.width,c.height); }
            this.status='Pemindaian dihentikan. '+this.hadirCount+' siswa hadir. Klik Mulai Scan untuk lanjut.';
        }
    }
}
</script>
@endpush
@endsection
