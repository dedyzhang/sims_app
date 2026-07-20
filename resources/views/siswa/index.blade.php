@extends('layouts.app')
@section('title', 'Data Siswa')

@section('content')
<div class="space-y-5" x-data="{ importModal: false, resetModal: false, scope: 'semua', tingkat: '', id_kelas: '', resetSubmitting: false }">

    @if(session()->has('import_kredensial_siswa'))
    <div class="card p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-2.5">
            <i data-lucide="key-round" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"></i>
            <div>
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">Kredensial login {{ count(session('import_kredensial_siswa')) }} siswa+ortu baru siap diunduh</p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">Unduh sekarang — password tidak bisa ditampilkan ulang setelah ini.</p>
            </div>
        </div>
        <a href="{{ route('siswa.import.kredensial') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold bg-amber-600 text-white hover:bg-amber-700 transition flex-shrink-0">
            <i data-lucide="download" class="w-4 h-4"></i> Unduh Kredensial
        </a>
    </div>
    @endif

    @if(session()->has('reset_kredensial_siswa'))
    <div class="card p-4 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-2.5">
            <i data-lucide="key-round" class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5"></i>
            <div>
                <p class="text-sm font-bold text-violet-800 dark:text-violet-300">Kredensial baru hasil reset {{ count(session('reset_kredensial_siswa')) }} akun siswa+ortu siap diunduh</p>
                <p class="text-xs text-violet-700 dark:text-violet-400 mt-0.5">Unduh sekarang — password tidak bisa ditampilkan ulang setelah ini.</p>
            </div>
        </div>
        <a href="{{ route('siswa.reset.bulk.kredensial') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold bg-violet-600 text-white hover:bg-violet-700 transition flex-shrink-0">
            <i data-lucide="download" class="w-4 h-4"></i> Unduh Kredensial
        </a>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Data Siswa</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ $siswas->total() }} siswa terdaftar</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            {{-- Cetak kartu pelajar per tingkat (A4, 10 kartu/halaman, ukuran ATM) --}}
            <div class="relative" x-data="{ cetakOpen:false, tk:'' }">
                <button @click="cetakOpen=!cetakOpen" type="button"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                    <i data-lucide="id-card" class="w-4 h-4"></i> Cetak Kartu
                </button>
                <div x-show="cetakOpen" @click.outside="cetakOpen=false" x-cloak
                     class="absolute right-0 mt-2 w-72 card p-4 z-40 space-y-3 shadow-xl">
                    <div>
                        <p class="text-sm font-bold text-slate-700 dark:text-slate-200">Cetak Kartu per Tingkat</p>
                        <p class="text-xs text-slate-400 mt-0.5">A4 · 10 kartu/halaman · ukuran ATM.</p>
                    </div>
                    <select x-model="tk" class="form-input py-2 text-sm w-full">
                        <option value="">Pilih tingkat…</option>
                        @foreach($kelas->pluck('tingkat')->unique()->sort() as $t)
                        <option value="{{ $t }}">Tingkat {{ $t }}</option>
                        @endforeach
                    </select>
                    <a :href="tk ? '{{ route('kartu-pelajar.cetak') }}?tingkat=' + encodeURIComponent(tk) : '#'"
                       :class="tk ? '' : 'opacity-50 pointer-events-none'"
                       target="_blank" rel="noopener"
                       class="w-full px-4 py-2 rounded-xl text-sm font-bold flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Cetak PDF
                    </a>
                </div>
            </div>
            <button @click="resetModal=true"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-violet-200 dark:border-violet-700 text-violet-700 dark:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition">
                <i data-lucide="key-round" class="w-4 h-4"></i> Reset Password Massal
            </button>
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
                        <li>Kolom NIS wajib diisi (harus unik). Baris dgn NIS yang sudah terdaftar akan dilewati</li>
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

    {{-- Reset Password Massal Modal --}}
    <div x-show="resetModal" class="modal-backdrop" x-transition @click.self="resetModal=false" x-cloak>
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200">Reset Password Massal</h3>
                <button @click="resetModal=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('siswa.reset.bulk') }}" id="resetBulkForm" onsubmit="return confirmResetBulk(this)">
                @csrf
                <div class="p-5 space-y-4">
                    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700 rounded-xl p-3 text-sm text-rose-800 dark:text-rose-300">
                        <p class="font-semibold mb-1">⚠️ Tindakan ini tidak bisa dibatalkan</p>
                        <p class="text-xs">Password LAMA akun yang direset langsung tidak berlaku. Akun siswa & orang tuanya sekaligus direset. Segera unduh &amp; bagikan kredensial baru setelah ini.</p>
                        <p class="text-xs mt-1">Untuk cakupan besar (semua siswa/tingkat penuh), proses bisa makan waktu beberapa menit — jangan tutup atau refresh halaman sampai selesai.</p>
                    </div>

                    <div class="space-y-2">
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer" :class="scope==='semua' && 'border-violet-400 bg-violet-50 dark:bg-violet-900/20'">
                            <input type="radio" name="scope" value="semua" x-model="scope" class="accent-violet-600">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Semua siswa aktif <span class="text-slate-400 font-normal">({{ $totalAktif }} siswa)</span></span>
                        </label>
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer" :class="scope==='tingkat' && 'border-violet-400 bg-violet-50 dark:bg-violet-900/20'">
                            <input type="radio" name="scope" value="tingkat" x-model="scope" class="accent-violet-600">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Per tingkat</span>
                        </label>
                        <div x-show="scope==='tingkat'" class="pl-8">
                            <select name="tingkat" x-model="tingkat" class="form-input py-2 text-sm w-full">
                                <option value="">Pilih tingkat…</option>
                                @foreach($tingkatCounts as $t => $jumlah)
                                <option value="{{ $t }}">Tingkat {{ $t }} ({{ $jumlah }} siswa)</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer" :class="scope==='kelas' && 'border-violet-400 bg-violet-50 dark:bg-violet-900/20'">
                            <input type="radio" name="scope" value="kelas" x-model="scope" class="accent-violet-600">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Per kelas</span>
                        </label>
                        <div x-show="scope==='kelas'" class="pl-8">
                            <select name="id_kelas" x-model="id_kelas" class="form-input py-2 text-sm w-full">
                                <option value="">Pilih kelas…</option>
                                @foreach($kelas as $k)
                                <option value="{{ $k->uuid }}">Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="p-5 border-t border-slate-100 dark:border-slate-700">
                    <button type="submit"
                            :disabled="resetSubmitting || (scope==='tingkat' && !tingkat) || (scope==='kelas' && !id_kelas)"
                            class="w-full py-2.5 rounded-xl text-sm font-bold bg-rose-600 hover:bg-rose-700 text-white transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <i data-lucide="loader-circle" class="w-4 h-4 animate-spin" x-show="resetSubmitting"></i>
                        <i data-lucide="key-round" class="w-4 h-4" x-show="!resetSubmitting"></i>
                        <span x-text="resetSubmitting ? 'Memproses reset…' : 'Reset Password Sekarang'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmResetBulk(form) {
    const scope = form.scope.value;
    let target = 'SEMUA siswa aktif';
    if (scope === 'tingkat') {
        const opt = form.tingkat.options[form.tingkat.selectedIndex];
        target = opt ? opt.text : 'tingkat terpilih';
    } else if (scope === 'kelas') {
        const opt = form.id_kelas.options[form.id_kelas.selectedIndex];
        target = opt ? opt.text : 'kelas terpilih';
    }
    $.confirm({
        theme: 'material', animation: 'scale', closeIcon: true, backgroundDismiss: true, useBootstrap: false, boxWidth: '420px',
        title: 'Reset password massal?',
        content: `Password akun siswa & orang tua untuk <b>${target}</b> akan direset semua dan password lama langsung tidak berlaku. Lanjutkan?`,
        type: 'red',
        buttons: {
            ya: { text: 'Ya, Reset Sekarang', btnClass: 'btn-red', action: function(){
                const root = form.closest('[x-data]');
                if (root) { Alpine.$data(root).resetSubmitting = true; }
                form.submit();
            } },
            batal: { text: 'Batal' },
        },
    });
    return false;
}
</script>
@endpush
