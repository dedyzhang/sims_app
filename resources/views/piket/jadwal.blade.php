@extends('layouts.app')

@section('title', 'Jadwal Piket Guru')

@section('content')
<div class="px-4 py-8 max-w-4xl mx-auto space-y-6" x-data="jadwalPiket({{ Js::from($rows) }}, '{{ route('piket.jadwal.simpan') }}')">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="calendar-check-2" class="w-6 h-6 text-primary"></i>
                Pengaturan Jadwal Piket
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1 text-sm">
                Tentukan hari piket untuk masing-masing guru. Jadwal ini akan berulang setiap minggunya.
            </p>
        </div>
        <div class="flex gap-2">
            <button @click="simpan" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition hover:shadow-md" :disabled="busy">
                <i data-lucide="save" class="w-4 h-4" x-show="!busy"></i>
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="busy"></i>
                <span x-text="busy ? 'Menyimpan...' : 'Simpan Jadwal'"></span>
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-600 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="py-4 px-6 min-w-[200px]">Nama Guru</th>
                        <th class="py-4 px-3 text-center w-24">Senin</th>
                        <th class="py-4 px-3 text-center w-24">Selasa</th>
                        <th class="py-4 px-3 text-center w-24">Rabu</th>
                        <th class="py-4 px-3 text-center w-24">Kamis</th>
                        <th class="py-4 px-3 text-center w-24">Jumat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <template x-for="row in rows" :key="row.id">
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                            <td class="py-3 px-6 font-medium text-slate-700 dark:text-slate-200" x-text="row.nama"></td>
                            
                            <!-- Checkbox mapping: 1=Senin ... 5=Jumat -->
                            <template x-for="h in 5">
                                <td class="p-0 text-center relative group">
                                    <label class="flex items-center justify-center w-full h-full py-4 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700/80 transition">
                                        <input type="checkbox" :value="h" x-model.number="row.hari" class="w-4 h-4 text-primary bg-slate-100 border-slate-300 rounded focus:ring-primary dark:focus:ring-primary dark:ring-offset-slate-800 focus:ring-2 dark:bg-slate-700 dark:border-slate-600">
                                    </label>
                                </td>
                            </template>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0">
                        <td colspan="7" class="py-8 text-center text-slate-500">Belum ada data guru.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function jadwalPiket(initialRows, submitUrl) {
    return {
        rows: initialRows,
        busy: false,
        async simpan() {
            this.busy = true;
            try {
                const response = await fetch(submitUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({ jadwal: this.rows })
                });
                
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    showToast(err.message || 'Gagal menyimpan jadwal', 'error');
                    return;
                }
                
                showToast('Jadwal piket berhasil disimpan', 'success');
            } catch (e) {
                showToast('Gagal terhubung ke server', 'error');
            } finally {
                this.busy = false;
            }
        }
    };
}
</script>
@endpush
