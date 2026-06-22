@extends('layouts.app')
@section('title', 'Data Siswa')

@section('content')
<div class="space-y-5" x-data="{ importModal: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Data Siswa</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ $siswas->total() }} siswa terdaftar</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button @click="importModal=true"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition">
                <i data-lucide="upload" class="w-4 h-4"></i> Import Excel
            </button>
            <a href="{{ route('siswa.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Siswa
            </a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="card p-4 flex flex-wrap gap-2 items-end">
        <div class="relative flex-1 min-w-48">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / NIS..."
                   class="form-input pl-9 py-2 text-sm">
        </div>
        <select name="id_kelas" class="form-input py-2 text-sm w-auto">
            <option value="">Semua Kelas</option>
            @foreach($kelas as $k)
            <option value="{{ $k->uuid }}" @selected(request('id_kelas')===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">
            <i data-lucide="filter" class="w-4 h-4 inline"></i> Filter
        </button>
        @if(request('search') || request('id_kelas'))
        <a href="{{ route('siswa.index') }}" class="px-4 py-2 rounded-xl text-sm text-slate-500 hover:text-slate-700 transition">Reset</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Nama Siswa</th>
                        <th>NIS</th>
                        <th class="hide-mobile">Kelas</th>
                        <th class="hide-mobile">JK</th>
                        <th class="hide-mobile">Login</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($siswas as $i => $siswa)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $siswas->firstItem() + $i }}</td>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0 overflow-hidden"
                                     style="background: {{ $siswa->jk==='L' ? 'var(--cp)' : '#ec4899' }}">
                                    @if($siswa->face_photo)<img src="{{ $siswa->face_photo_url }}" class="w-full h-full object-cover" alt="">@else{{ strtoupper(substr($siswa->nama, 0, 1)) }}@endif
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ $siswa->nama }}</p>
                                    @if($siswa->nisn)
                                    <p class="text-xs text-slate-400 font-mono">NISN: {{ $siswa->nisn }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ $siswa->nis ?? '—' }}</td>
                        <td class="hide-mobile">
                            @if($siswa->kelas)
                            <span class="badge bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">
                                {{ $siswa->kelas->tingkat }}{{ $siswa->kelas->kelas }}
                            </span>
                            @else
                            <span class="text-slate-300 text-xs">Belum</span>
                            @endif
                        </td>
                        <td class="hide-mobile">
                            <span class="badge {{ $siswa->jk==='L' ? 'bg-sky-100 text-sky-700' : 'bg-pink-100 text-pink-700' }}">
                                {{ $siswa->jk==='L' ? 'Laki-laki' : 'Perempuan' }}
                            </span>
                        </td>
                        <td class="hide-mobile">
                            <span class="font-mono text-xs text-slate-500">{{ $siswa->user?->username ?? '—' }}</span>
                        </td>
                        <td>
                            <div class="flex items-center gap-1 justify-end">
                                <a href="{{ route('siswa.show', $siswa->uuid) }}"
                                   class="p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900 text-blue-500 transition" title="Detail">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <a href="{{ route('siswa.edit', $siswa->uuid) }}"
                                   class="p-1.5 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900 text-amber-500 transition" title="Edit">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <form method="POST" action="{{ route('siswa.reset', $siswa->uuid) }}" class="inline"
                                      onsubmit="return confirmAction(this, 'Reset password siswa {{ addslashes($siswa->nama) }}?')">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-violet-50 dark:hover:bg-violet-900 text-violet-500 transition" title="Reset Password Siswa">
                                        <i data-lucide="key-round" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('siswa.resetOrtu', $siswa->uuid) }}" class="inline"
                                      onsubmit="return confirmAction(this, 'Reset password orang tua {{ addslashes($siswa->nama) }}?')">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-cyan-50 dark:hover:bg-cyan-900 text-cyan-500 transition" title="Reset Password Orang Tua">
                                        <i data-lucide="user-cog" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('siswa.destroy', $siswa->uuid) }}" onsubmit="return confirmDelete(this)">
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
                        <td colspan="7" class="text-center py-12 text-slate-400">
                            <i data-lucide="users" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                            <p class="font-medium">Belum ada siswa</p>
                            <p class="text-sm mt-1">Tambah siswa baru atau import dari Excel</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($siswas->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $siswas->links() }}
        </div>
        @endif
    </div>

    {{-- Import Modal --}}
    <div x-show="importModal" class="modal-backdrop" x-transition @click.self="importModal=false">
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200">Import Siswa dari Excel</h3>
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
                        <li>NIS bisa diisi manual (harus unik) atau dibuat otomatis</li>
                        <li>Akun siswa & orang tua dibuat otomatis</li>
                        <li>Format file: .xlsx atau .xls (maks 5MB)</li>
                    </ul>
                </div>
                <a href="{{ route('siswa.template') }}"
                   class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition w-full justify-center">
                    <i data-lucide="download" class="w-4 h-4"></i> Download Template Excel (.xlsx)
                </a>
                <form method="POST" action="{{ route('siswa.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-xl p-6 text-center mb-3 hover:border-primary transition">
                        <i data-lucide="file-spreadsheet" class="w-8 h-8 mx-auto text-slate-400 mb-2"></i>
                        <label class="cursor-pointer">
                            <span class="text-sm text-slate-500">Pilih file Excel (.xlsx / .xls)</span>
                            <input type="file" name="file" accept=".xlsx,.xls" required class="hidden"
                                   onchange="document.getElementById('fileName').textContent = this.files[0]?.name || ''">
                        </label>
                        <p id="fileName" class="text-xs text-primary mt-1 font-semibold"></p>
                    </div>
                    <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-semibold">
                        Upload &amp; Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
