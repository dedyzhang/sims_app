<script>
function agendaForm(cfg) {
    return {
        // konfigurasi
        mode: cfg.mode || 'create',
        storeUrl: cfg.storeUrl, updateUrl: cfg.updateUrl,
        slotsUrl: cfg.slotsUrl, siswaUrl: cfg.siswaUrl, indexUrl: cfg.indexUrl,
        presetTanggal: cfg.presetTanggal, presetJadwal: cfg.presetJadwal,

        // state
        tanggal: cfg.tanggal || cfg.presetTanggal || '',
        jadwal: cfg.jadwal || '',
        slots: cfg.slots || [],
        slotMsg: '',
        siswaList: cfg.siswaList || [],
        form: Object.assign({ pembahasan: '', metode: '', proses: 'belum', kegiatan: '', kendala: '' }, cfg.form || {}),
        absensiRows: cfg.absensiRows || [],
        absInput: { siswa: '', absensi: '', keterangan: '' },
        loading: false,

        init() {
            if (this.mode === 'create') {
                if (this.tanggal) {
                    this.loadSlots().then(() => {
                        if (this.presetJadwal) {
                            this.jadwal = this.presetJadwal;
                            this.loadSiswa();
                        }
                    });
                }
            }
            this.refreshIcons();
        },

        refreshIcons() { this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); }); },

        async loadSlots() {
            this.slots = []; this.jadwal = ''; this.siswaList = []; this.slotMsg = '';
            if (!this.tanggal) return;
            try {
                const res = await fetch(`${this.slotsUrl}?tanggal=${this.tanggal}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.slots = data.slots; }
                else { this.slotMsg = data.message || 'Tidak ada jadwal.'; }
            } catch (e) { this.slotMsg = 'Gagal memuat jadwal.'; }
            this.refreshIcons();
        },

        async loadSiswa() {
            this.siswaList = [];
            if (!this.jadwal) return;
            try {
                const res = await fetch(`${this.siswaUrl}?id_jadwal=${this.jadwal}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) { this.siswaList = data.siswa; }
            } catch (e) { /* abaikan */ }
        },

        namaSiswa(uuid) {
            const s = this.siswaList.find(x => x.uuid === uuid);
            return s ? s.nama : '';
        },

        addAbsensi() {
            if (!this.absInput.siswa || !this.absInput.absensi) {
                return this.alert('Pilih siswa dan status ketidakhadiran dulu.');
            }
            if (this.absensiRows.some(r => r.id_siswa === this.absInput.siswa)) {
                return this.alert('Siswa ini sudah ada di daftar ketidakhadiran.');
            }
            this.absensiRows.push({
                id_siswa: this.absInput.siswa, nama: this.namaSiswa(this.absInput.siswa),
                absensi: this.absInput.absensi, keterangan: this.absInput.keterangan || '',
            });
            this.absInput = { siswa: '', absensi: '', keterangan: '' };
            this.refreshIcons();
        },
        removeAbsensi(i) { this.absensiRows.splice(i, 1); },

        alert(msg, type = 'orange') {
            if (window.jQuery && $.alert) $.alert({ title: 'Perhatian', content: msg, type: type });
            else window.alert(msg);
        },

        validasiB() {
            const f = this.form;
            return f.pembahasan.trim() && f.metode.trim() && f.kegiatan.trim() && f.kendala.trim();
        },

        async submit() {
            if (this.mode === 'create' && (!this.tanggal || !this.jadwal)) {
                return this.alert('Pilih tanggal dan jadwal mengajar dulu.');
            }
            if (!this.validasiB()) {
                return this.alert('Lengkapi bagian B: Pembahasan, Metode, Kegiatan, dan Kendala wajib diisi.');
            }

            const payload = {
                tanggal: this.tanggal, jadwal: this.jadwal,
                pembahasan: this.form.pembahasan, metode: this.form.metode, proses: this.form.proses,
                kegiatan: this.form.kegiatan, kendala: this.form.kendala,
                absensi: JSON.stringify(this.absensiRows.map(r => ({ siswa: r.id_siswa, absensi: r.absensi, keterangan: r.keterangan }))),
            };

            this.loading = true;
            if (window.showGlobalSpinner) showGlobalSpinner();
            try {
                const url = this.mode === 'edit' ? this.updateUrl : this.storeUrl;
                const method = this.mode === 'edit' ? 'PUT' : 'POST';
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify(payload),
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.success) {
                        window.location = `${this.indexUrl}?tanggal=${this.tanggal}`;
                        return;
                    }
                    this.alert(data.message || 'Gagal menyimpan agenda.', 'red');
                } else if (res.status === 422) {
                    const err = await res.json();
                    const first = err.errors ? Object.values(err.errors)[0][0] : (err.message || 'Data tidak valid.');
                    this.alert(first, 'red');
                } else {
                    this.alert('Terjadi kesalahan saat menyimpan.', 'red');
                }
            } catch (e) {
                this.alert('Gagal terhubung ke server.', 'red');
            } finally {
                this.loading = false;
                if (window.hideGlobalSpinner) hideGlobalSpinner();
            }
        },
    };
}
</script>
