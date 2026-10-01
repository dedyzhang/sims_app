<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Kegiatan - {{ $namaSekolah ?? 'SIMS' }}</title>
    @if($sekolahLogoUrl)<link rel="icon" href="{{ $sekolahLogoUrl }}" type="image/png">@endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-100">
        <!-- Header Panel -->
        <div class="bg-gradient-to-br from-blue-700 to-indigo-900 p-8 text-center relative overflow-hidden">
            <!-- Decorative shapes -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-blue-500/20 rounded-full blur-2xl"></div>
            
            @if($sekolahLogoUrl)
            <img src="{{ $sekolahLogoUrl }}" alt="Logo" class="w-16 h-16 mx-auto object-contain mb-4 relative z-10 drop-shadow-md">
            @else
            <div class="w-16 h-16 mx-auto mb-4 relative z-10 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center border border-white/30 shadow-md">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3L1 9l11 6 9-4.91V17M1 9v7"></path></svg>
            </div>
            @endif
            <h2 class="text-2xl font-bold text-white relative z-10">{{ $kegiatan->nama_kegiatan }}</h2>
            <p class="text-blue-100 mt-2 text-sm relative z-10">{{ $namaSekolah ?? 'SIMS' }}</p>
        </div>

        <!-- Form Panel -->
        <div class="p-8">
            <h3 class="text-lg font-semibold text-slate-800 mb-6 text-center">Formulir Pendaftaran</h3>
            
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 text-green-700 border border-green-200 rounded-xl text-sm font-medium text-center shadow-sm">
                    {!! nl2br(e(session('success'))) !!}
                </div>
            @endif
            
            <form class="space-y-5" method="POST" action="{{ route('kegiatan.daftar.store', $kegiatan) }}">
                @csrf
                @foreach($kegiatan->form_fields ?? [] as $field)
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">{{ $field['label'] ?? $field['name'] }} @if(!empty($field['required'])) <span class="text-red-500">*</span> @endif</label>
                    <input type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}" {{ !empty($field['required']) ? 'required' : '' }} 
                           class="block w-full px-4 py-3 rounded-xl border-slate-200 bg-slate-50 border focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Masukkan {{ strtolower($field['label'] ?? $field['name']) }}">
                    @error($field['name'])
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </div>
                @endforeach
                
                <button type="submit" class="w-full py-3.5 px-4 rounded-xl shadow-md text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-500/30 transition-all mt-4">
                    Kirim Pendaftaran
                </button>
            </form>
        </div>
    </div>

</body>
</html>
