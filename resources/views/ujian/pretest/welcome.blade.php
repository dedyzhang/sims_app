<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Test — {{ $pretest->judul ?? $pretest->ujian->judul }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('build/assets/app-GtwHpXZD.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/lucide@0.468.0"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col justify-between p-4 sm:p-6 antialiased">
    <div class="max-w-md w-full mx-auto my-auto py-8">
        {{-- Kartu Sambutan & Identitas --}}
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 border border-slate-100 space-y-6">
            <div class="text-center space-y-2">
                <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
                    <i data-lucide="file-check-2" class="w-7 h-7"></i>
                </div>
                <span class="inline-block px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-full uppercase tracking-wider">
                    Uji Coba Pre-Test
                </span>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                    {{ $pretest->judul ?? $pretest->ujian->judul }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    {{ $pretest->ujian->pelajaran?->nama ?? 'Ujian' }} · {{ $pretest->ujian->soal->count() }} Soal
                </p>
            </div>

            {{-- Ringkasan Info --}}
            <div class="grid grid-cols-2 gap-3 p-3.5 bg-slate-50 rounded-2xl border border-slate-100 text-center text-xs">
                <div>
                    <span class="text-slate-400 block text-[11px]">Durasi Uji Coba</span>
                    <strong class="text-slate-700 font-bold text-sm">
                        {{ $pretest->durasi_menit > 0 ? $pretest->durasi_menit . ' Menit' : 'Bebas Waktu' }}
                    </strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[11px]">Format Soal</span>
                    <strong class="text-slate-700 font-bold text-sm">
                        {{ $pretest->acak_soal ? 'Acak Nomor' : 'Sesuai Urutan' }}
                    </strong>
                </div>
            </div>

            @if($errors->any())
            <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 font-medium">
                @foreach($errors->all() as $err)
                    <p>{{ $err }}</p>
                @endforeach
            </div>
            @endif

            {{-- Form Isi Identitas Peserta --}}
            <form method="POST" action="{{ route('pretest.public.mulai', ['token' => $pretest->token]) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama" required value="{{ old('nama') }}" placeholder="Contoh: Ahmad Fadilah"
                           class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Kelas / Asal Sekolah / Instansi <span class="text-rose-500">*</span></label>
                    <input type="text" name="identitas" required value="{{ old('identitas') }}" placeholder="Contoh: Kelas 9A / SMPN 1 / Tamu"
                           class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">No. WhatsApp / Kontak <span class="text-slate-400 font-normal">(Opsional)</span></label>
                    <input type="tel" name="nomor_kontak" value="{{ old('nomor_kontak') }}" placeholder="Contoh: 081234567890"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition shadow-sm">
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-700 active:scale-[0.99] text-white font-bold rounded-2xl shadow-lg shadow-blue-500/25 transition flex items-center justify-center gap-2 text-sm">
                        <i data-lucide="play" class="w-4 h-4 fill-white"></i> Mulai Mengerjakan Pre-Test
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            Pre-Test Mandiri &copy; {{ date('Y') }} SIMS Edutive
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) window.lucide.createIcons();
        });
    </script>
</body>
</html>
