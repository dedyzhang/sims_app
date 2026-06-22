@extends('layouts.app')
@section('title', 'Cek Wajah Ganda')

@section('content')
<div class="space-y-5" x-data="{ zoomSrc:null }">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('wajah.galeri') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="page-title">Cek Wajah Ganda</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pasangan wajah yang sangat mirip — kemungkinan orang yang sama terdaftar di beberapa akun.</p>
            </div>
        </div>
    </div>

    {{-- Filter ambang --}}
    <form method="GET" action="{{ route('wajah.ganda') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="min-w-52">
            <label class="form-label">Ambang kemiripan: <span class="font-bold text-primary">{{ round($min*100) }}%</span></label>
            <input type="range" name="min" min="0.7" max="0.99" step="0.01" value="{{ $min }}" class="w-full accent-[color:var(--cp)]" oninput="this.previousElementSibling && (this.form.querySelector('.minlbl').textContent=Math.round(this.value*100)+'%')" onchange="this.form.submit()">
        </div>
        <p class="text-xs text-slate-400 flex-1">Makin tinggi ambang = makin ketat (hanya yang benar-benar mirip). Geser lalu lepas untuk memuat ulang.</p>
    </form>

    @if(empty($pairs))
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="shield-check" class="w-12 h-12 mx-auto mb-3 text-emerald-400 opacity-60"></i>
        <p class="font-medium text-slate-600 dark:text-slate-300">Tidak ada wajah ganda terdeteksi.</p>
        <p class="text-sm mt-1">Tidak ada pasangan wajah dengan kemiripan ≥ {{ round($min*100) }}%.</p>
    </div>
    @else
    <p class="text-sm text-slate-500">Ditemukan <span class="font-bold text-rose-600">{{ count($pairs) }}</span> pasangan mirip (≥ {{ round($min*100) }}%). Periksa secara visual — bila benar orang yang sama, hapus salah satu pendaftaran wajah.</p>

    <div class="grid sm:grid-cols-2 gap-3">
        @foreach($pairs as $p)
        @php $pct = round($p['similarity']*100); @endphp
        <div class="card p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="badge {{ $pct>=95 ? 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' }} font-bold">Mirip {{ $pct }}%</span>
                <span class="text-xs text-slate-400">{{ $pct>=95 ? 'kemungkinan besar sama' : 'perlu dicek' }}</span>
            </div>
            <div class="flex items-center gap-3">
                @foreach([$p['a'], $p['b']] as $idx => $person)
                <div class="flex-1 text-center">
                    <div class="aspect-square rounded-xl overflow-hidden mb-1.5 grid place-items-center text-white text-xl font-bold bg-slate-300 dark:bg-slate-600">
                        @if($person['foto'])
                        <img src="{{ $person['foto'] }}" loading="lazy" class="w-full h-full object-cover cursor-zoom-in" @click="zoomSrc=@js($person['foto'])">
                        @else
                        {{ strtoupper(substr($person['nama'],0,1)) }}
                        @endif
                    </div>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $person['nama'] }}</p>
                    <span class="text-[10px] px-1.5 py-0.5 rounded {{ $person['tipe']==='guru' ? 'bg-primary-50 text-primary' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }}">{{ ucfirst($person['tipe']) }}</span>
                </div>
                @if($idx===0)<div class="flex flex-col items-center text-slate-300"><i data-lucide="git-compare" class="w-5 h-5"></i></div>@endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    <p class="text-xs text-slate-400 mt-2">Catatan: wajah tanpa foto (didaftarkan sebelum fitur foto) tetap dibandingkan via data biometrik, namun tampil sebagai inisial.</p>
    @endif

    {{-- Zoom --}}
    <div x-show="zoomSrc" x-cloak class="modal-backdrop" style="display:none" @click="zoomSrc=null" x-transition>
        <div @click.stop class="text-center">
            <img :src="zoomSrc" class="max-h-[72vh] max-w-[90vw] rounded-2xl shadow-2xl ring-4 ring-white/20">
            <p class="text-white/60 text-xs mt-3">Klik di mana saja untuk menutup</p>
        </div>
    </div>
</div>
@endsection
