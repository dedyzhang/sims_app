<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Sedang Sibuk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
{{--
    Sengaja TANPA query database apa pun di halaman ini (tak ada nama sekolah/logo dari
    Setting) — ini dirender justru SAAT koneksi database sedang bermasalah, jadi tak boleh
    bergantung pada DB sama sekali supaya tak ikut gagal.
--}}
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="max-w-sm w-full bg-white rounded-3xl shadow-xl p-8 text-center">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 flex items-center justify-center mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
        </div>
        <h1 class="text-lg font-bold text-slate-800">Server Sedang Sibuk</h1>
        <p class="text-sm text-slate-500 mt-2 leading-relaxed">
            Banyak yang sedang mengakses bersamaan. Ini gangguan sementara — halaman akan dicoba lagi otomatis.
        </p>
        <p class="text-sm text-slate-400 mt-4" x-data="{ detik: 5 }" x-init="setInterval(() => { if (detik > 0) detik--; }, 1000)">
            Mencoba lagi dalam <span class="font-bold text-slate-600" x-text="detik"></span> detik…
        </p>
        <button onclick="window.location.reload()" class="mt-5 w-full py-3 rounded-xl text-sm font-bold text-white bg-slate-800 hover:bg-slate-900 transition">
            Coba Sekarang
        </button>
    </div>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        setTimeout(() => window.location.reload(), 5000);
    </script>
</body>
</html>
