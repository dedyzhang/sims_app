{{-- Bagian C (Ketidakhadiran Siswa) — dipakai create & edit. Butuh Alpine state agendaForm. --}}

<div class="card p-5 space-y-4">
    <div class="flex items-center justify-between gap-2 flex-wrap">
        <p class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2"><span class="w-6 h-6 rounded-lg grid place-items-center text-white text-xs font-bold" style="background:var(--cp)">C</span> Ketidakhadiran Siswa <span class="text-xs font-normal text-slate-400">(opsional)</span></p>
        <span class="text-xs text-slate-400">Hanya catat siswa yang Sakit / Izin / Alpha</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
        <div class="sm:col-span-5">
            <label class="form-label">Nama Siswa</label>
            <select class="form-select" x-model="absInput.siswa">
                <option value="">— Pilih siswa —</option>
                <template x-for="s in siswaList" :key="s.uuid">
                    <option :value="s.uuid" x-text="s.nama"></option>
                </template>
            </select>
        </div>
        <div class="sm:col-span-3">
            <label class="form-label">Status</label>
            <select class="form-select" x-model="absInput.absensi">
                <option value="">— Pilih —</option>
                <option value="S">Sakit</option>
                <option value="I">Izin</option>
                <option value="A">Alpha</option>
            </select>
        </div>
        <div class="sm:col-span-3">
            <label class="form-label">Keterangan</label>
            <input type="text" class="form-input" x-model="absInput.keterangan" placeholder="Opsional">
        </div>
        <div class="sm:col-span-1">
            <button type="button" @click="addAbsensi()" class="w-full px-3 py-2.5 rounded-xl text-sm font-bold text-white flex items-center justify-center" style="background:var(--cp)" title="Tambah"><i data-lucide="plus" class="w-4 h-4"></i></button>
        </div>
    </div>

    <div class="overflow-x-auto" x-show="absensiRows.length > 0" x-cloak>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-slate-400 border-b border-slate-100 dark:border-slate-700">
                    <th class="py-2 pr-2">Nama</th><th class="py-2 px-2 w-24">Status</th><th class="py-2 px-2">Keterangan</th><th class="py-2 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(r, i) in absensiRows" :key="i">
                    <tr class="border-b border-slate-50 dark:border-slate-800">
                        <td class="py-2 pr-2 text-slate-700 dark:text-slate-200" x-text="r.nama"></td>
                        <td class="py-2 px-2"><span class="badge"
                            :class="{'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300': r.absensi==='S','bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300': r.absensi==='I','bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300': r.absensi==='A'}"
                            x-text="r.absensi==='S'?'Sakit':(r.absensi==='I'?'Izin':'Alpha')"></span></td>
                        <td class="py-2 px-2 text-slate-500 dark:text-slate-400" x-text="r.keterangan"></td>
                        <td class="py-2 text-right"><button type="button" @click="removeAbsensi(i)" class="text-rose-500 hover:text-rose-700"><i data-lucide="x" class="w-4 h-4"></i></button></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
