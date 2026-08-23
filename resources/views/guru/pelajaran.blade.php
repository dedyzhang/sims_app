@extends('layouts.app')
@section('title', 'Pelajaran Guru')

@section('content')
@php $breadcrumbs = [['label'=>'Data Guru','url'=>route('guru.index')], ['label'=>$guru->nama,'url'=>route('guru.show',$guru->uuid)], ['label'=>'Pelajaran','url'=>'#']]; @endphp

<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('guru.show', $guru->uuid) }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Pelajaran Diajar</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $guru->nama }}</p>
        </div>
    </div>

    {{-- Form Tambah --}}
    <form method="POST" action="{{ route('guru.ngajar', $guru->uuid) }}" class="card p-5 space-y-4">
        @csrf
        <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="plus-circle" class="w-[18px] h-[18px] text-primary"></i> Tambah Pelajaran</h2>
        <div>
            <label class="form-label">Pelajaran</label>
            <select name="id_pelajaran" id="ngPelajaran" required class="form-select">
                <option value="">Pilih Pelajaran</option>
                @foreach($pelajarans as $p)
                <option value="{{ $p->uuid }}">{{ $p->nama }}{{ $p->kode ? " ({$p->kode})" : '' }}</option>
                @endforeach
            </select>
            @error('id_pelajaran')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="form-label !mb-0">Kelas <span class="text-slate-400 font-normal">(boleh pilih banyak)</span></label>
                @if(count($kelas))
                <label class="text-xs text-primary font-semibold flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" class="accent-[color:var(--cp)]" onchange="document.querySelectorAll('.ng-kelas:not(:disabled)').forEach(c=>c.checked=this.checked)"> Pilih semua
                </label>
                @endif
            </div>
            @if(count($kelas))
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                @foreach($kelas as $k)
                <label data-kelas="{{ $k->uuid }}" class="ng-lbl flex flex-col items-center justify-center gap-0.5 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm font-medium cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700 has-[:checked]:border-primary has-[:checked]:bg-primary-50 has-[:checked]:text-primary transition">
                    <span class="flex items-center gap-1.5"><input type="checkbox" name="id_kelas[]" value="{{ $k->uuid }}" class="ng-kelas sr-only"> {{ $k->tingkat }}{{ $k->kelas }}</span>
                    <span class="ng-note text-[9px] text-rose-400 leading-none truncate max-w-full"></span>
                </label>
                @endforeach
            </div>
            @else
            <p class="text-sm text-slate-400">Belum ada kelas. <a href="{{ route('kelas.create') }}" class="text-primary hover:underline">Tambah kelas dulu</a>.</p>
            @endif
            @error('id_kelas')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah ke kelas terpilih
        </button>
    </form>

    {{-- Daftar --}}
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pelajaran</th>
                        <th>Kelas</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ngajars as $ngajar)
                    <tr>
                        <td class="font-medium text-slate-800 dark:text-slate-200">{{ $ngajar->pelajaran?->nama ?? '-' }}</td>
                        <td>
                            <span class="badge bg-primary-50 text-primary">{{ $ngajar->kelas ? 'Kelas '.$ngajar->kelas->tingkat.$ngajar->kelas->kelas : 'Semua Kelas' }}</span>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1" x-data="{ tfModal: false }">
                                <button type="button" @click="tfModal = true" class="p-1.5 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900 text-amber-600 transition" title="Pindahtangankan ke guru lain">
                                    <i data-lucide="arrow-right-left" class="w-4 h-4"></i>
                                </button>
                                <form method="POST" action="{{ route('guru.hapusNgajar', $ngajar->uuid) }}" onsubmit="return confirmAction(this, 'Ruang Kelas (materi, tugas & jawaban siswa) untuk mapel ini di kelas tsb akan ikut terhapus permanen. Lanjutkan?', 'red')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900 text-rose-500 transition" title="Hapus Data Ngajar">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>

                                {{-- Modal Transfer --}}
                                <template x-teleport="body">
                                    <div x-show="tfModal" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4" @keydown.escape.window="tfModal=false">
                                        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="tfModal=false" x-show="tfModal" x-transition.opacity></div>
                                        <div class="relative card w-full max-w-md p-6 text-left" x-show="tfModal" x-transition.scale.origin.center @click.outside="tfModal=false">
                                            <h3 class="font-bold text-lg text-slate-800 dark:text-slate-100 mb-2">Transfer Data Ngajar</h3>
                                            <p class="text-sm text-slate-500 mb-5 whitespace-normal">Pindahkan jadwal dan ruang kelas <strong>{{ $ngajar->pelajaran?->nama }}</strong> ({{ $ngajar->kelas ? 'Kelas '.$ngajar->kelas->tingkat.$ngajar->kelas->kelas : 'Semua Kelas' }}) ke guru lain.</p>
                                            
                                            <form method="POST" action="{{ route('guru.transferNgajar', $ngajar->uuid) }}">
                                                @csrf
                                                <div class="mb-5 text-left">
                                                    <label class="form-label">Pilih Guru Tujuan</label>
                                                    <select name="id_guru_tujuan" class="form-select" required x-init="new TomSelect($el, {create: false})">
                                                        <option value="">Pilih guru...</option>
                                                        @foreach($guruList as $g)
                                                        <option value="{{ $g->uuid }}">{{ $g->nama }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="flex items-center justify-end gap-3 mt-6">
                                                    <button type="button" @click="tfModal=false" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Batal</button>
                                                    <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2">
                                                        <i data-lucide="check" class="w-4 h-4"></i> Transfer
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center py-10 text-slate-400">
                        <i data-lucide="book-open" class="w-9 h-9 mx-auto mb-2 opacity-30"></i>
                        <p>Belum ada pelajaran yang diajar</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // PENTING: `defer` di tag <script> TIDAK ADA EFEKNYA di script INLINE (tanpa src) — browser
    // diam2 mengabaikannya & tetap menjalankannya SEKARANG JUGA saat parser sampai sini (sempat
    // salah kira ini "cukup ditambah defer" sebelum diverifikasi langsung di browser & ternyata
    // TomSelect belum tentu sempat termuat). Cara yg BENAR utk menunda kode INLINE spt ini adalah
    // membungkusnya dgn DOMContentLoaded — itu dijamin baru jalan SETELAH semua <script defer
    // src="..."> (termasuk TomSelect, yg beneran defer krn dia external) selesai dieksekusi.
    document.addEventListener('DOMContentLoaded', () => {
        const ngTakenMap = @js($takenMap ?? []);
        // Saat pilih pelajaran → nonaktifkan kelas yang SUDAH diajar guru lain (anti-bentrok)
        function ngApplyTaken(pel){
            const taken = ngTakenMap[pel] || {};
            document.querySelectorAll('.ng-lbl').forEach(lbl => {
                const k = lbl.dataset.kelas;
                const cb = lbl.querySelector('.ng-kelas');
                const note = lbl.querySelector('.ng-note');
                if(taken[k]){
                    cb.checked = false; cb.disabled = true;
                    lbl.classList.add('opacity-50','cursor-not-allowed','pointer-events-none');
                    lbl.title = 'Sudah diajar: ' + taken[k];
                    note.textContent = taken[k];
                } else {
                    cb.disabled = false;
                    lbl.classList.remove('opacity-50','cursor-not-allowed','pointer-events-none');
                    lbl.title = '';
                    note.textContent = '';
                }
            });
        }
        const ngPelTom = new TomSelect('#ngPelajaran', { create:false, onChange: ngApplyTaken });
        ngApplyTaken(ngPelTom.getValue());
    });
</script>
@endpush
@endsection
