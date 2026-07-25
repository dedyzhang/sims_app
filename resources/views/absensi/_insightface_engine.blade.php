{{--
    Mesin InsightFace (SCRFD deteksi + ArcFace pengenalan) — PERCOBAAN, hanya aktif kalau
    setting face_engine = 'insightface'. Dipakai bersama di halaman scan kiosk & registrasi
    wajah siswa supaya logikanya SATU tempat (bukan disalin ke tiap halaman).

    PENTING: bagian ini WAJIB diuji langsung dgn kamera & wajah sungguhan sebelum dipakai
    serius — deteksi/alignment di sini ditulis manual dari spesifikasi model (bukan lewat
    library siap-pakai spt Human.js), jadi kalau ada salah hitung bisa "kelihatan jalan"
    (tak error) tapi diam-diam tak pernah mengenali siapa pun.

    Sumber model: immich-app/buffalo_s di Hugging Face, DIKUNCI ke satu commit (bukan "main"
    mengambang) — pelajaran dari masalah versi Human.js/Alpine yg pernah terjadi di aplikasi
    ini. I/O tensor di bawah diverifikasi LANGSUNG dari file .onnx (diunduh & dibaca pakai
    Python `onnx` package), bukan ditebak dari dokumentasi umum:
      Deteksi   input  : "input.1"  [1,3,H,W] float32 — dipakai 640x640
      Deteksi   output : 9 tensor, 3 stride (8,16,32) x {score,bbox,kps}, 2 anchor/lokasi
      Pengenalan input : "input.1"  [N,3,112,112] float32 (wajah SUDAH diluruskan)
      Pengenalan output: "516" [1,512] float32 (embedding mentah, belum dinormalisasi)

    Lisensi: model ini non-komersial (lisensi InsightFace asli) — HANYA utk pemakaian
    internal, jangan disertakan kalau aplikasi didistribusikan/dijual ke sekolah lain.
--}}
<script src="https://cdn.jsdelivr.net/npm/onnxruntime-web@1.27.0/dist/ort.min.js"></script>
<script>
const IF_COMMIT = '0ff1751885575e62e084dff70549ce24a11fa5dc';
const IF_DET_URL = `https://huggingface.co/immich-app/buffalo_s/resolve/${IF_COMMIT}/detection/model.onnx`;
const IF_REC_URL = `https://huggingface.co/immich-app/buffalo_s/resolve/${IF_COMMIT}/recognition/model.onnx`;
const IF_INPUT = 640;
const IF_REC_SIZE = 112;
const IF_STRIDES = [8, 16, 32];
const IF_ANCHORS_PER_LOC = 2;
const IF_DET_THRESH = 0.5;
const IF_NMS_THRESH = 0.4;
// Titik acuan wajah standar InsightFace utk hasil selaras 112x112 (mata kiri/kanan, hidung,
// sudut mulut kiri/kanan) — konstanta resmi dari insightface/utils/face_align.py.
const IF_REF_5PT = [[38.2946,51.6963],[73.5318,51.5014],[56.0252,71.7366],[41.5493,92.3655],[70.7299,92.2041]];

let ifDetSession=null, ifRecSession=null, ifReady=false, ifLoadingPromise=null;

async function loadInsightFace(){
    if(ifReady) return;
    if(ifLoadingPromise) return ifLoadingPromise;
    ifLoadingPromise = (async () => {
        ort.env.wasm.wasmPaths = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.27.0/dist/';
        [ifDetSession, ifRecSession] = await Promise.all([
            ort.InferenceSession.create(IF_DET_URL, { executionProviders:['wasm'] }),
            ort.InferenceSession.create(IF_REC_URL, { executionProviders:['wasm'] }),
        ]);
        ifReady = true;
    })();
    return ifLoadingPromise;
}

/** Siapkan 1 frame video jadi tensor 640x640 (letterbox: skala+pad hitam, isi aspek rasio asli). */
function ifPreprocessDetect(source, vw, vh){
    const scale = Math.min(IF_INPUT / vw, IF_INPUT / vh);
    const nw = Math.max(1, Math.round(vw * scale)), nh = Math.max(1, Math.round(vh * scale));
    if(!window._ifDetCv){ window._ifDetCv = document.createElement('canvas'); window._ifDetCtx = window._ifDetCv.getContext('2d', { willReadFrequently:true }); }
    const cv = window._ifDetCv, ctx = window._ifDetCtx;
    cv.width = IF_INPUT; cv.height = IF_INPUT;
    ctx.fillStyle = '#000'; ctx.fillRect(0, 0, IF_INPUT, IF_INPUT);
    ctx.drawImage(source, 0, 0, vw, vh, 0, 0, nw, nh);
    const { data } = ctx.getImageData(0, 0, IF_INPUT, IF_INPUT);
    // NCHW float32, (px-127.5)/128, urutan RGB — cocok dgn preprocessing resmi InsightFace
    // (cv2 BGR dibalik ke RGB via swapRB=True sebelum masuk model; canvas getImageData sudah
    // RGBA secara native jadi tak perlu dibalik lagi di sini).
    const plane = IF_INPUT * IF_INPUT;
    const chw = new Float32Array(3 * plane);
    for(let i=0, p=0; i<data.length; i+=4, p++){
        chw[p] = (data[i] - 127.5) / 128.0;
        chw[plane + p] = (data[i+1] - 127.5) / 128.0;
        chw[2*plane + p] = (data[i+2] - 127.5) / 128.0;
    }
    return { tensor: new ort.Tensor('float32', chw, [1,3,IF_INPUT,IF_INPUT]), scale };
}

/**
 * Decode output SCRFD 1 stride jadi daftar {score, box:[x1,y1,x2,y2], kps:[[x,y]x5]} dlm
 * ruang koordinat 640x640 (sebelum dibagi `scale` balik ke ukuran video asli). Mengikuti
 * algoritma resmi insightface/detection/scrfd/scrfd.py (anchor grid per-stride, 2 anchor per
 * lokasi dgn PUSAT SAMA — SCRFD tak pakai skala/rasio anchor seperti detektor lama).
 */
function ifDecodeStride(scoreArr, bboxArr, kpsArr, stride, featW, featH){
    const out = [];
    const bboxDim = 4, kpsDim = 10;
    let idx = 0;
    for(let y=0; y<featH; y++){
        for(let x=0; x<featW; x++){
            const cx = x * stride, cy = y * stride;
            for(let a=0; a<IF_ANCHORS_PER_LOC; a++){
                const score = scoreArr[idx];
                if(score >= IF_DET_THRESH){
                    const bo = idx * bboxDim;
                    const x1 = cx - bboxArr[bo]   * stride;
                    const y1 = cy - bboxArr[bo+1] * stride;
                    const x2 = cx + bboxArr[bo+2] * stride;
                    const y2 = cy + bboxArr[bo+3] * stride;
                    const ko = idx * kpsDim;
                    const kps = [];
                    for(let k=0; k<5; k++){
                        kps.push([cx + kpsArr[ko + k*2] * stride, cy + kpsArr[ko + k*2 + 1] * stride]);
                    }
                    out.push({ score, box:[x1,y1,x2,y2], kps });
                }
                idx++;
            }
        }
    }
    return out;
}

function ifIou(a, b){
    const x1=Math.max(a[0],b[0]), y1=Math.max(a[1],b[1]), x2=Math.min(a[2],b[2]), y2=Math.min(a[3],b[3]);
    const inter = Math.max(0, x2-x1) * Math.max(0, y2-y1);
    const areaA = (a[2]-a[0])*(a[3]-a[1]), areaB = (b[2]-b[0])*(b[3]-b[1]);
    return inter / (areaA + areaB - inter + 1e-6);
}
function ifNms(dets){
    dets.sort((a,b)=> b.score - a.score);
    const keep = [];
    const used = new Array(dets.length).fill(false);
    for(let i=0; i<dets.length; i++){
        if(used[i]) continue;
        keep.push(dets[i]);
        for(let j=i+1; j<dets.length; j++){
            if(!used[j] && ifIou(dets[i].box, dets[j].box) > IF_NMS_THRESH) used[j] = true;
        }
    }
    return keep;
}

/** Lebar/tinggi sumber gambar — dukung baik elemen <video> maupun <canvas> (dipakai halaman
 *  registrasi yg memproses frame lewat kanvas offscreen utk pencerahan otomatis dulu). */
function ifSourceSize(source){
    return [source.videoWidth || source.width || 0, source.videoHeight || source.height || 0];
}

/** Deteksi semua wajah pada 1 frame (video ATAU canvas). Balikan koordinat di ruang ASLI sumber. */
async function ifDetectFaces(source){
    const [vw, vh] = ifSourceSize(source);
    if(!vw || !vh) return [];
    const { tensor, scale } = ifPreprocessDetect(source, vw, vh);
    const results = await ifDetSession.run({ 'input.1': tensor });
    // Nama output tensor terverifikasi dari file model (lihat komentar atas file).
    const pairs = [
        { score:'443', bbox:'446', kps:'449', stride:8  },
        { score:'468', bbox:'471', kps:'474', stride:16 },
        { score:'493', bbox:'496', kps:'499', stride:32 },
    ];
    let all = [];
    for(const p of pairs){
        const featSize = IF_INPUT / p.stride;
        const dets = ifDecodeStride(
            results[p.score].data, results[p.bbox].data, results[p.kps].data,
            p.stride, featSize, featSize
        );
        all = all.concat(dets);
    }
    const kept = ifNms(all);
    // Balik dari ruang 640x640 ke ukuran video asli.
    return kept.map(d => ({
        score: d.score,
        box: d.box.map(v => v / scale),
        kps: d.kps.map(([x,y]) => [x/scale, y/scale]),
    }));
}

/**
 * Transform similaritas 2D (skala+rotasi+translasi, TANPA cermin/shear) dari 5 titik wajah
 * terdeteksi ke 5 titik acuan standar — bentuk tertutup least-squares (setara Umeyama utk
 * kasus 2D non-refleksi), bukan lewat SVD. Diturunkan dari sistem normal 4-parameter
 * (a,b,tx,ty) yg merepresentasikan [x'] = a*x - b*y + tx ; [y'] = b*x + a*y + ty.
 */
function ifSimilarityTransform(src5, dst5){
    const n = src5.length;
    let sx=0, sy=0, dx=0, dy=0;
    for(let i=0;i<n;i++){ sx+=src5[i][0]; sy+=src5[i][1]; dx+=dst5[i][0]; dy+=dst5[i][1]; }
    sx/=n; sy/=n; dx/=n; dy/=n;
    let num=0, den=0;
    for(let i=0;i<n;i++){
        const ux = src5[i][0]-sx, uy = src5[i][1]-sy;
        const vx = dst5[i][0]-dx, vy = dst5[i][1]-dy;
        num += ux*vx + uy*vy;
        den += ux*ux + uy*uy;
    }
    const a = den > 1e-6 ? num/den : 1;
    let numB=0;
    for(let i=0;i<n;i++){
        const ux = src5[i][0]-sx, uy = src5[i][1]-sy;
        const vx = dst5[i][0]-dx, vy = dst5[i][1]-dy;
        numB += ux*vy - uy*vx;
    }
    const b = den > 1e-6 ? numB/den : 0;
    const tx = dx - a*sx + b*sy;
    const ty = dy - b*sx - a*sy;
    return { a, b, tx, ty };
}

/** Luruskan & potong wajah 112x112 dari sumber (video ATAU canvas) pakai 5 titik landmark hasil deteksi. */
function ifAlignFace(source, kps){
    const { a, b, tx, ty } = ifSimilarityTransform(kps, IF_REF_5PT);
    const [sw, sh] = ifSourceSize(source);
    if(!window._ifAlignCv){ window._ifAlignCv = document.createElement('canvas'); window._ifAlignCtx = window._ifAlignCv.getContext('2d', { willReadFrequently:true }); }
    const cv = window._ifAlignCv, ctx = window._ifAlignCtx;
    cv.width = IF_REC_SIZE; cv.height = IF_REC_SIZE;
    ctx.save();
    ctx.clearRect(0, 0, IF_REC_SIZE, IF_REC_SIZE);
    // Canvas transform matrix [a c e; b d f] memetakan koordinat SUMBER ke tujuan
    // (kanvas 112x112) — persis transform (a,b,tx,ty) yg dihitung di atas.
    ctx.setTransform(a, b, -b, a, tx, ty);
    ctx.drawImage(source, 0, 0, sw, sh);
    ctx.restore();
    return cv;
}

/** Embedding ArcFace 512-d (L2-normalized) dari 1 wajah yg SUDAH diluruskan (kanvas 112x112). */
async function ifEmbedFace(alignedCanvas){
    const ctx = alignedCanvas.getContext('2d', { willReadFrequently:true });
    const { data } = ctx.getImageData(0, 0, IF_REC_SIZE, IF_REC_SIZE);
    const plane = IF_REC_SIZE * IF_REC_SIZE;
    const chw = new Float32Array(3 * plane);
    for(let i=0, p=0; i<data.length; i+=4, p++){
        chw[p] = (data[i] - 127.5) / 128.0;
        chw[plane + p] = (data[i+1] - 127.5) / 128.0;
        chw[2*plane + p] = (data[i+2] - 127.5) / 128.0;
    }
    const tensor = new ort.Tensor('float32', chw, [1,3,IF_REC_SIZE,IF_REC_SIZE]);
    const results = await ifRecSession.run({ 'input.1': tensor });
    const raw = results['516'].data;
    let norm = 0;
    for(let i=0; i<raw.length; i++) norm += raw[i]*raw[i];
    norm = Math.sqrt(norm) || 1;
    const out = new Array(raw.length);
    for(let i=0; i<raw.length; i++) out[i] = raw[i] / norm;
    return out;
}

/** Deteksi + luruskan + embedding utk SEMUA wajah pada 1 frame (video/canvas) — dipakai scan & registrasi. */
async function ifDetectAndEmbed(source){
    const faces = await ifDetectFaces(source);
    const out = [];
    for(const f of faces){
        const aligned = ifAlignFace(source, f.kps);
        const embedding = await ifEmbedFace(aligned);
        out.push({ box: f.box, score: f.score, embedding, kps: f.kps });
    }
    return out;
}

/** Cosine similarity 2 embedding yg SUDAH dinormalisasi (tinggal dot product). */
function ifCosineSim(a, b){
    let dot = 0;
    const n = Math.min(a.length, b.length);
    for(let i=0; i<n; i++) dot += a[i]*b[i];
    return dot;
}

/**
 * Adapter: hasil ifDetectAndEmbed() dibentuk PERSIS spt hasil human.detect() ({face:[{box:
 * [x,y,w,h], embedding, faceScore}]}) supaya faceScan()'s render() di scan.blade.php bisa
 * dipakai TANPA PERUBAHAN sama sekali utk kedua mesin — cuma titik pemanggilan deteksi yg beda.
 * `kps` (5 landmark) ikut disertakan (field TAMBAHAN, bukan bagian bentuk Human.js) supaya
 * halaman registrasi bisa hitung proksi "seberapa menghadap depan" — InsightFace tak
 * menyediakan face.rotation.angle.yaw spt Human.js.
 */
async function ifDetect(source){
    const faces = await ifDetectAndEmbed(source);
    return {
        face: faces.map(f => ({
            box: [f.box[0], f.box[1], f.box[2]-f.box[0], f.box[3]-f.box[1]], // x1,y1,x2,y2 -> x,y,w,h
            embedding: f.embedding,
            faceScore: f.score,
            kps: f.kps,
        })),
    };
}
</script>
