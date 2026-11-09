<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Test Ditutup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/lucide@0.468.0"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-6 antialiased">
    <div class="max-w-md w-full bg-white rounded-3xl p-8 shadow-xl shadow-slate-200/50 border border-slate-100 text-center space-y-4">
        <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
            <i data-lucide="lock" class="w-8 h-8"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-900">Akses Pre-Test Ditutup</h1>
        <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
            Sesi uji coba untuk soal <strong>{{ $pretest->judul ?? $pretest->ujian->judul }}</strong> saat ini sedang dinonaktifkan oleh guru pengampu.
        </p>
        <p class="text-xs text-slate-400 pt-2">
            Silakan hubungi guru yang bersangkutan untuk membuka kembali akses pre-test.
        </p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => { if (window.lucide) window.lucide.createIcons(); });
    </script>
</body>
</html>
