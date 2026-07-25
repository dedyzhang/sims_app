{{-- Toggle on/off modul sekolah (ModulAktif). Dipakai di Pengaturan Sistem & Hak Akses & Fitur. --}}
<div class="card p-6">
    <div>
        <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="toggle-left" class="w-4 h-4 text-primary"></i> Fitur Aktif</h2>
        <p class="text-xs text-slate-400 mt-1 leading-relaxed">Geser untuk matikan fitur yang tidak dipakai satuan pendidikan. Bisa dihidupkan kembali kapan saja. Fitur yang dimatikan hilang dari menu dan tidak bisa diakses.</p>
    </div>

    <form method="POST" action="{{ route('setting.fitur') }}" class="space-y-2.5 mt-5">
        @csrf
        @foreach($modulFitur as $kode => $meta)
        @php $on = ($settings[\App\Support\ModulAktif::settingKey($kode)] ?? '1') === '1'; @endphp
        <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3"
             x-data="{ on: {{ $on ? 'true' : 'false' }} }">
            <div class="min-w-0 flex items-start gap-3">
                <div class="mt-0.5 w-9 h-9 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 grid place-items-center flex-shrink-0">
                    <i data-lucide="{{ $meta['ikon'] }}" class="w-4 h-4 text-primary"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $meta['label'] }}</p>
                    <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">{{ $meta['deskripsi'] }}</p>
                    <p class="text-xs mt-1.5 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                <input type="checkbox" name="{{ $kode }}" value="1" class="hidden peer" x-model="on">
                <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
            </label>
        </div>
        @endforeach

        <div class="pt-1">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Fitur</button>
        </div>
    </form>
</div>
