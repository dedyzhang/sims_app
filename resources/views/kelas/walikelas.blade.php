@extends('layouts.app')
@section('title', 'Set Wali Kelas')

@section('content')
@php $breadcrumbs = [['label'=>'Data Kelas','url'=>route('kelas.index')], ['label'=>'Wali Kelas','url'=>'#']]; @endphp

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('kelas.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Wali Kelas</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelas {{ $kelas->tingkat }}{{ $kelas->kelas }}</p>
        </div>
    </div>

    @if($kelas->walikelas)
    <div class="flex items-center gap-3 mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900 grid place-items-center flex-shrink-0">
            <i data-lucide="user-check" class="w-5 h-5 text-emerald-600"></i>
        </div>
        <div>
            <p class="text-xs text-emerald-600 dark:text-emerald-400">Wali kelas saat ini</p>
            <p class="font-bold text-emerald-800 dark:text-emerald-300">{{ $kelas->walikelas->guru?->nama }}</p>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('kelas.walikelas', $kelas->uuid) }}" class="card p-6 space-y-4">
        @csrf
        <div>
            <label class="form-label">Pilih Guru sebagai Wali Kelas</label>
            <select name="id_guru" required class="form-select" data-tom>
                <option value="">— Pilih Guru —</option>
                @foreach($gurus as $g)
                <option value="{{ $g->uuid }}" @selected($kelas->walikelas?->id_guru === $g->uuid)>{{ $g->nama }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="user-check" class="w-4 h-4"></i> Simpan Wali Kelas
        </button>
    </form>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-tom]').forEach(el => new TomSelect(el, { create:false }));
</script>
@endpush
@endsection
