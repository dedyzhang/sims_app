@extends('layouts.app')
@section('title', 'Ekstrakurikuler')

@section('content')
<div class="space-y-5" x-data="{ editOpen:false, ed:{ uuid:'', nama:'', guru:'', pel:'' } }">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Ekstrakurikuler</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Nilai ekskul berupa deskripsi — manual atau diolah dari nilai rapor mapel.</p>
        </div>
    </div>

    @if($isAdmin)
    {{-- Tambah ekskul --}}
    <form method="POST" action="{{ route('ekskul.store') }}" class="card p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
        @csrf
        <div>
            <label class="form-label">Nama Ekskul</label>
            <input type="text" name="nama" required placeholder="mis. Pramuka" class="form-input">
        </div>
        <div>
            <label class="form-label">Pembina (Guru)</label>
            <select name="id_guru" class="form-select">
                <option value="">— pilih guru —</option>
                @foreach($gurus as $g)<option value="{{ $g->uuid }}">{{ $g->nama }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Ambil dari Mapel <span class="text-slate-400 font-normal">(opsional)</span></label>
            <select name="id_pelajaran" class="form-select">
                <option value="">— manual (ketik sendiri) —</option>
                @foreach($pelajarans as $p)<option value="{{ $p->uuid }}">{{ $p->nama }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm"><i data-lucide="plus" class="w-4 h-4"></i> Tambah</button>
    </form>
    @endif

    @if($ekskuls->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="volleyball" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada ekskul.</p>
        <p class="text-sm mt-1">{{ $isAdmin ? 'Tambahkan ekskul di atas.' : 'Belum ada ekskul yang Anda bina.' }}</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($ekskuls as $e)
        <div class="card p-4 flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $e->nama }}</p>
                    <p class="text-xs text-slate-500 flex items-center gap-1.5 mt-0.5"><i data-lucide="user" class="w-3.5 h-3.5"></i> {{ $e->guru?->nama ?? '—' }}</p>
                </div>
                @if($e->dariMapel())
                <span class="badge bg-primary/10 text-primary flex-shrink-0" title="Deskripsi diolah dari rapor mapel ini">↳ {{ $e->pelajaran?->nama }}</span>
                @else
                <span class="badge bg-slate-100 text-slate-500 dark:bg-slate-700 flex-shrink-0">Manual</span>
                @endif
            </div>
            <div class="flex items-center gap-2 mt-1">
                <a href="{{ route('ekskul.nilai', $e->uuid) }}" class="flex-1 flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-bold bg-primary/10 text-primary hover:bg-primary/20 transition">
                    @if($e->dariMapel())<i data-lucide="eye" class="w-3.5 h-3.5"></i> Lihat Nilai @else<i data-lucide="pencil-line" class="w-3.5 h-3.5"></i> Input Nilai @endif
                </a>
                @if($isAdmin)
                <button type="button" @click="ed={ uuid:'{{ $e->uuid }}', nama:@js($e->nama), guru:'{{ $e->id_guru }}', pel:'{{ $e->id_pelajaran }}' }; editOpen=true" class="grid place-items-center w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-500 hover:text-primary transition"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                <form method="POST" action="{{ route('ekskul.destroy', $e->uuid) }}" onsubmit="return confirmDelete(this)">
                    @csrf @method('DELETE')
                    <button type="submit" class="grid place-items-center w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-600 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </form>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Modal edit (admin) --}}
    @if($isAdmin)
    <div x-show="editOpen" x-cloak style="display:none" @keydown.escape.window="editOpen=false" @click.self="editOpen=false"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(15,12,10,.55)">
        <form method="POST" :action="'{{ url('ekskul') }}/'+ed.uuid" class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-5 space-y-3">
            @csrf @method('PUT')
            <h3 class="font-bold text-slate-800 dark:text-slate-100">Edit Ekskul</h3>
            <div><label class="form-label">Nama Ekskul</label><input type="text" name="nama" x-model="ed.nama" required class="form-input"></div>
            <div><label class="form-label">Pembina (Guru)</label>
                <select name="id_guru" x-model="ed.guru" class="form-select"><option value="">— pilih guru —</option>@foreach($gurus as $g)<option value="{{ $g->uuid }}">{{ $g->nama }}</option>@endforeach</select>
            </div>
            <div><label class="form-label">Ambil dari Mapel (opsional)</label>
                <select name="id_pelajaran" x-model="ed.pel" class="form-select"><option value="">— manual (ketik sendiri) —</option>@foreach($pelajarans as $p)<option value="{{ $p->uuid }}">{{ $p->nama }}</option>@endforeach</select>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="editOpen=false" class="px-3 py-2 rounded-lg text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</button>
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>
    @endif
    @endif
</div>
@endsection
