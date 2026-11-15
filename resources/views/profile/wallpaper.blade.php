<section class="card p-5" x-data="wallpaperStudio()" aria-labelledby="wallpaper-heading">
    <h2 id="wallpaper-heading" class="font-bold text-slate-800 dark:text-slate-100">Wallpaper pribadi</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Gunakan foto keluarga, pemandangan, atau gambar favorit. Disimpan hanya untuk akun ini di browser ini, tidak dikirim ke server sekolah dan tidak ikut berpindah perangkat.</p>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Pilih Ikuti tema aplikasi agar wallpaper otomatis berganti saat tema diganti. Foto sendiri menggantikan wallpaper bawaan.</p>
    <fieldset :disabled="busy" class="mt-4 space-y-4">
        <legend class="sr-only">Pengaturan wallpaper</legend>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <template x-for="option in [{id:'theme',label:'Ikuti tema aplikasi'}, {id:'none',label:'Tanpa gambar'}, {id:'aurora',label:'Aurora'}, {id:'garden',label:'Taman'}, {id:'dusk',label:'Senja'}]" :key="option.id">
                <button type="button" @click="choose(option.id)" :aria-pressed="preset===option.id" class="wallpaper-choice border rounded-xl p-3 text-sm" :class="preset===option.id ? 'ring-2 ring-primary' : ''" :data-wallpaper="option.id" x-text="option.label"></button>
            </template>
        </div>
        <label class="block border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-2xl p-4">
            <span class="block font-semibold text-sm mb-2">Unggah foto sendiri</span>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="upload($event)" class="block w-full text-sm" aria-describedby="wallpaper-help">
            <span id="wallpaper-help" class="block text-xs text-slate-500 dark:text-slate-400 mt-2">JPG, PNG, WebP · maksimal 10 MB / 32 MP. Foto dioptimalkan untuk layar.</span>
        </label>
        <p x-show="preset === 'photo'" class="text-sm">Foto pribadi sedang digunakan.</p>
        <div class="grid sm:grid-cols-2 gap-4" x-show="preset !== 'none' && preset !== 'theme'">
            <label class="text-sm">Redupkan gambar <span x-text="shade + '%'"></span><input type="range" min="0" max="85" x-model.number="shade" @input="preview()" class="block w-full mt-2"></label>
            <label class="text-sm">Efek buram <span x-text="blur + ' px'"></span><input type="range" min="0" max="12" x-model.number="blur" @input="preview()" class="block w-full mt-2"></label>
            <label class="text-sm">Posisi foto<select class="form-select mt-2" x-model="position" @change="preview()"><option value="center">Tengah</option><option value="top">Atas</option><option value="bottom">Bawah</option></select></label>
            <label class="text-sm">Ukuran foto<select class="form-select mt-2" x-model="fit" @change="preview()"><option value="cover">Penuhi layar</option><option value="contain">Tampilkan foto utuh</option></select></label>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="save()" :disabled="busy || !dirty" class="btn-primary px-4 py-2 disabled:opacity-50">Simpan Wallpaper</button>
            <button type="button" @click="cancel()" :disabled="busy || !dirty" class="btn-ghost border px-4 py-2 disabled:opacity-50">Batal</button>
            <button type="button" @click="choose('none')" :disabled="preset === 'none'" class="btn-ghost border px-4 py-2 disabled:opacity-50">Hapus Wallpaper</button>
        </div>
    </fieldset>
    <p x-show="busy" role="status" class="text-sm mt-3">Memproses foto…</p>
    <p x-show="dirty" class="text-xs text-slate-500 dark:text-slate-400 mt-3">Pratinjau belum disimpan. Klik Simpan Wallpaper untuk menyimpan atau menerapkan penghapusan.</p>
    <p x-show="message" x-text="message" role="status" class="text-sm mt-3 text-emerald-700 dark:text-emerald-300"></p>
    <p x-show="error" x-text="error" role="alert" class="text-sm mt-3 text-red-600 dark:text-red-300"></p>
</section>
