@extends('layouts.app')
@section('title', 'Agenda Guru')

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Agenda Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Catat agenda mengajar harian sesuai jadwal pelajaran</p>
        </div>
        @if($guru)
        <a href="{{ route('agenda.create', ['tanggal' => $tanggal ?: now()->toDateString()]) }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Agenda
        </a>
        @endif
    </div>

    @unless($guru)
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Akun ini tidak memiliki profil guru.</p>
        <p class="text-sm mt-1">Agenda hanya dapat diisi oleh akun yang terhubung ke data guru.</p>
    </div>
    @else

    @unless($mengajar)
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="calendar-off" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Anda belum memiliki jadwal mengajar.</p>
        <p class="text-sm mt-1">Agenda mengikuti jadwal pelajaran. Belum ada jam yang perlu diisi.</p>
    </div>
    @else

    {{-- Ringkasan + pilih tanggal / periode --}}
    <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                @if(empty($daftar))
                Tidak ada jam mengajar {{ $tanggal ? 'pada tanggal ini' : 'pada periode ini' }}.
                @elseif($belum > 0)
                <span class="font-bold text-amber-600 dark:text-amber-400">{{ $belum }}</span> dari {{ count($daftar) }} jam mengajar <span class="font-semibold">belum diisi</span> agendanya.
                @else
                <span class="font-bold text-emerald-600 dark:text-emerald-400">Semua</span> agenda {{ $tanggal ? 'pada tanggal ini' : 'dalam periode ini' }} sudah terisi. 🎉
                @endif
            </p>

            {{-- Pilih tanggal --}}
            <form method="GET" action="{{ route('agenda.index') }}" class="flex items-end gap-2">
                <div>
                    <label class="form-label !mb-1">Lihat tanggal</label>
                    <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input !py-2 text-sm" onchange="this.form.submit()">
                </div>
                @if($tanggal)
                <a href="{{ route('agenda.index') }}" class="px-3 py-2 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Semua</a>
                @endif
            </form>
        </div>

        {{-- Periode (saat tidak memfilter satu tanggal) --}}
        @unless($tanggal)
        <div class="flex items-center gap-1.5 border-t border-slate-100 dark:border-slate-700 pt-3">
            <span class="text-xs text-slate-400">Periode:</span>
            @foreach([7 => '7 hari', 14 => '14 hari', 30 => '30 hari'] as $h => $lbl)
            <a href="{{ route('agenda.index', ['hari' => $h]) }}" class="px-2.5 py-1 rounded-lg text-xs font-semibold border {{ $hari===$h ? 'text-white border-transparent' : 'border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}" @if($hari===$h) style="background:var(--cp)" @endif>{{ $lbl }}</a>
            @endforeach
        </div>
        @endunless
    </div>

    {{-- Daftar dikelompokkan per tanggal, tiap slot diberi badge status --}}
    @if(empty($daftar))
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="calendar-off" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Tidak ada jam mengajar {{ $tanggal ? 'pada ' . \Carbon\Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') : 'pada periode ini' }}.</p>
        @if($tanggal)<p class="text-sm mt-1">Hari libur/akhir pekan tidak memiliki jadwal.</p>@endif
    </div>
    @else
    <div class="space-y-6">
        @foreach(collect($daftar)->groupBy('tanggal') as $tgl => $items)
        @php
            $first = $items->first();
            $belumHari = $items->filter(fn($x) => !$x['agenda'])->count();
            $c = \Carbon\Carbon::parse($tgl)->locale('id');
        @endphp
        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-slate-100 dark:border-slate-700/60 first:pt-0 first:border-0">

            {{-- Rail tanggal (kiri) --}}
            <div class="sm:w-44 flex-shrink-0">
                <div class="sm:sticky sm:top-24 flex sm:flex-col items-center sm:items-start gap-2 sm:gap-1.5">
                    <div class="flex items-baseline sm:items-end gap-2 sm:gap-0 sm:flex-col">
                        <p class="font-extrabold text-slate-800 dark:text-slate-100 leading-none">{{ $c->isoFormat('dddd') }}</p>
                        <p class="text-sm text-slate-400 dark:text-slate-500 sm:mt-1">{{ $c->isoFormat('D MMMM Y') }}</p>
                    </div>
                    @if($first['hari_ini'])
                    <span class="badge bg-amber-500 text-white font-semibold">Hari ini</span>
                    @endif
                    @if($belumHari > 0)
                    <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 font-semibold">{{ $belumHari }} belum diisi</span>
                    @else
                    <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold">Lengkap</span>
                    @endif
                </div>
            </div>

            {{-- Kartu per slot (kanan, mengalir 2–3 kolom) --}}
            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                @foreach($items->sortBy('jam_mulai') as $d)
                @php $ag = $d['agenda']; @endphp
                <div class="card p-4 flex flex-col gap-2.5 {{ $ag ? '' : 'border-amber-200 dark:border-amber-800 bg-amber-50/30 dark:bg-amber-900/10' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $d['pelajaran'] }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-col gap-0.5">
                                <span class="inline-flex items-center gap-1"><i data-lucide="door-open" class="w-3.5 h-3.5 text-slate-400"></i> Kelas {{ $d['kelas'] }}</span>
                                <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i> {{ $d['jam_mulai'] }}–{{ $d['jam_selesai'] }}</span>
                            </p>
                        </div>
                        @if($ag)
                        <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold flex items-center gap-1 flex-shrink-0"><i data-lucide="check" class="w-3 h-3"></i> Sudah diisi</span>
                        @else
                        <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 font-semibold flex-shrink-0">Belum</span>
                        @endif
                    </div>

                    @if($ag)
                    <div class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 border-t border-slate-100 dark:border-slate-700 pt-2 flex-1">
                        <span class="font-semibold text-slate-600 dark:text-slate-300">Pembahasan:</span> {{ \Illuminate\Support\Str::limit($ag->pembahasan, 90) }}
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('agenda.edit', $ag) }}" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:border-primary text-slate-600 dark:text-slate-300">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Lihat / Edit
                        </a>
                        <form method="POST" action="{{ route('agenda.destroy', $ag) }}" onsubmit="return confirmDelete(this)">
                            @csrf @method('DELETE')
                            <button class="flex items-center justify-center w-8 h-8 rounded-lg text-xs font-semibold border border-rose-200 dark:border-rose-800 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30" title="Hapus">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                    @else
                    <a href="{{ route('agenda.create', ['tanggal' => $d['tanggal'], 'jadwal' => $d['id_jadwal']]) }}" class="btn-primary flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold mt-auto">
                        <i data-lucide="clipboard-pen-line" class="w-3.5 h-3.5"></i> Isi Agenda
                    </a>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @endunless
    @endunless
</div>
@endsection
