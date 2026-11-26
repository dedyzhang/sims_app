<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langganan Berakhir — {{ $namaSekolah ?? 'SIMS' }}</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script src="https://unpkg.com/lucide@1.81.0/dist/umd/lucide.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 grid place-items-center px-4">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl p-8 text-center space-y-4">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-rose-500/10 grid place-items-center">
            <i data-lucide="calendar-x" class="w-8 h-8 text-rose-500"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-800">Langganan SIMS Berakhir</h1>
        <p class="text-sm text-slate-500 leading-relaxed">
            Masa aktif langganan sistem ini telah berakhir, sehingga akses sementara dikunci.
            Silakan hubungi pengelola / administrator SIMS untuk memperpanjang langganan.
        </p>
        <div class="pt-2 flex items-center justify-center gap-3">
            @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    <i data-lucide="log-out" class="w-4 h-4"></i>Keluar
                </button>
            </form>
            @else
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                <i data-lucide="log-in" class="w-4 h-4"></i>Ke Halaman Masuk
            </a>
            @endauth
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
