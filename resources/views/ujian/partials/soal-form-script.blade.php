{{-- Dipakai bersama oleh ujian/edit.blade.php (susun soal ujian) DAN
     bank-soal/show.blade.php (susun soal bank per-mapel) — bentuk soal identik
     (mcq/mcq_complex/true_false/match/essay), cuma beda induk. @once cegah
     duplikat kalau suatu saat ke-include dua kali di halaman yg sama. --}}
@once
<script>
function ujianUid() {
    return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
}

function soalForm(init) {
    return {
        teks_soal: '',
        penjelasan: '',
        kunci_esai: '',
        ...init,
        rendered: init.open || false,
        loadingData: false,
        async toggle() {
            if (!this.open && !this.rendered && this.fetchUrl) {
                this.loadingData = true;
                this.open = true;
                try {
                    const res = await fetch(this.fetchUrl, { headers: { 'Accept': 'application/json' }});
                    if (!res.ok) throw new Error('Network error');
                    const data = await res.json();
                    this.teks_soal = data.teks_soal;
                    this.penjelasan = data.penjelasan;
                    this.kunci_esai = data.kunci_esai;
                    this.opsi = data.opsi.map(o => ({ ...o, _key: ujianUid() }));
                    this.pasangan = data.pasangan.map(p => ({ ...p, _key: ujianUid() }));
                    this.rendered = true;
                } catch (e) {
                    alert('Gagal memuat data soal.');
                    this.open = false;
                } finally {
                    this.loadingData = false;
                }
            } else {
                this.open = !this.open;
            }
            if (this.open && this.rendered) {
                this.$nextTick(() => window.UjianEditor && window.UjianEditor.mountAll());
            }
        },
        // _uid: id unik per KARTU soal (dipakai buat namespacing id textarea TinyMCE
        // supaya tak bentrok antar kartu di halaman yg sama). _key per baris opsi/pasangan:
        // kunci STABIL yg tak berubah walau baris LAIN ditambah/dihapus — kalau pakai index
        // biasa (:key="i"), Alpine akan mengira DOM node opsi pindah isi (bukan pindah index)
        // saat baris lain dihapus, dan textarea TinyMCE yg sudah ter-mount jadi salah kaitan.
        _uid: init._uid || ujianUid(),
        opsi: (init.opsi || []).map(o => ({ ...o, _key: o._key || ujianUid() })),
        pasangan: (init.pasangan || []).map(p => ({ ...p, _key: p._key || ujianUid() })),
        resetTrueFalse() {
            // Lepas TinyMCE dari textarea opsi (kalau sempat ter-mount waktu tipe masih
            // mcq/mcq_complex) SEBELUM x-if melepas elemennya dari DOM — cegah instance
            // TinyMCE menggantung tanpa elemen (juga cegah ia diam2 ikut nulis value='' ke
            // field bernama sama saat submit, lihat catatan di soal-fields.blade.php).
            this.opsi.forEach(o => window.UjianEditor && window.UjianEditor.unmount('opsi-editor-' + this._uid + '-' + o._key));
            // Mutasi 2 baris opsi PERTAMA di tempat (pertahankan _key-nya) drpd mengganti
            // `opsi` dgn array BARU sepenuhnya. Baris ekstra (kalau sblmnya tipe lain punya
            // >2 opsi) dibuang.
            while (this.opsi.length < 2) this.opsi.push({ teks: '', benar: false, _key: ujianUid() });
            this.opsi[0].teks = 'Benar'; this.opsi[0].benar = false;
            this.opsi[1].teks = 'Salah'; this.opsi[1].benar = false;
            if (this.opsi.length > 2) this.opsi.splice(2);
            // Jaring pengaman: ganti tipe soal ke Benar/Salah lewat kode (bukan klik user
            // langsung ke checkbox) terbukti kadang tak bikin binding x-model checkbox yg
            // SUDAH ter-mount ikut sinkron ulang ke DOM (opsi.benar di data Alpine sudah
            // benar, tapi properti `checked` elemen tetap nyangkut di nilai lama) — entah
            // krn detail internal x-for/Alpine saat banyak mutasi ditumpuk sekaligus dlm satu
            // siklus reaktif. Paksa cocokkan langsung sbg jaring pengaman drpd berharap penuh
            // pada reaktivitas otomatis di titik SPESIFIK ini. Pakai `_rootEl` (ditangkap via
            // x-init di elemen ROOT kartu), BUKAN `this.$el` — `$el` di dalam method yg
            // dipanggil dari @change select ternyata resolve ke elemen <select> ITU SENDIRI
            // (elemen directive yg sedang dievaluasi), bukan root kartu, jadi querySelectorAll
            // via `this.$el` selalu 0 hasil (checkbox bukan descendant dari <select>).
            this.$nextTick(() => {
                (this._rootEl || document).querySelectorAll('input[type="checkbox"][name*="[benar]"]').forEach((cb, idx) => {
                    cb.checked = !!this.opsi[idx]?.benar;
                });
            });
        },
        addOpsi() {
            this.opsi.push({ teks: '', benar: false, _key: ujianUid() });
            this.$nextTick(() => window.UjianEditor && window.UjianEditor.mountAll());
        },
        removeOpsi(i) {
            const key = this.opsi[i]?._key;
            if (key) window.UjianEditor && window.UjianEditor.unmount('opsi-editor-' + this._uid + '-' + key);
            this.opsi.splice(i, 1);
        },
        addPasangan() {
            this.pasangan.push({ kiri: '', kanan: '', _key: ujianUid() });
            this.$nextTick(() => window.UjianEditor && window.UjianEditor.mountAll());
        },
        removePasangan(i) {
            const key = this.pasangan[i]?._key;
            if (key) {
                window.UjianEditor && window.UjianEditor.unmount('pasangan-kiri-' + this._uid + '-' + key);
                window.UjianEditor && window.UjianEditor.unmount('pasangan-kanan-' + this._uid + '-' + key);
            }
            this.pasangan.splice(i, 1);
        },
    }
}
</script>
@endonce
