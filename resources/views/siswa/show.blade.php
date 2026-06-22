@extends('layouts.app')
@section('title', 'Detail Siswa')

@section('content')
@php $breadcrumbs = [['label'=>'Data Siswa','url'=>route('siswa.index')], ['label'=>$siswa->nama,'url'=>'#']]; @endphp

<div class="max-w-4xl mx-auto space-y-5">

    {{-- Hero --}}
    @php $heroGrad = $siswa->jk==='L' ? 'var(--cp),var(--cps) 55%,var(--ca)' : '#ec4899,#db2777 60%,var(--ca)'; @endphp
    <div class="relative overflow-hidden rounded-2xl shadow-lg" style="background:linear-gradient(120deg,{{ $heroGrad }})" x-data="{ fz:false }">
        <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
        <div class="absolute right-24 -bottom-10 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="absolute top-4 right-4 flex gap-2 z-20">
            <a href="{{ route('siswa.edit', $siswa->uuid) }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 text-white text-xs font-semibold transition backdrop-blur">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
            </a>
            <a href="{{ route('siswa.index') }}" class="grid place-items-center w-8 h-8 rounded-lg bg-white/20 hover:bg-white/30 text-white transition backdrop-blur">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="relative z-10 px-6 py-7 flex items-center gap-4">
            <div class="w-20 h-20 rounded-2xl grid place-items-center text-3xl font-black flex-shrink-0 bg-white shadow-lg overflow-hidden {{ $siswa->face_photo ? 'cursor-zoom-in' : '' }}"
                 style="color:{{ $siswa->jk==='L' ? 'var(--cp)' : '#db2777' }}" @if($siswa->face_photo) @click="fz=true" title="Lihat foto" @endif>
                @if($siswa->face_photo)<img src="{{ $siswa->face_photo_url }}" class="w-full h-full object-cover" alt="Foto {{ $siswa->nama }}">@else{{ strtoupper(substr($siswa->nama, 0, 1)) }}@endif
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white drop-shadow-sm">{{ $siswa->nama }}</h2>
                <div class="flex items-center gap-2 mt-2 flex-wrap">
                    <span class="badge bg-white/25 text-white backdrop-blur font-mono">NIS {{ $siswa->nis ?? '-' }}</span>
                    @if($siswa->kelas)
                    <span class="badge bg-white/25 text-white backdrop-blur">Kelas {{ $siswa->kelas->tingkat }}{{ $siswa->kelas->kelas }}</span>
                    @else
                    <span class="badge bg-white/25 text-white backdrop-blur">Belum ada kelas</span>
                    @endif
                </div>
            </div>
        </div>

        @if($siswa->face_photo)
        {{-- Lightbox foto wajah --}}
        <div x-show="fz" x-cloak @click="fz=false" @keydown.escape.window="fz=false" class="fixed inset-0 z-[10000] flex items-center justify-center p-6" style="display:none; background:rgba(15,12,10,.78); backdrop-filter:blur(6px)">
            <div class="text-center" @click.stop>
                <img src="{{ $siswa->face_photo_url }}" class="max-h-[78vh] max-w-[92vw] rounded-3xl shadow-2xl ring-4 ring-white/15" alt="Foto {{ $siswa->nama }}">
                <p class="text-white/80 mt-3 font-semibold">{{ $siswa->nama }}</p>
                <p class="text-white/50 text-xs">Klik di mana saja untuk menutup</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Identitas Lengkap Siswa --}}
    <div class="card p-6 md:p-8 space-y-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        
        {{-- Row 1: A & B --}}
        <div class="grid md:grid-cols-2 gap-8 md:gap-12">
            
            {{-- A. Identitas Pribadi --}}
            <div class="space-y-4">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                    A. Identitas Pribadi
                </h3>
                <div class="space-y-2">
                    
                    {{-- Row: Nama --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Nama</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->nama }}</div>
                    </div>
                    
                    {{-- Row: NIS --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">NIS</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold font-mono">{{ $siswa->nis ?? '-' }}</div>
                    </div>

                    {{-- Row: Jenis Kelamin --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Jenis Kelamin</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->jk === 'L' ? 'Laki-laki' : 'Perempuan' }}</div>
                    </div>

                    {{-- Row: Tempat/Tanggal Lahir --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Tempat/Tanggal Lahir</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">
                            {{ $siswa->tempat_lahir ?? '-' }} / {{ $siswa->tanggal_lahir ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->translatedFormat('F j, Y') : '-' }}
                        </div>
                    </div>

                    {{-- Row: Agama --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Agama</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ strtolower($siswa->agama ?? '-') }}</div>
                    </div>

                    {{-- Row: No Telepon --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">No Telepon</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->no_handphone ?? '-' }}</div>
                    </div>

                    {{-- Row: Alamat --}}
                    <div class="text-sm py-1">
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Alamat</div>
                            <div class="px-2 text-slate-400">:</div>
                        </div>
                        <div class="mt-1.5 pl-0 text-slate-700 dark:text-slate-300 leading-relaxed font-semibold">
                            {{ $siswa->alamat ?? '-' }}
                        </div>
                    </div>

                </div>
            </div>

            {{-- B. Identitas Sekolah --}}
            <div class="space-y-4">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                    B. Identitas Sekolah
                </h3>
                <div class="space-y-2">
                    
                    {{-- Row: NISN --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">NISN</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold font-mono">{{ $siswa->nisn ?? '-' }}</div>
                    </div>

                    {{-- Row: Sekolah Asal --}}
                    <div class="text-sm py-1">
                        <div class="flex items-start">
                            <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Sekolah Asal</div>
                            <div class="px-2 text-slate-400">:</div>
                        </div>
                        <div class="mt-1.5 pl-0 text-slate-700 dark:text-slate-300 leading-relaxed font-semibold">
                            {{ $siswa->sekolah_asal ?? '-' }}
                        </div>
                    </div>

                    {{-- Row: Nama Ijazah --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Nama Ijazah</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->nama_ijazah ?? '-' }}</div>
                    </div>

                    {{-- Row: Ortu Ijazah --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">Ortu Ijazah</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->ortu_ijazah ?? '-' }}</div>
                    </div>

                    {{-- Row: TTL Ijazah --}}
                    <div class="flex items-start text-sm py-1">
                        <div class="w-40 flex-shrink-0 text-slate-500 font-medium">TTL Ijazah</div>
                        <div class="px-2 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">
                            @if($siswa->tempat_lahir_ijazah || $siswa->tanggal_lahir_ijazah)
                                {{ $siswa->tempat_lahir_ijazah ?? '-' }} / {{ $siswa->tanggal_lahir_ijazah ? \Carbon\Carbon::parse($siswa->tanggal_lahir_ijazah)->translatedFormat('F j, Y') : '-' }}
                            @else
                                -
                            @endif
                        </div>
                    </div>

                </div>
            </div>

        </div>

        {{-- Row 2: C, D, E --}}
        <div class="grid md:grid-cols-3 gap-6 md:gap-8 pt-6 border-t border-slate-100 dark:border-slate-800">
            
            {{-- C. Identitas Ayah --}}
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 pb-2 border-b border-slate-100 dark:border-slate-800">
                    C. Identitas Ayah
                </h3>
                <div class="space-y-2">
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">nama Ayah</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->nama_ayah ?? '-' }}</div>
                    </div>
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">Pekerjaan Ayah</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->pekerjaan_ayah ?? '-' }}</div>
                    </div>
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">No Telepon Ayah</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold font-mono">{{ $siswa->no_telp_ayah ?? '-' }}</div>
                    </div>
                </div>
            </div>

            {{-- D. Identitas Ibu --}}
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 pb-2 border-b border-slate-100 dark:border-slate-800">
                    D. Identitas Ibu
                </h3>
                <div class="space-y-2">
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">nama Ibu</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->nama_ibu ?? '-' }}</div>
                    </div>
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">Pekerjaan Ibu</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->pekerjaan_ibu ?? '-' }}</div>
                    </div>
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">No Telepon Ibu</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold font-mono">{{ $siswa->no_telp_ibu ?? '-' }}</div>
                    </div>
                </div>
            </div>

            {{-- E. Identitas Wali --}}
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 pb-2 border-b border-slate-100 dark:border-slate-800">
                    E. Identitas Wali
                </h3>
                <div class="space-y-2">
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">nama Wali</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->nama_wali ?? '-' }}</div>
                    </div>
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">Pekerjaan Wali</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold">{{ $siswa->pekerjaan_wali ?? '-' }}</div>
                    </div>
                    <div class="flex items-start text-sm py-0.5">
                        <div class="w-28 flex-shrink-0 text-slate-500 font-medium">No Telepon Wali</div>
                        <div class="px-1.5 text-slate-400">:</div>
                        <div class="flex-1 text-slate-800 dark:text-slate-200 font-semibold font-mono">{{ $siswa->no_telp_wali ?? '-' }}</div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- Akun --}}
    <div class="card p-6 max-w-xl">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2"><i data-lucide="shield" class="w-[18px] h-[18px] text-primary"></i> Akun Login</h3>
        <div class="space-y-2.5">
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                <div>
                    <p class="text-xs text-slate-400">Akun Siswa</p>
                    <p class="font-mono font-semibold text-slate-700 dark:text-slate-200 text-sm">{{ $siswa->user?->username ?? '-' }}</p>
                </div>
                <form method="POST" action="{{ route('siswa.reset', $siswa->uuid) }}" onsubmit="return confirmAction(this, 'Reset password siswa?')">
                    @csrf
                    <button class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 hover:bg-amber-100 transition">
                        <i data-lucide="key-round" class="w-3 h-3"></i> Reset
                    </button>
                </form>
            </div>
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                <div>
                    <p class="text-xs text-slate-400">Akun Orang Tua</p>
                    <p class="font-mono font-semibold text-slate-700 dark:text-slate-200 text-sm">{{ $siswa->orangtua?->user?->username ?? '-' }}</p>
                </div>
                <form method="POST" action="{{ route('siswa.resetOrtu', $siswa->uuid) }}" onsubmit="return confirmAction(this, 'Reset password orang tua?')">
                    @csrf
                    <button class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 hover:bg-amber-100 transition">
                        <i data-lucide="key-round" class="w-3 h-3"></i> Reset
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
