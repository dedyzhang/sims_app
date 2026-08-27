@extends('layouts.app')
@section('title', 'Bank Soal — ' . $pelajaran->nama)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('bank-soal.index') }}" class="hover:underline">Bank Soal</a> / {{ $pelajaran->nama }}
        </nav>
        <h1 class="page-title">Bank Soal — {{ $pelajaran->nama }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $soal->count() }} soal tersimpan. Dikelola bersama semua guru yang mengajar {{ $pelajaran->nama }}.</p>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Daftar soal --}}
    <div class="space-y-3">
        @foreach($soal as $i => $s)
        <div class="card p-5" x-data="soalForm({
                tipe: '{{ $s->tipe }}',
                poin: {{ $s->poin }},
                skor_mode: {{ Js::from($s->skor_mode ?? 'all_or_nothing') }},
                open: false,
                rendered: false,
                fetchUrl: '{{ route('bank-soal.soal.data', [$pelajaran, $s]) }}'
              })"
             x-init="_rootEl = $el">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="toggle()">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 grid place-items-center text-xs font-bold flex-shrink-0">
                        <template x-if="loadingData"><i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i></template>
                        <template x-if="!loadingData"><span>{{ $i + 1 }}</span></template>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-slate-400">{{ $s->typeLabel() }} · {{ $s->poinEfektif() }} poin</p>
                        <p class="text-sm font-medium truncate">{{ Str::limit(strip_tags($s->teks_soal), 80) }}</p>
                    </div>
                </div>
                <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 flex-shrink-0 transition" :class="open && 'rotate-180'"></i>
            </div>

            <form x-show="open" x-cloak x-transition method="POST" action="{{ route('bank-soal.soal.update', [$pelajaran, $s]) }}" class="mt-4 space-y-3 border-t border-slate-100 dark:border-slate-700 pt-4">
                <template x-if="rendered">
                    <div>
                        @csrf
                        @include('ujian.partials.soal-fields')
                        <div class="flex gap-2 pt-1">
                            <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold">Simpan Perubahan</button>
                            <button type="button" @click="if(confirm('Hapus permanen soal ini dari Bank?')) document.getElementById('hapus-soal-{{ $s->uuid }}').submit()" class="px-4 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 ml-auto">Hapus</button>
                        </div>
                    </div>
                </template>
            </form>
        </div>
        <form id="hapus-soal-{{ $s->uuid }}" method="POST" action="{{ route('bank-soal.soal.destroy', [$pelajaran, $s]) }}" class="hidden">@csrf @method('DELETE')</form>
        @endforeach
    </div>

    {{-- Tambah soal baru --}}
    <div class="card p-5" x-data="soalForm({ tipe: 'mcq', teks_soal: '', poin: 1, penjelasan: '', opsi: [{teks:'',benar:true},{teks:'',benar:false}], pasangan: [{kiri:'',kanan:''},{kiri:'',kanan:''}], kunci_esai: '', skor_mode: 'all_or_nothing', open: true })"
         x-init="_rootEl = $el; $nextTick(() => window.UjianEditor && window.UjianEditor.mountAll())">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-primary"></i> Tambah Soal ke Bank</h2>
        <form method="POST" action="{{ route('bank-soal.soal.store', $pelajaran) }}" class="space-y-3">
            @csrf
            @include('ujian.partials.soal-fields')
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold">Tambah ke Bank</button>
        </form>
    </div>

    <div class="flex justify-end">
        <a href="{{ route('bank-soal.index') }}" class="text-sm text-primary hover:underline">← Kembali ke daftar mapel</a>
    </div>
</div>
@endsection

@push('scripts')
@include('ujian.partials.soal-form-script')
@endpush
