@extends('layouts.app')
@section('title', 'Mata Pelajaran')

@section('content')
<div class="space-y-5" x-data="pelajaranApp()">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Mata Pelajaran</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $pelajarans->count() }} mata pelajaran terdaftar</p>
        </div>
        <button @click="openAdd()" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Pelajaran
        </button>
    </div>

    {{-- Table Card --}}
    <div class="card">
        <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3 flex-wrap">
            <div class="relative flex-1 min-w-48">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input type="text" id="searchPelajaran" placeholder="Cari pelajaran..." x-model="search"
                       class="form-input pl-9 py-2 text-sm">
            </div>
            <p class="text-xs text-slate-400">Drag baris untuk ubah urutan</p>
        </div>
        <div class="table-responsive">
            <table class="data-table w-full" id="pelajaranTable">
                <thead>
                    <tr>
                        <th class="w-8"><i data-lucide="grip-vertical" class="w-3.5 h-3.5 mx-auto"></i></th>
                        <th class="w-10">#</th>
                        <th>Nama Pelajaran</th>
                        <th>Kode</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pelajaranBody">
                    @forelse($pelajarans as $i => $p)
                    <tr class="pelajaran-row" data-id="{{ $p->uuid }}" x-show="search==='' || '{{ strtolower($p->nama) }}'.includes(search.toLowerCase()) || '{{ strtolower($p->kode ?? '') }}'.includes(search.toLowerCase())">
                        <td class="cursor-grab text-slate-300 hover:text-slate-500 select-none">
                            <i data-lucide="grip-vertical" class="w-4 h-4 mx-auto"></i>
                        </td>
                        <td class="text-slate-400 text-xs font-mono">{{ $i+1 }}</td>
                        <td class="font-medium text-slate-800 dark:text-slate-200">{{ $p->nama }}</td>
                        <td>
                            @if($p->kode)
                            <span class="badge bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-mono">{{ $p->kode }}</span>
                            @else
                            <span class="text-slate-300 text-sm">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center gap-1 justify-end">
                                <button @click="openEdit('{{ $p->uuid }}','{{ addslashes($p->nama) }}','{{ $p->kode ?? '' }}')"
                                        class="p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900 text-blue-500 transition" title="Edit">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                                <button @click="hapus('{{ $p->uuid }}','{{ addslashes($p->nama) }}')"
                                        class="p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900 text-rose-500 transition" title="Hapus">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-12 text-slate-400">
                            <i data-lucide="book-open" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                            <p>Belum ada mata pelajaran</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah/Edit --}}
    <div x-show="modalOpen" class="modal-backdrop" x-transition @click.self="modalOpen=false">
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200" x-text="editId ? 'Edit Pelajaran' : 'Tambah Pelajaran'"></h3>
                <button @click="modalOpen=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Nama Pelajaran <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="form.nama" placeholder="Contoh: Matematika" class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Kode</label>
                    <input type="text" x-model="form.kode" placeholder="Contoh: MAT (opsional)" maxlength="10" class="form-input">
                </div>
                <p x-show="formError" class="text-sm text-rose-500" x-text="formError"></p>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-700 flex gap-2 justify-end">
                <button @click="modalOpen=false" class="px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Batal</button>
                <button @click="simpan()" :disabled="loading"
                        class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition">
                    <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin" x-show="loading"></i>
                    <span x-text="loading ? 'Menyimpan...' : 'Simpan'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function pelajaranApp() {
    return {
        search: '',
        modalOpen: false,
        editId: null,
        form: { nama: '', kode: '' },
        formError: '',
        loading: false,

        openAdd() {
            this.editId = null;
            this.form = { nama: '', kode: '' };
            this.formError = '';
            this.modalOpen = true;
            this.$nextTick(() => lucide.createIcons());
        },
        openEdit(id, nama, kode) {
            this.editId = id;
            this.form = { nama, kode };
            this.formError = '';
            this.modalOpen = true;
            this.$nextTick(() => lucide.createIcons());
        },

        async simpan() {
            if (!this.form.nama.trim()) { this.formError = 'Nama pelajaran wajib diisi.'; return; }
            this.loading = true;
            this.formError = '';
            const url  = this.editId ? `/pelajaran/${this.editId}` : '/pelajaran';
            const method = this.editId ? 'PUT' : 'POST';
            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content'), Accept: 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                if (res.ok) {
                    this.modalOpen = false;
                    showToast(data.message || 'Tersimpan!');
                    setTimeout(() => location.reload(), 800);
                } else {
                    this.formError = Object.values(data.errors || {})[0]?.[0] || data.message || 'Terjadi kesalahan.';
                }
            } catch { this.formError = 'Gagal menghubungi server.'; }
            this.loading = false;
        },

        hapus(id, nama) {
            $.confirm({
                title: 'Hapus Pelajaran?',
                content: `Hapus <strong>${nama}</strong>? Data tidak dapat dikembalikan.`,
                type: 'red',
                theme: 'material',
                buttons: {
                    hapus: {
                        text: 'Hapus',
                        btnClass: 'btn-danger',
                        action: async () => {
                            const res = await fetch(`/pelajaran/${id}`, {
                                method: 'DELETE',
                                headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content'), Accept: 'application/json' }
                            });
                            const data = await res.json();
                            showToast(data.message || 'Dihapus.', res.ok ? 'success' : 'error');
                            if (res.ok) setTimeout(() => location.reload(), 800);
                        }
                    },
                    batal: { text: 'Batal' }
                }
            });
        },

        initSortable() {
            Sortable.create(document.getElementById('pelajaranBody'), {
                animation: 150,
                handle: '.pelajaran-row',
                ghostClass: 'bg-indigo-50',
                onEnd: () => this.saveOrder()
            });
        },

        saveOrder() {
            const rows = document.querySelectorAll('.pelajaran-row');
            const urutans = {};
            rows.forEach((r, i) => { urutans[r.dataset.id] = i + 1; });
            fetch('/pelajaran/sorting', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
                body: JSON.stringify({ urutans })
            });
        },

        init() {
            this.$nextTick(() => {
                lucide.createIcons();
                this.initSortable();
            });
        }
    }
}
</script>
@endpush
