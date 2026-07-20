@extends('layouts.app')
@section('title', 'Pantau Lokasi')

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
<style>
    #pantauMap { height: min(62vh, 520px); min-height: 320px; border-radius: 16px; z-index: 0; }
</style>
@endpush

@section('content')
<div class="space-y-4" x-data="pantauLokasi({
    markers: @js($markers),
    schoolLat: @js($schoolLat),
    schoolLng: @js($schoolLng),
    radius: {{ $radius }},
})" x-init="init()">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Pantau Lokasi</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Riwayat titik GPS absen QR di dalam area sekolah saja — bukan pelacakan live.
                Titik di luar radius sekolah tidak ditampilkan.
            </p>
        </div>
        <span class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-semibold">
            {{ count($markers) }} titik pada {{ \Illuminate\Support\Carbon::parse($tanggal)->isoFormat('D MMM Y') }}
        </span>
    </div>

    @if($titikTerpotong ?? false)
        {{-- Pembatasan jangan senyap: beri tahu agar pengguna mempersempit filter. --}}
        <div class="rounded-xl bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 text-amber-800 dark:text-amber-300 px-4 py-3 text-sm flex items-start gap-2">
            <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
            <span>
                Menampilkan <b>{{ number_format($maxTitik, 0, ',', '.') }}</b> titik pertama dari
                <b>{{ number_format($totalKandidat, 0, ',', '.') }}</b> pada tanggal ini.
                Persempit dengan filter kelas atau siswa untuk melihat sisanya.
            </span>
        </div>
    @endif

    <form method="GET" action="{{ route('pantau-lokasi.index') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input">
        </div>
        @if($schoolWide || $walikelasKelas)
        <div class="min-w-[10rem]">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" @if($walikelasKelas) disabled @endif>
                @if($schoolWide)
                <option value="">Semua kelas</option>
                @endif
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selectedKelas === $k->uuid)>{{ $k->tingkat }} {{ $k->kelas }}</option>
                @endforeach
            </select>
            @if($walikelasKelas)
            <input type="hidden" name="kelas" value="{{ $walikelasKelas }}">
            @endif
        </div>
        @endif
        @if($siswaList->isNotEmpty())
        <div class="min-w-[12rem] flex-1">
            <label class="form-label">{{ $isOrtu ? 'Anak' : 'Siswa' }}</label>
            <select name="siswa" class="form-select">
                @unless($isOrtu)
                <option value="">Semua siswa</option>
                @endunless
                @foreach($siswaList as $s)
                <option value="{{ $s->uuid }}" @selected($selectedSiswa === $s->uuid)>{{ $s->nama }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-bold flex items-center gap-1.5">
            <i data-lucide="filter" class="w-4 h-4"></i> Tampilkan
        </button>
    </form>

    <div class="card p-3 space-y-2">
        <div class="relative rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700">
            <div id="pantauMap"></div>
            <div class="absolute top-3 right-3 z-[1000] flex rounded-lg overflow-hidden shadow-md border border-white/40 bg-white/95 dark:bg-slate-800/95 text-xs font-bold">
                <button type="button" @click="setBase('street')"
                    :class="baseMode==='street' ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-300'"
                    class="px-2.5 py-1.5 transition">Peta</button>
                <button type="button" @click="setBase('satellite')"
                    :class="baseMode==='satellite' ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-300'"
                    class="px-2.5 py-1.5 transition">Satelit</button>
            </div>
        </div>
        <p class="text-xs text-slate-400 px-1">
            Lingkaran hijau = area sekolah (radius {{ (int) $radius }} m + toleransi {{ (int) ($effectiveRadius - $radius) }} m).
            Hanya titik di dalam area ini yang ditampilkan.
            @if(count($markers) === 0)
            Belum ada titik GPS absen di dalam area pada filter ini.
            @endif
        </p>
    </div>

    @if(count($markers) > 0)
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Jam</th>
                        <th>Status</th>
                        <th>Jarak</th>
                        <th>Akurasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($markers as $m)
                    <tr>
                        <td class="font-medium text-slate-700 dark:text-slate-200">{{ $m['nama'] }}</td>
                        <td>{{ $m['kelas'] }}</td>
                        <td class="font-mono">{{ $m['jam'] }}</td>
                        <td>{{ $m['status'] }}</td>
                        <td>{{ $m['jarak'] !== null ? $m['jarak'].' m' : '—' }}</td>
                        <td>{{ $m['accuracy'] !== null ? '±'.$m['accuracy'].' m' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/geo-map-layers.js') }}"></script>
<script>
function pantauLokasi(cfg){
    return {
        markers: cfg.markers || [],
        schoolLat: cfg.schoolLat ? parseFloat(cfg.schoolLat) : null,
        schoolLng: cfg.schoolLng ? parseFloat(cfg.schoolLng) : null,
        radius: cfg.radius || 200,
        map:null, baseCtrl:null, baseMode:'street',
        init(){
            this.$nextTick(()=>{
                lucide.createIcons();
                const el = document.getElementById('pantauMap');
                if(!el || typeof L === 'undefined') return;
                let start = [-0.9177, 104.4602];
                let zoom = 12;
                if(this.schoolLat && this.schoolLng){
                    start = [this.schoolLat, this.schoolLng];
                    zoom = 16;
                } else if(this.markers.length){
                    start = [this.markers[0].lat, this.markers[0].lng];
                    zoom = 16;
                }
                this.map = L.map('pantauMap').setView(start, zoom);
                this.baseCtrl = SimsMapLayers.attach(this.map, this.baseMode);
                this.baseMode = this.baseCtrl.mode;
                if(this.schoolLat && this.schoolLng){
                    L.marker([this.schoolLat, this.schoolLng]).addTo(this.map).bindPopup('Sekolah');
                    L.circle([this.schoolLat, this.schoolLng], {
                        radius: this.radius, color:'#10b981', weight:2,
                        fillColor:'#10b981', fillOpacity:0.12
                    }).addTo(this.map);
                }
                const bounds = [];
                if(this.schoolLat && this.schoolLng) bounds.push([this.schoolLat, this.schoolLng]);
                this.markers.forEach(m=>{
                    const mk = L.circleMarker([m.lat, m.lng], {
                        radius:7, color:'#fff', weight:2, fillColor:'#3b82f6', fillOpacity:1
                    }).addTo(this.map);
                    const html = '<strong>'+this.esc(m.nama)+'</strong><br>'
                        + this.esc(m.kelas)+' · '+this.esc(m.jam)+'<br>'
                        + this.esc(m.status)
                        + (m.jarak!=null ? '<br>'+m.jarak+' m dari sekolah' : '')
                        + (m.accuracy!=null ? '<br>akurasi ±'+m.accuracy+' m' : '');
                    mk.bindPopup(html);
                    bounds.push([m.lat, m.lng]);
                });
                if(bounds.length > 1){
                    try { this.map.fitBounds(bounds, { padding:[36,36], maxZoom:18 }); } catch(e){}
                }
                try { new ResizeObserver(()=> this.map && this.map.invalidateSize()).observe(el); } catch(e){}
                [100,400,900].forEach(t=> setTimeout(()=> this.map && this.map.invalidateSize(), t));
            });
        },
        setBase(mode){
            if(!this.baseCtrl) return;
            this.baseCtrl.setMode(mode);
            this.baseMode = this.baseCtrl.mode;
        },
        esc(s){
            return String(s??'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
        }
    }
}
</script>
@endpush
@endsection
