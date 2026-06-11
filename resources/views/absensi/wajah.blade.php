@extends('layouts.app')
@section('title', 'Registrasi Wajah')

@section('content')
<div class="space-y-5" x-data="faceEnroll()">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Registrasi Wajah Siswa</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Daftarkan wajah tiap siswa untuk absensi otomatis</p>
        </div>
        <a href="{{ route('absensi.scan', ['kelas'=>$selectedKelas]) }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="scan-face" class="w-4 h-4"></i> Mulai Absen Scan
        </a>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('absensi.wajah') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selectedKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        @php $terdaftar = $siswas->whereNotNull('face_descriptor')->count(); @endphp
        <div class="text-sm text-slate-500">
            <span class="font-bold text-primary">{{ $terdaftar }}</span> / {{ $siswas->count() }} wajah terdaftar
        </div>
    </form>

    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="users" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada siswa di kelas ini.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($siswas as $s)
        <div class="card p-3.5 flex items-center gap-3">
            <div class="w-11 h-11 rounded-full grid place-items-center text-white font-bold flex-shrink-0 relative" style="background:{{ $s->jk==='L' ? 'var(--cp)' : '#ec4899' }}">
                {{ strtoupper(substr($s->nama,0,1)) }}
                @if($s->face_descriptor)
                <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-emerald-500 grid place-items-center ring-2 ring-white"><i data-lucide="check" class="w-2.5 h-2.5 text-white"></i></span>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $s->nama }}</p>
                @if($s->face_descriptor)
                <p class="text-xs text-emerald-600 flex items-center gap-1"><i data-lucide="badge-check" class="w-3 h-3"></i> Terdaftar</p>
                @else
                <p class="text-xs text-slate-400">Belum daftar wajah</p>
                @endif
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
                <button @click="openFor('{{ $s->uuid }}', @js($s->nama))" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-primary-50 text-primary hover:bg-primary-100 transition flex items-center gap-1">
                    <i data-lucide="camera" class="w-3.5 h-3.5"></i> {{ $s->face_descriptor ? 'Ulangi' : 'Daftar' }}
                </button>
                @if($s->face_descriptor)
                <button @click="hapus('{{ $s->uuid }}')" class="p-1.5 rounded-lg hover:bg-rose-50 text-rose-500" title="Hapus wajah"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
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
                <div class="relative rounded-2xl overflow-hidden bg-slate-900 aspect-[4/3]">
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
                        <template x-for="i in 3"><span class="w-3 h-3 rounded-full transition" :class="samples.length>=i ? 'bg-emerald-400' : 'bg-white/40'"></span></template>
                    </div>
                </div>
                <p class="text-center text-sm" :class="msgErr ? 'text-rose-500' : 'text-slate-500'" x-text="msg"></p>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-700 flex gap-2 justify-end">
                <button @click="close()" class="px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Batal</button>
                <button @click="capture()" :disabled="!streaming || capturing || samples.length>=3" class="px-4 py-2 rounded-xl text-sm font-semibold border border-primary text-primary hover:bg-primary-50 transition flex items-center gap-1.5 disabled:opacity-40">
                    <i data-lucide="aperture" class="w-4 h-4"></i> Ambil Sampel (<span x-text="samples.length"></span>/3)
                </button>
                <button @click="save()" :disabled="samples.length<1 || saving" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 disabled:opacity-40">
                    <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin" x-show="saving"></i><span x-text="saving?'Menyimpan...':'Simpan'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"></script>
<script>
const FACE_MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model';
let faceModelsLoaded = false;
async function loadFaceModels(){
    if(faceModelsLoaded) return;
    try { await faceapi.tf.setBackend('webgl'); await faceapi.tf.ready(); } catch(e){}
    await faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODEL_URL);
    await faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODEL_URL);
    await faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODEL_URL);
    faceModelsLoaded = true;
}

function faceEnroll(){
    return {
        modal:false, loading:false, streaming:false, capturing:false, saving:false,
        uuid:null, nama:'', samples:[], stream:null, status:'', msg:'', msgErr:false,

        async openFor(uuid, nama){
            this.uuid=uuid; this.nama=nama; this.samples=[]; this.msg=''; this.msgErr=false;
            this.modal=true; this.streaming=false; this.loading=true; this.status='Memuat model AI...';
            try {
                await loadFaceModels();
                this.status='Mengaktifkan kamera...';
                this.stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:640}, height:{ideal:480} } });
                this.$refs.video.srcObject = this.stream;
                this.loading=false; this.streaming=true;
                this.msg='Posisikan wajah dalam bingkai, lalu klik Ambil Sampel.';
            } catch(e){
                this.loading=false;
                this.status='Gagal: ' + (e.name==='NotAllowedError' ? 'akses kamera ditolak' : e.message);
            }
        },
        async capture(){
            this.capturing=true; this.msg='Mendeteksi wajah...'; this.msgErr=false;
            try {
                const det = await faceapi.detectSingleFace(this.$refs.video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.45 }))
                    .withFaceLandmarks().withFaceDescriptor();
                if(det){
                    this.samples.push(Array.from(det.descriptor));
                    this.msg = 'Sampel ' + this.samples.length + ' tersimpan. ' + (this.samples.length<3 ? 'Ambil lagi dari sudut berbeda.' : 'Cukup, klik Simpan.');
                    this.msgErr=false;
                } else {
                    this.msg='Wajah tidak terdeteksi. Pastikan pencahayaan cukup & wajah menghadap kamera.';
                    this.msgErr=true;
                }
            } catch(e){ this.msg='Error: '+e.message; this.msgErr=true; }
            this.capturing=false;
        },
        async save(){
            this.saving=true;
            try {
                const res = await fetch(`/siswa/${this.uuid}/wajah`, {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ descriptors: this.samples })
                });
                const data = await res.json();
                if(res.ok){ showToast(data.message||'Wajah terdaftar.'); this.close(); setTimeout(()=>location.reload(),700); }
                else { showToast('Gagal menyimpan','error'); }
            } catch { showToast('Gagal menghubungi server','error'); }
            this.saving=false;
        },
        hapus(uuid){
            $.confirm({ title:'Hapus data wajah?', content:'Siswa perlu daftar ulang untuk absen scan.', type:'red',
                buttons:{ hapus:{ text:'Hapus', btnClass:'btn-red', action: async ()=>{
                    const res = await fetch(`/siswa/${uuid}/wajah`, { method:'DELETE', headers:{'X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'} });
                    if(res.ok){ showToast('Data wajah dihapus.'); setTimeout(()=>location.reload(),600); }
                } }, batal:{text:'Batal'} } });
        },
        close(){
            this.modal=false;
            if(this.stream){ this.stream.getTracks().forEach(t=>t.stop()); this.stream=null; }
            this.streaming=false;
        }
    }
}
</script>
@endpush
@endsection
