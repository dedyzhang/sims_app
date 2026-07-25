{{--
    Logika bersama utk 3 halaman registrasi wajah (absensi/wajah.blade.php, absensi/wajah-guru.blade.php,
    face/self.blade.php): klasifikasi 5 zona sudut (tengah/kiri/kanan/atas/bawah) dari yaw+pitch, dan
    cek posisi/jarak wajah di dalam lingkaran panduan. Dulu disalin manual ke 3 file terpisah — sekarang
    1 tempat supaya kalibrasi ambang (belum diverifikasi kamera sungguhan) tak bisa ketinggalan sinkron
    di salah satu halaman.

    Dipakai via Object.assign ke Alpine data tiap halaman:
        return Object.assign(faceEnrollShared(), { ...state khusus halaman... });
--}}
<script>
const ANGLE_ZONES = ['tengah','kiri','kanan','atas','bawah'];

function faceEnrollShared(){
    return {
        // Variasi sudut wajib: tiap sampel diklasifikasi ke salah satu dari 5 zona (ANGLE_ZONES)
        // dari yaw+pitch BERTANDA, dibatasi maks 1 sampel per zona secara real-time (lihat
        // capture() pemanggil) — 5 zona x 1 = pas 5 target sampel, jadi begitu 5 sampel diterima,
        // ke-5 zona PASTI sudah terisi. Wajib ke-5 zona terisi sebelum bisa Simpan.
        zoneCounts:{tengah:0, kiri:0, kanan:0, atas:0, bawah:0},

        // Ambang & arah kiri/kanan/atas/bawah ini perkiraan awal (belum dikalibrasi dgn kamera
        // sungguhan, terutama utk InsightFace yg proksinya beda satuan dari radian Human.js) —
        // tujuan utamanya BUKAN akurasi derajat, cuma memaksa 5 pose yg jelas berbeda satu sama
        // lain supaya tidak semua sampel wajah lurus/nyaris sama. Dipilih SUMBU yg dominan (yaw
        // vs pitch) supaya 1 gerakan jelas = 1 zona, bukan campuran diagonal yg rancu.
        classifyAngle(signedYaw, signedPitch){
            const CENTER = 0.12;
            const yawAbs = Math.abs(signedYaw), pitchAbs = Math.abs(signedPitch);
            if(yawAbs < CENTER && pitchAbs < CENTER) return 'tengah';
            if(yawAbs >= pitchAbs) return signedYaw < 0 ? 'kiri' : 'kanan';
            return signedPitch < 0 ? 'atas' : 'bawah';
        },
        zoneLabel(z){
            return { tengah:'Tengah', kiri:'Kiri', kanan:'Kanan', atas:'Atas', bawah:'Bawah' }[z] || z;
        },

        // Cek wajah di dalam lingkaran panduan (posisi & jarak) — dipanggil SETELAH faceQuality()
        // lolos (yg cuma cek "terlalu jauh"). video ditampilkan via CSS object-cover (meng-crop
        // salah satu sisi resolusi native kamera supaya pas kontainer) — kalau posisi/jarak dicek
        // langsung terhadap videoWidth/videoHeight PENUH, batas terima/tolak tak sesuai dgn yg user
        // lihat di layar (bagian yg sudah ter-crop ikut terhitung). Di bawah ini dihitung area yg
        // BENAR-BENAR terlihat (pakai clientWidth/clientHeight elemen video) baru posisi/jarak
        // wajah dibandingkan terhadap area itu. Ambang 0.78/0.16/0.20 sendiri tetap perkiraan awal.
        checkFramed(face, video){
            const [bx,by,bw,bh] = face.box;
            const vw = video.videoWidth, vh = video.videoHeight;
            if(!vw || !vh) return { ok:true };
            const cw = video.clientWidth || vw, ch = video.clientHeight || vh;
            const scale = Math.max(cw / vw, ch / vh);
            const visW = cw / scale, visH = ch / scale;
            const offX = (vw - visW) / 2, offY = (vh - visH) / 2;

            if(bh > visH * 0.78) return { ok:false, msg:'Wajah terlalu dekat ke kamera. Mundur sedikit.' };
            const cx = bx+bw/2, cy = by+bh/2;
            const dx = Math.abs(cx - (offX + visW/2)) / visW;
            const dy = Math.abs(cy - (offY + visH/2)) / visH;
            if(dx > 0.16 || dy > 0.20) return { ok:false, msg:'Posisikan wajah di tengah lingkaran panduan.' };
            return { ok:true };
        },
    };
}
</script>
