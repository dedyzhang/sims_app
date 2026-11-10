<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Katalog & QR Code Demo Fitur — B'tive SIMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-inside: avoid;
            }
            body {
                background: white !important;
                color: black !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

    <!-- Top Notice Bar (No-Print) -->
    <header class="no-print bg-slate-900 text-white border-b border-slate-800 sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 mr-1.5 animate-pulse"></span>
                    MODE DEMO AKTIF
                </span>
                <span class="text-sm text-slate-300 font-medium">Katalog Akses Promosi &amp; QR Code Interaktif</span>
            </div>

            <div class="flex items-center space-x-2 text-xs">
                <button onclick="window.print()" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-medium transition shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Cetak Lembar Brosur
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <!-- Hero Section -->
        <div class="mb-10 text-center sm:text-left sm:flex sm:items-end sm:justify-between border-b border-slate-200 pb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Link &amp; QR Code Uji Coba Fitur
                </h1>
                <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                    Setiap kartu di bawah memiliki link dan QR Code unik. Calon klien cukup scan atau klik untuk langsung merasakan fitur tanpa perlu memasukkan username &amp; password dummy.
                </p>
            </div>
            <div class="no-print mt-4 sm:mt-0 flex items-center justify-center sm:justify-end gap-2 text-xs text-slate-500">
                <span class="inline-block px-2.5 py-1 bg-white border border-slate-200 rounded-md font-mono text-[11px] text-slate-600 shadow-sm">
                    Host: {{ request()->getHost() }}
                </span>
            </div>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($catalog as $item)
                <div class="page-break bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between overflow-hidden">
                    <!-- Card Header -->
                    <div class="p-6 pb-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                {{ $item['badge'] }}
                            </span>
                            <span class="text-[11px] font-mono text-slate-400 bg-slate-50 px-2 py-0.5 rounded border border-slate-100">
                                {{ $item['slug'] }}
                            </span>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900 leading-snug">
                            {{ $item['title'] }}
                        </h2>
                        <p class="mt-1.5 text-xs text-slate-600 leading-relaxed min-h-[36px]">
                            {{ $item['description'] }}
                        </p>
                    </div>

                    <!-- QR Code Container -->
                    <div class="px-6 py-4 bg-slate-50 border-y border-slate-100 flex flex-col items-center justify-center">
                        <div class="p-3 bg-white rounded-xl shadow-sm border border-slate-200/80 hover:scale-[1.02] transition-transform">
                            {!! $item['qr_svg'] !!}
                        </div>
                        <span class="mt-2.5 text-[11px] text-slate-500 font-medium text-center">
                            Arahkan kamera smartphone untuk masuk
                        </span>
                    </div>

                    <!-- Card Actions (No-Print) -->
                    <div class="no-print p-5 bg-white space-y-3">
                        <!-- Direct Link Copy Input -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                Link Promosi Website:
                            </label>
                            <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50/50 p-1 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:border-indigo-500">
                                <input type="text" readonly value="{{ $item['direct_url'] }}" 
                                    id="link-{{ $item['slug'] }}"
                                    class="w-full bg-transparent px-2 text-xs font-mono text-slate-700 select-all focus:outline-none">
                                <button type="button" 
                                    onclick="copyToClipboard('{{ $item['direct_url'] }}', this)" 
                                    class="shrink-0 px-2.5 py-1 rounded-md bg-white hover:bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700 shadow-sm transition active:scale-95">
                                    Salin
                                </button>
                            </div>
                        </div>

                        <!-- Test Button -->
                        <div class="flex items-center gap-2 pt-1">
                            <a href="{{ $item['direct_url'] }}" target="_blank" 
                                class="w-full text-center py-2 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition shadow-sm hover:shadow">
                                Uji Coba Masuk Sekarang &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Instruksi Embed Marketing -->
        <div class="no-print mt-12 bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm">
            <h3 class="text-base font-bold text-slate-900 flex items-center">
                <svg class="w-5 h-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Panduan Memasang di Homepage Promosi &amp; Brosur
            </h3>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6 text-xs text-slate-600">
                <div class="space-y-1.5 p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="font-bold text-slate-800 text-sm">1. Link Tombol Homepage</span>
                    <p class="leading-relaxed">
                        Salin link di atas ke tombol CTA (Call To Action) di homepage marketing Anda, misalnya: <em>"Coba Arena Belajar Sekarang"</em>.
                    </p>
                </div>
                <div class="space-y-1.5 p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="font-bold text-slate-800 text-sm">2. Cetak ke Brosur / Proposal</span>
                    <p class="leading-relaxed">
                        Gunakan tombol <strong>Cetak Lembar Brosur</strong> di pojok kanan atas untuk mencetak atau simpan ke PDF. QR Code siap di-scan langsung oleh calon klien saat presentasi fisik.
                    </p>
                </div>
                <div class="space-y-1.5 p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="font-bold text-slate-800 text-sm">3. Nonaktifkan Seketika</span>
                    <p class="leading-relaxed">
                        Jika masa promosi selesai atau ingin dimatikan, ubah <code class="bg-white px-1.5 py-0.5 rounded border font-mono">DEMO_ENABLED=false</code> di file <code class="bg-white px-1.5 py-0.5 rounded border font-mono">.env</code>. Semua akses akan terblokir total (404).
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Notification -->
    <div id="toast" class="no-print fixed bottom-6 right-6 z-50 transform translate-y-16 opacity-0 transition-all duration-300 pointer-events-none">
        <div class="bg-slate-900 text-white text-xs font-semibold px-4 py-2.5 rounded-lg shadow-xl flex items-center space-x-2 border border-slate-800">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span id="toast-text">Link promosi berhasil disalin!</span>
        </div>
    </div>

    <script>
        function copyToClipboard(text, btnElement) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = btnElement.innerText;
                btnElement.innerText = 'Disalin!';
                btnElement.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-300');
                
                showToast('Link berhasil disalin ke clipboard!');

                setTimeout(() => {
                    btnElement.innerText = originalText;
                    btnElement.classList.remove('bg-emerald-50', 'text-emerald-700', 'border-emerald-300');
                }, 2000);
            }).catch(err => {
                alert('Gagal menyalin link: ' + err);
            });
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            const text = document.getElementById('toast-text');
            text.innerText = message;
            toast.classList.remove('translate-y-16', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');

            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-16', 'opacity-0');
            }, 2500);
        }
    </script>
</body>
</html>
