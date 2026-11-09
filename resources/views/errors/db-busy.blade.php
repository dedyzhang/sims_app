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
            Banyak yang sedang mengakses bersamaan. Ini gangguan sementara — silakan tunggu sejenak lalu klik tombol di bawah.
        </p>
        <button id="btn-reload" disabled class="mt-5 w-full py-3 rounded-xl text-sm font-bold text-white bg-slate-400 cursor-not-allowed transition flex items-center justify-center gap-2">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span id="btn-text">Tunggu 5 detik...</span>
        </button>
        <script>
            (function() {
                var sisa = 5;
                var btn = document.getElementById('btn-reload');
                var text = document.getElementById('btn-text');
                var timer = setInterval(function() {
                    sisa--;
                    if (sisa > 0) {
                        text.textContent = 'Tunggu ' + sisa + ' detik...';
                    } else {
                        clearInterval(timer);
                        btn.disabled = false;
                        btn.classList.remove('bg-slate-400', 'cursor-not-allowed');
                        btn.classList.add('bg-slate-800', 'hover:bg-slate-900', 'cursor-pointer');
                        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg> Muat Ulang Halaman';
                        btn.onclick = function() {
                            btn.disabled = true;
                            btn.innerHTML = 'Memuat ulang...';
                            window.location.reload();
                        };
                    }
                }, 1000);
            })();
        </script>
    </div>
</body>
</html>
