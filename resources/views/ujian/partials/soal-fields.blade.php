{{-- Dipakai di dalam <form x-data="soalForm({...})">...</form> (lihat ujian/edit.blade.php).
     teks_soal & opsi mcq/mcq_complex pakai TinyMCE (rumus + upload gambar) — lihat
     ujian/partials/rich-editor.blade.php. x-model SENGAJA tidak dipakai utk field2 itu krn
     TinyMCE mengambil alih DOM textarea-nya; nilai awal dibaca sekali lewat x-text, lalu
     TinyMCE sendiri yang menyinkronkan isi editor ke textarea saat form di-submit. --}}
<div class="grid sm:grid-cols-[1fr_100px] gap-3">
    <div>
        <label class="form-label">Tipe Soal</label>
        <select name="tipe" x-model="tipe" class="form-select"
                @change="tipe==='true_false' && resetTrueFalse(); $nextTick(() => window.UjianEditor && window.UjianEditor.mountAll())">
            <option value="mcq">Pilihan Ganda</option>
            <option value="mcq_complex">Pilihan Ganda Kompleks</option>
            <option value="true_false">Benar/Salah</option>
            <option value="match">Mencocokkan</option>
            <option value="essay">Esai</option>
        </select>
    </div>
    <div>
        <label class="form-label">Poin</label>
        <input type="number" name="poin" x-model.number="poin" min="1" max="100" class="form-input">
    </div>
</div>

<div>
    <label class="form-label">Teks Soal <span class="text-slate-400 font-normal">(bisa sisip rumus &amp; gambar)</span></label>
    {{-- SENGAJA tanpa `required`: TinyMCE menyembunyikan textarea aslinya (display:none) dan
         baru menyinkronkan isi ke situ lewat listener 'submit'-nya sendiri, yg jalan SETELAH
         validasi native browser — kalau `required` dipasang, submit form akan diblokir diam2
         oleh browser krn textarea (yg tersembunyi) masih kosong di titik validasi. Wajib-isi
         tetap ditegakkan di server (UjianSoalController::validateSoal(), 'required|string').
         Pola ini SAMA dgn classroom/partials/editor.blade.php yg juga sengaja tanpa required. --}}
    <textarea name="teks_soal" x-text="teks_soal" :id="'teks-soal-'+_uid" class="ujian-rich-editor" rows="4"></textarea>
</div>

{{-- mcq / mcq_complex / true_false: daftar opsi --}}
<div x-show="tipe==='mcq' || tipe==='mcq_complex' || tipe==='true_false'" x-cloak class="space-y-3">
    <label class="form-label" x-text="tipe==='mcq_complex' ? 'Opsi (centang SEMUA yang benar)' : 'Opsi (pilih satu yang benar)'"></label>
    <template x-for="(o, i) in opsi" :key="o._key">
        <div class="space-y-1.5 pb-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
            <div class="flex items-center gap-2">
                {{-- SENGAJA x-model (bukan :checked + @click/@change): dicoba :checked+@click.prevent
                     dulu — data Alpine benar tapi checkbox SUNGGUHAN tetap tak tercentang krn
                     "canceled activation steps" bawaan browser membalikkan `checked` SETELAH
                     preventDefault(). Diganti :checked+@change (tanpa prevent) — masih gagal jg
                     utk kasus LAIN: begitu checkbox pernah "dirty" (pernah disentuh user/klik
                     sekali saja), :checked Alpine ternyata menulis ATRIBUT `checked` (bukan
                     properti `.checked` langsung) — dan per spek HTML, ATRIBUT checkbox yg
                     sudah dirty TIDAK LAGI memengaruhi properti live-nya, jadi reset via kode
                     (mis. ganti tipe soal ke Benar/Salah) gagal diam2 pada checkbox yg SUDAH
                     pernah diklik user. x-model Alpine menulis PROPERTI `.checked` langsung
                     (bukan atribut) — kebal thd masalah dirty-flag ini, pola dua-arah standar
                     Alpine yg sudah teruji. Logika "hanya satu opsi benar" (mcq/true_false)
                     dijalankan SETELAH x-model menuliskan benar=true via @change. --}}
                <input type="checkbox"
                       :name="'opsi['+i+'][benar]'" value="1"
                       x-model="o.benar"
                       @change="tipe!=='mcq_complex' && o.benar && opsi.forEach((x,idx) => { if (idx !== i) x.benar = false })"
                       class="rounded text-primary focus:ring-primary flex-shrink-0">
                <span x-show="tipe==='true_false'" class="text-sm text-slate-600 dark:text-slate-300" x-text="o.teks"></span>
                <button type="button" x-show="tipe!=='true_false' && opsi.length > 2" @click="removeOpsi(i)" class="text-rose-400 hover:text-rose-600 flex-shrink-0 ml-auto">
                    <i data-lucide="x" class="w-4 h-4"></i> <span class="text-xs">Hapus opsi</span>
                </button>
            </div>
            {{-- true_false: label tetap "Benar"/"Salah", tak pernah diedit — kirim via hidden input.
                 SENGAJA x-if (bukan x-show) utk pasangan hidden-input/textarea di bawah ini —
                 keduanya BERBAGI `name="opsi[i][teks]"` yg sama. x-show cuma toggle CSS
                 display:none, TIDAK melepas elemen dari DOM — dan display:none TIDAK
                 mengecualikan field dari form submission. Akibatnya saat tipe=true_false,
                 KEDUANYA ikut ter-submit dgn name yg sama: textarea (yg pernah ter-mount jadi
                 TinyMCE waktu tipe masih mcq, lalu disembunyikan tanpa pernah di-unmount) ikut
                 menuliskan `value`-nya sendiri (kosong, krn TinyMCE cuma sinkron ke textarea
                 SAAT event 'submit', dan tak pernah diedit) SETELAH hidden input dlm urutan
                 DOM — dan PHP/Laravel utk key array duplikat pakai nilai TERAKHIR, jadi
                 textarea kosong itu menimpa "Benar"/"Salah" dari hidden input, bikin submit
                 gagal "field is required" walau checkbox & data Alpine sudah benar. x-if
                 melepas elemen yg tak aktif dari DOM sepenuhnya — tak ada lagi duplikasi name. --}}
            <template x-if="tipe==='true_false'">
                <input type="hidden" :name="'opsi['+i+'][teks]'" :value="o.teks">
            </template>
            <template x-if="tipe!=='true_false'">
                <textarea :name="'opsi['+i+'][teks]'" x-cloak
                          :id="'opsi-editor-'+_uid+'-'+o._key" x-text="o.teks" class="ujian-rich-editor" rows="2"></textarea>
            </template>
        </div>
    </template>
    <button type="button" x-show="tipe!=='true_false'" @click="addOpsi()" class="text-xs text-primary hover:underline">+ Tambah opsi</button>
</div>

{{-- match: pasangan kiri-kanan, pakai TinyMCE (bisa sisip rumus) spt teks_soal/opsi.
     SENGAJA tanpa `required` (lihat catatan di teks_soal di atas) — field ini tersembunyi
     lewat x-show saat tipe != match, dan browser TIDAK konsisten mengecualikan elemen di
     dalam ancestor x-show=false dari constraint validation, jadi `required` di sini akan
     diam2 memblokir submit soal tipe LAIN (mcq/essay/dst). Wajib-isi tetap ditegakkan
     server (required_if:tipe,match). --}}
<div x-show="tipe==='match'" x-cloak class="space-y-3">
    <label class="form-label">Pasangan (kiri dicocokkan dengan kanan) <span class="text-slate-400 font-normal">(bisa sisip rumus)</span></label>
    <template x-for="(p, i) in pasangan" :key="p._key">
        <div class="flex items-start gap-2">
            <div class="flex-1 min-w-0">
                <textarea :name="'pasangan['+i+'][kiri]'" :id="'pasangan-kiri-'+_uid+'-'+p._key" x-text="p.kiri" class="ujian-rich-editor" rows="2"></textarea>
            </div>
            <i data-lucide="arrow-right" class="w-4 h-4 text-slate-400 flex-shrink-0 mt-3"></i>
            <div class="flex-1 min-w-0">
                <textarea :name="'pasangan['+i+'][kanan]'" :id="'pasangan-kanan-'+_uid+'-'+p._key" x-text="p.kanan" class="ujian-rich-editor" rows="2"></textarea>
            </div>
            <button type="button" x-show="pasangan.length > 2" @click="removePasangan(i)" class="text-rose-400 hover:text-rose-600 flex-shrink-0 mt-3">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    </template>
    <button type="button" @click="addPasangan()" class="text-xs text-primary hover:underline">+ Tambah pasangan</button>
</div>

{{-- essay: kunci jawaban opsional (referensi guru saat menilai, TIDAK auto-grade) --}}
<div x-show="tipe==='essay'" x-cloak>
    <label class="form-label">Kunci Jawaban / Rubrik (opsional, referensi saat menilai manual)</label>
    <textarea name="kunci_esai" x-model="kunci_esai" rows="2" class="form-input"></textarea>
</div>

<div>
    <label class="form-label">Pembahasan (opsional, ditampilkan ke siswa kalau diaktifkan)</label>
    <textarea name="penjelasan" x-model="penjelasan" rows="2" class="form-input"></textarea>
</div>

@include('ujian.partials.rich-editor')
