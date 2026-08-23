{{-- Dipakai di dalam <form x-data="soalForm({...})">...</form> (lihat ujian/edit.blade.php).
     teks_soal & opsi mcq/mcq_complex pakai TinyMCE (rumus + upload gambar) — lihat
     ujian/partials/rich-editor.blade.php. x-model SENGAJA tidak dipakai utk field2 itu krn
     TinyMCE mengambil alih DOM textarea-nya; nilai awal dibaca sekali lewat x-text, lalu
     TinyMCE sendiri yang menyinkronkan isi editor ke textarea saat form di-submit. --}}
<div class="grid sm:grid-cols-[1fr_100px] gap-3">
    <div>
        <label class="form-label">Tipe Soal</label>
        <select name="tipe" x-model="tipe" class="form-select"
                @change="tipe==='true_false' && resetTrueFalse();
                         tipe==='match' && (skor_mode='proporsional');
                         tipe==='mcq_complex' && (skor_mode='all_or_nothing');
                         $nextTick(() => window.UjianEditor && window.UjianEditor.mountAll())">
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
        <div class="space-y-1.5 pb-3 border-b-2 border-slate-200 dark:border-slate-600 last:border-0 last:pb-0">
            {{-- Judul "Opsi A/B/C/..." di tiap pembatas — sejajar huruf badge yg dilihat
                 siswa (lihat kerjakan.blade.php), supaya guru jelas sedang edit opsi yg mana.
                 Tak relevan utk true_false (cuma "Benar"/"Salah" tetap, bukan opsi A/B/C). --}}
            <p x-show="tipe!=='true_false'" class="text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500" x-text="'Opsi ' + String.fromCharCode(65 + i)"></p>
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
                       class="w-5 h-5 rounded-md border-2 border-slate-300 dark:border-slate-600 text-primary focus:ring-2 focus:ring-primary/30 transition cursor-pointer flex-shrink-0">
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
     server (required_if:tipe,match).
     Kiri & kanan SENGAJA bertumpuk (flex-col) di mobile, sejajar (sm:flex-row) di layar
     lebar — dua TinyMCE penuh toolbar berdampingan di layar sempit membuat tiap editor
     cuma dapat ~150px, toolbar-nya jadi patah/pecah bertingkat dan area ketik nyaris tak
     bisa dipakai. Panah "→" diputar jadi "↓" (rotate-90) di mobile, tanpa perlu ikon kedua.
     Pembatas putus-putus di sekitar panah — garis horizontal di mobile (memisahkan editor
     kiri/kanan yg bertumpuk), garis vertikal (border-l, sejajar tinggi via self-stretch) di
     layar lebar — supaya jarak antar dua editor tak terlihat kosong tanpa penanda. --}}
<div x-show="tipe==='match'" x-cloak class="space-y-3">
    <label class="form-label">Pasangan (kiri dicocokkan dengan kanan) <span class="text-slate-400 font-normal">(bisa sisip rumus)</span></label>
    <template x-for="(p, i) in pasangan" :key="p._key">
        <div class="flex flex-col sm:flex-row sm:items-start gap-2 pb-3 border-b border-slate-100 dark:border-slate-700 last:border-0 last:pb-0">
            <div class="w-full sm:flex-1 sm:min-w-0">
                <textarea :name="'pasangan['+i+'][kiri]'" :id="'pasangan-kiri-'+_uid+'-'+p._key" x-text="p.kiri" class="ujian-rich-editor" rows="2"></textarea>
            </div>
            <div class="flex justify-center items-center border-t-2 border-dashed border-slate-300 dark:border-slate-500 pt-2 sm:self-stretch sm:border-t-0 sm:border-l-2 sm:pt-0 sm:pl-2 sm:mt-3">
                <i data-lucide="arrow-right" class="w-4 h-4 text-slate-400 flex-shrink-0 rotate-90 sm:rotate-0"></i>
            </div>
            <div class="w-full sm:flex-1 sm:min-w-0">
                <textarea :name="'pasangan['+i+'][kanan]'" :id="'pasangan-kanan-'+_uid+'-'+p._key" x-text="p.kanan" class="ujian-rich-editor" rows="2"></textarea>
            </div>
            <button type="button" x-show="pasangan.length > 2" @click="removePasangan(i)" class="text-rose-400 hover:text-rose-600 flex-shrink-0 self-end sm:self-auto sm:mt-3">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    </template>
    <button type="button" @click="addPasangan()" class="text-xs text-primary hover:underline">+ Tambah pasangan</button>
</div>

{{-- mcq_complex & match: cara penilaian — semua-benar-baru-dapat-poin (all_or_nothing:
     "Poin" adalah TOTAL soal apa adanya, tak dikali jumlah opsi/pasangan, krn semua-atau-
     tidak-sama-sekali tak punya konsep "per item"), atau poin sesuai jumlah yg benar
     (proporsional: "Poin" berarti poin PER opsi/pasangan benar, preview total dihitung
     live dari poin × jumlah opsi/pasangan benar, supaya guru tak salah kira poin yg
     diinput = total soal). --}}
<div x-show="tipe==='mcq_complex' || tipe==='match'" x-cloak>
    <label class="form-label">Cara Penilaian</label>
    <select name="skor_mode" x-model="skor_mode" class="form-select">
        <option value="all_or_nothing">Semua benar baru dapat poin</option>
        <option value="proporsional">Poin sesuai jumlah yang benar</option>
    </select>
    <template x-if="skor_mode === 'all_or_nothing'">
        <p class="text-xs text-slate-400 mt-1.5">
            Siswa dapat <span class="font-semibold text-slate-600 dark:text-slate-300" x-text="poin"></span> poin HANYA kalau
            <span x-text="tipe==='mcq_complex' ? 'semua opsi benar dipilih tanpa ada yang salah' : 'semua pasangan cocok'"></span>,
            kalau tidak dapat 0.
        </p>
    </template>
    <template x-if="skor_mode === 'proporsional'">
        <p class="text-xs text-slate-400 mt-1.5">
            "Poin" di atas adalah poin PER <span x-text="tipe==='mcq_complex' ? 'opsi benar' : 'pasangan'"></span> —
            total poin soal ini: <span class="font-semibold text-slate-600 dark:text-slate-300" x-text="poin * (tipe==='mcq_complex' ? opsi.filter(o => o.benar).length : pasangan.length)"></span>
        </p>
    </template>
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
