@extends('layouts.app')
@section('title', 'Data Guru')

@section('content')
<div class="space-y-5" x-data="{ importModal: false }">

    @if(session()->has('import_kredensial_guru'))
    <div class="card p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-2.5">
            <i data-lucide="key-round" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
            <div>
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">Kredensial login {{ count(session('import_kredensial_guru')) }} guru baru siap diunduh</p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Unduh sekarang — password tidak bisa ditampilkan ulang setelah ini.</p>
            </div>
        </div>
        <a href="{{ route('guru.import.kredensial') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold bg-amber-600 text-white hover:bg-amber-700 transition flex-shrink-0">
            <i data-lucide="download" class="w-4 h-4"></i> Unduh Kredensial
        </a>
    </div>
    @endif

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Data Guru</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ $gurus->total() }} guru terdaftar</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('guru.ngajar-tanpa-guru') }}"
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border {{ ($ngajarTanpaGuruCount ?? 0) > 0 ? 'border-amber-300 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 hover:bg-amber-100' : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' }} transition">
                <i data-lucide="user-x" class="w-4 h-4 {{ ($ngajarTanpaGuruCount ?? 0) > 0 ? 'text-amber-600' : 'text-slate-400' }}"></i>
                <span>Penugasan Tanpa Guru</span>
                @if(($ngajarTanpaGuruCount ?? 0) > 0)
                <span class="px-2 py-0.5 rounded-full text-xs font-extrabold bg-rose-500 text-white animate-pulse">
                    {{ $ngajarTanpaGuruCount }}
                </span>
                @endif
            </a>
            <button @click="importModal=true"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition">
                <i data-lucide="upload" class="w-4 h-4"></i> Import Excel
            </button>
            <a href="{{ route('guru.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Guru
            </a>
        </div>
    </div>

    @if(($ngajarTanpaGuruCount ?? 0) > 0)
    <div class="card p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-700 flex items-center justify-between gap-3 flex-wrap rounded-2xl shadow-sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-amber-900 dark:text-amber-200">
                    Terdapat {{ $ngajarTanpaGuruCount }} penugasan mengajar yang tidak memiliki guru aktif
                </p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                    Data guru sebelumnya telah dihapus sehingga penugasan kelas dan mapel ini menggantung. Tentukan guru pengganti agar KBM dan jadwal berjalan normal.
                </p>
            </div>
        </div>
        <a href="{{ route('guru.ngajar-tanpa-guru') }}" class="btn-primary text-xs font-bold px-4 py-2 rounded-xl flex items-center gap-1.5 shadow-sm">
            <span>Atur Guru Pengganti</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>
    @endif

    {{-- Search --}}
    <form method="GET" class="card p-4 flex flex-wrap gap-2">
        <div class="relative flex-1 min-w-48">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, NIK, atau NIP..."
                   class="form-input pl-9 py-2 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">
            Cari
        </button>
        @if(request('search'))
        <a href="{{ route('guru.index') }}" class="px-4 py-2 rounded-xl text-sm text-slate-500 hover:text-slate-700 transition">Reset</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Nama Guru</th>
                        <th class="hide-mobile">NIK / NIP</th>
                        <th class="hide-mobile">Username</th>
                        <th class="hide-mobile">Wali Kelas</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gurus as $i => $guru)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $gurus->firstItem() + $i }}</td>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold flex-shrink-0 overflow-hidden"
                                     style="background:var(--cp)">
                                    @if($guru->face_photo)<img src="{{ $guru->face_photo_url }}" class="w-full h-full object-cover" alt="">@else{{ strtoupper(substr($guru->nama, 0, 1)) }}@endif
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $guru->nama }}</p>
                                    <p class="text-xs text-slate-400">{{ $guru->jk === 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <span class="text-sm text-slate-600 dark:text-slate-400 font-mono">{{ $guru->nik ?? $guru->nip ?? '—' }}</span>
                        </td>
                        <td class="hide-mobile">
                            <span class="text-xs text-slate-500 font-mono">{{ $guru->user?->username ?? '—' }}</span>
                        </td>
                        <td class="hide-mobile">
                            @if($guru->walikelas)
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">
                                Kls {{ $guru->walikelas->kelas?->tingkat }}{{ $guru->walikelas->kelas?->kelas }}
                            </span>
                            @else
                            <span class="text-slate-300 text-sm">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center gap-1 justify-end">
                                <a href="{{ route('guru.show', $guru->uuid) }}"
                                   class="p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900 text-blue-500 transition" title="Detail">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <a href="{{ route('guru.edit', $guru->uuid) }}"
                                   class="p-1.5 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900 text-amber-500 transition" title="Edit">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <form method="POST" action="{{ route('guru.reset', $guru->uuid) }}" class="inline"
                                      onsubmit="return confirmAction(this, 'Reset password {{ addslashes($guru->nama) }}?')">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-violet-50 dark:hover:bg-violet-900 text-violet-500 transition" title="Reset Password">
                                        <i data-lucide="key-round" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('guru.destroy', $guru->uuid) }}" class="inline"
                                      onsubmit="return confirmDelete(this)">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900 text-rose-500 transition" title="Hapus">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-12 text-slate-400">
                            <i data-lucide="users" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                            <p class="font-medium">Belum ada data guru</p>
                            <a href="{{ route('guru.create') }}" class="text-indigo-500 hover:underline text-sm mt-1 inline-block">+ Tambah sekarang</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($gurus->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $gurus->links() }}
        </div>
        @endif
    </div>

    {{-- Import Modal --}}
    <div x-show="importModal" class="modal-backdrop" x-transition @click.self="importModal=false" style="display: none;">
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200">Import Guru dari Excel</h3>
                <button @click="importModal=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-3 text-sm text-amber-800 dark:text-amber-300">
                    <p class="font-semibold mb-1">⚠️ Perhatian</p>
                    <ul class="text-xs space-y-0.5 list-disc list-inside">
                        <li>Gunakan template Excel yang tersedia</li>
                        <li>Baris contoh (diawali "CONTOH") otomatis dilewati</li>
                        <li>NIK wajib diisi dengan Nomor Induk Karyawan</li>
                        <li>Akun guru dibuat otomatis</li>
                        <li>Format file: .xlsx atau .xls (maks 5MB)</li>
                    </ul>
                </div>
                <a href="{{ route('guru.import.template') }}"
                   class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition w-full justify-center">
                    <i data-lucide="download" class="w-4 h-4"></i> Download Template Excel (.xlsx)
                </a>
                <form method="POST" action="{{ route('guru.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="block border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-xl p-6 text-center mb-3 hover:border-primary transition cursor-pointer">
                        <i data-lucide="file-spreadsheet" class="w-8 h-8 mx-auto text-slate-400 mb-2"></i>
                        <span class="text-sm text-slate-500">Pilih file Excel (.xlsx / .xls)</span>
                        <input type="file" name="file" accept=".xlsx,.xls" required class="hidden"
                               onchange="document.getElementById('fileName').textContent = this.files[0]?.name || ''">
                        <p id="fileName" class="text-xs text-primary mt-1 font-semibold"></p>
                    </label>
                    <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-semibold">
                        Upload &amp; Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmAction(form, message) {
    if (form.dataset.submitting) return false;
    $.confirm({
        title: 'Konfirmasi',
        content: message,
        type: 'orange',
        theme: 'material',
        buttons: {
            ya: {
                text: 'Ya, Lanjutkan',
                btnClass: 'btn-warning',
                action: function() { 
                    form.dataset.submitting = 'true';
                    form.submit(); 
                }
            },
            tidak: { text: 'Batal' }
        }
    });
    return false;
}
</script>
@endpush
@endsection
