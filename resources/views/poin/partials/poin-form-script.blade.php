{{-- Dipakai bersama oleh poin/siswa/create.blade.php (admin/kesiswaan tambah poin
     langsung) & poin/guru/create.blade.php (guru ajukan poin, perlu approval) — form
     & alur Alpine-nya identik, cuma beda endpoint submit & pesan. --}}
@once
<script>
function poinForm(url) {
    return {
        jenis: '', aturans: [], loadingAturan: false, ts: null,
        init() {
            // Jaring pengaman: kalau TomSelect somehow belum/gagal termuat (mis. CDN lambat
            // di jaringan sekolah yg kurang stabil), jangan biarkan x-init lempar error diam2
            // yg bikin `loadAturan()` selanjutnya SELALU crash di `this.ts.clear(true)` sebelum
            // sempat fetch — dropdown "Aturan" jadi kosong permanen tanpa penjelasan apapun ke
            // user. `this.ts` dibiarkan null, loadAturan() di bawah fallback ke <select> native.
            if (typeof TomSelect === 'undefined') return;
            this.ts = new TomSelect(this.$refs.aturanSelect, {
                create: false,
                placeholder: 'Pilih jenis dulu, lalu cari aturan...',
            });
            this.ts.disable();
        },
        async loadAturan() {
            this.aturans = [];
            if (this.ts) {
                this.ts.clear(true);
                this.ts.clearOptions();
                this.ts.disable();
            } else {
                this.$refs.aturanSelect.innerHTML = '';
                this.$refs.aturanSelect.disabled = true;
            }
            if (!this.jenis) return;

            this.loadingAturan = true;
            try {
                const res = await fetch(url + '?jenis=' + this.jenis);
                const data = await res.json();
                this.aturans = data.aturans || [];
                const label = a => a.kode + ' — ' + a.aturan + ' (' + a.poin + ' poin)';
                if (this.ts) {
                    this.aturans.forEach(a => this.ts.addOption({ value: a.uuid, text: label(a) }));
                    this.ts.refreshOptions(false);
                    this.ts.enable();
                } else {
                    this.aturans.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a.uuid;
                        opt.textContent = label(a);
                        this.$refs.aturanSelect.appendChild(opt);
                    });
                    this.$refs.aturanSelect.disabled = false;
                }
            } catch (e) {
                // biarkan tetap nonaktif jika gagal memuat
            } finally {
                this.loadingAturan = false;
            }
        }
    };
}
</script>
@endonce
