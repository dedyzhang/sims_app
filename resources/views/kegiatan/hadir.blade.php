<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Kehadiran - {{ $namaSekolah ?? 'SIMS' }}</title>
    @if(isset($sekolahLogoUrl) && $sekolahLogoUrl)<link rel="icon" href="{{ $sekolahLogoUrl }}" type="image/png">@endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-sm w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-100 text-center relative">
        <div class="h-24 bg-gradient-to-r from-emerald-500 to-teal-600"></div>
        
        <div class="relative -mt-12 mb-4">
            <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mx-auto shadow-md border-4 border-white p-2">
                @if($sekolahLogoUrl)
                    <img src="{{ $sekolahLogoUrl }}" alt="Logo" class="w-full h-full object-contain">
                @else
                    <svg class="w-12 h-12 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                @endif
            </div>
        </div>
        
        <div class="px-8 pb-8 space-y-4">
            <div>
                <p class="text-sm text-slate-500 font-medium tracking-wide uppercase mb-1">Selamat Datang</p>
                <h2 class="text-2xl font-bold text-slate-900 leading-tight">{{ $peserta->biodata['nama'] ?? 'Peserta' }}</h2>
                <p class="text-sm text-slate-500 mt-1">Instansi: {{ $peserta->biodata['instansi'] ?? '-' }}</p>
            </div>
            
            <div class="bg-slate-50 rounded-xl p-4 mt-6 border border-slate-100 shadow-inner">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Acara</p>
                <p class="text-base font-bold text-slate-800">{{ $peserta->kegiatan->nama_kegiatan }}</p>
                <p class="text-sm text-slate-500 mt-1 flex items-center justify-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    {{ $peserta->kegiatan->tanggal_mulai->translatedFormat('d M Y') }}, {{ $peserta->kegiatan->waktu_mulai ?? '07:30' }}
                </p>
            </div>
            
            @if(session('success'))
                <div class="mt-6 p-4 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl font-medium text-sm flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ session('success') }}
                </div>
            @elseif(session('info'))
                <div class="mt-6 p-4 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl font-medium text-sm flex items-center justify-center gap-2 text-center">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('info') }}
                </div>
            @elseif(session('error'))
                <div class="mt-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl font-medium text-sm flex flex-col items-center justify-center gap-2 text-center">
                    <svg class="w-8 h-8 flex-shrink-0 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    {{ session('error') }}
                </div>
            @else
                <form method="POST" action="{{ route('kegiatan.hadir.mark', $peserta->qr_token) }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full flex justify-center items-center gap-2 py-3.5 px-4 rounded-xl shadow-md text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-500/30 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        KLIK UNTUK HADIR
                    </button>
                </form>
            @endif
        </div>
    </div>

</body>
</html>
