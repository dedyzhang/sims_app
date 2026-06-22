@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">

    {{-- Hero card --}}
    <div class="relative overflow-hidden rounded-2xl shadow-lg" style="background:linear-gradient(120deg,var(--cp),var(--cps) 55%,var(--ca))">
        <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
        <div class="absolute right-24 -bottom-10 w-28 h-28 rounded-full bg-white/10"></div>
        <a href="{{ route('profile.edit') }}" class="absolute top-4 right-4 z-20 flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 text-white text-xs font-semibold transition backdrop-blur">
            <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit Profil
        </a>
        <div class="relative z-10 px-6 py-7 flex items-center gap-4">
            @php $profFace = $user->siswa?->face_photo_url ?? $user->guru?->face_photo_url; @endphp
            <div class="w-20 h-20 rounded-2xl grid place-items-center text-3xl font-black flex-shrink-0 bg-white shadow-lg overflow-hidden {{ $profFace ? 'cursor-zoom-in' : '' }}" style="color:var(--cp)" @if($profFace) @click="avatarZoom=true" title="Lihat foto profil" @endif>
                @if($profFace)<img src="{{ $profFace }}" class="w-full h-full object-cover" alt="foto profil">@else{{ strtoupper(substr($user->guru?->nama ?? $user->siswa?->nama ?? $user->username, 0, 1)) }}@endif
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white drop-shadow-sm">
                    {{ $user->guru?->nama ?? $user->siswa?->nama ?? $user->username }}
                </h2>
                <div class="flex items-center gap-2 mt-2">
                    <span class="badge bg-white/25 text-white backdrop-blur capitalize">{{ $user->access }}</span>
                    <span class="badge bg-white/20 text-white backdrop-blur font-mono">{{ '@'.$user->username }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail --}}
    <div class="card p-6">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
            <i data-lucide="id-card" class="w-[18px] h-[18px] text-primary"></i> Informasi
        </h3>
        @if($user->guru)
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            @foreach([
                ['NIK / NIP', $user->guru->nik ?? $user->guru->nip ?? '-'],
                ['Jenis Kelamin', $user->guru->jk === 'L' ? 'Laki-laki' : 'Perempuan'],
                ['Tempat Lahir', $user->guru->tempat_lahir ?? '-'],
                ['Tanggal Lahir', $user->guru->tanggal_lahir ? \Carbon\Carbon::parse($user->guru->tanggal_lahir)->isoFormat('D MMMM Y') : '-'],
                ['Agama', $user->guru->agama ?? '-'],
                ['No. Telepon', $user->guru->no_telp ?? '-'],
            ] as [$label, $val])
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                <p class="text-slate-400 text-[11px] uppercase tracking-wide font-semibold mb-1">{{ $label }}</p>
                <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $val }}</p>
            </div>
            @endforeach
        </div>
        @elseif($user->siswa)
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            @foreach([
                ['NIS', $user->siswa->nis ?? '-'],
                ['NISN', $user->siswa->nisn ?? '-'],
                ['Kelas', $user->siswa->kelas ? $user->siswa->kelas->tingkat . $user->siswa->kelas->kelas : '-'],
                ['Jenis Kelamin', $user->siswa->jk === 'L' ? 'Laki-laki' : 'Perempuan'],
                ['Tempat Lahir', $user->siswa->tempat_lahir ?? '-'],
                ['Tanggal Lahir', $user->siswa->tanggal_lahir ? \Carbon\Carbon::parse($user->siswa->tanggal_lahir)->isoFormat('D MMMM Y') : '-'],
            ] as [$label, $val])
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                <p class="text-slate-400 text-[11px] uppercase tracking-wide font-semibold mb-1">{{ $label }}</p>
                <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $val }}</p>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-500">Akun sistem — tidak ada data tambahan.</p>
        @endif
    </div>

    {{-- Keamanan --}}
    <div class="card p-6">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
            <i data-lucide="shield-check" class="w-[18px] h-[18px] text-primary"></i> Keamanan &amp; Tampilan
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 {{ ($user->siswa || $user->guru) ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-3">
            <a href="{{ route('ganti.password') }}" class="group flex items-center gap-3 p-4 rounded-xl border border-slate-100 dark:border-slate-700 card-hover">
                <span class="grid place-items-center w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 flex-shrink-0"><i data-lucide="key-round" class="w-5 h-5"></i></span>
                <div><p class="text-sm font-bold text-slate-700 dark:text-slate-200">Password</p><p class="text-xs text-slate-400">Ubah kata sandi</p></div>
            </a>
            <a href="{{ route('ganti.pin') }}" class="group flex items-center gap-3 p-4 rounded-xl border border-slate-100 dark:border-slate-700 card-hover">
                <span class="grid place-items-center w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-900/30 text-violet-600 flex-shrink-0"><i data-lucide="lock-keyhole" class="w-5 h-5"></i></span>
                <div><p class="text-sm font-bold text-slate-700 dark:text-slate-200">PIN</p><p class="text-xs text-slate-400">Atur PIN 6 digit</p></div>
            </a>
            <a href="{{ route('profile.preference') }}" class="group flex items-center gap-3 p-4 rounded-xl border border-slate-100 dark:border-slate-700 card-hover">
                <span class="grid place-items-center w-10 h-10 rounded-xl bg-primary-50 text-primary flex-shrink-0"><i data-lucide="palette" class="w-5 h-5"></i></span>
                <div><p class="text-sm font-bold text-slate-700 dark:text-slate-200">Tampilan</p><p class="text-xs text-slate-400">Tema &amp; warna</p></div>
            </a>
            @if($user->siswa || $user->guru)
            <a href="{{ route('face.self', ['ulang'=>1]) }}" class="group flex items-center gap-3 p-4 rounded-xl border border-slate-100 dark:border-slate-700 card-hover">
                <span class="grid place-items-center w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 flex-shrink-0"><i data-lucide="scan-face" class="w-5 h-5"></i></span>
                <div><p class="text-sm font-bold text-slate-700 dark:text-slate-200">Wajah</p><p class="text-xs text-slate-400">{{ $profFace ? 'Daftar ulang wajah' : 'Daftarkan wajah' }}</p></div>
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
