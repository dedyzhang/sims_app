@extends('layouts.app')
@section('title', 'Data Guru')

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Data Guru</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ $gurus->total() }} guru terdaftar</p>
        </div>
        <a href="{{ route('guru.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Guru
        </a>
    </div>

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
</div>

@push('scripts')
<script>
function confirmAction(form, message) {
    $.confirm({
        title: 'Konfirmasi',
        content: message,
        type: 'orange',
        theme: 'material',
        buttons: {
            ya: {
                text: 'Ya, Lanjutkan',
                btnClass: 'btn-warning',
                action: function() { form.submit(); }
            },
            tidak: { text: 'Batal' }
        }
    });
    return false;
}
</script>
@endpush
@endsection
