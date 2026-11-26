@extends('layouts.app')
@section('title', $ujian->judul)

@section('content')
<div class="max-w-lg mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('ujian.siswa.index') }}" class="hover:underline">Ujian Saya</a> / {{ $ujian->judul }}</nav>
        <h1 class="page-title">{{ $ujian->judul }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $ujian->pelajaran?->nama }} · {{ $ujian->jenisLabel() }} · {{ $ujian->durasi_menit }} menit</p>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
    </div>
    @endif

    @if($ujianKelas->belumDimulai())
    {{-- Hitung mundur di browser — siswa TAK perlu refresh berulang (dulu tiap refresh = 1
         request penuh ke server, ×ratusan siswa yg menunggu). Saat jam mulai tiba, halaman
         memuat ulang SENDIRI tepat sekali, dgn jeda acak 0–15 dtk per siswa supaya ratusan
         siswa tak serempak menembak server di detik yg sama. Waktu dihitung dari jam SERVER
         (bukan jam HP siswa yg bisa saja meleset). --}}
    <div id="gate-menunggu" class="card p-6 text-center space-y-2"
         data-buka-ms="{{ $ujianKelas->dibuka_mulai->getTimestampMs() }}"
         data-server-now-ms="{{ now()->getTimestampMs() }}">
        <i data-lucide="clock" class="w-8 h-8 mx-auto text-amber-500"></i>
        <p class="font-bold text-slate-800 dark:text-slate-100">Ujian Belum Dimulai</p>
        <p class="text-sm text-slate-500 dark:text-slate-400">Ujian ini baru bisa dikerjakan mulai <b>{{ $ujianKelas->dibuka_mulai->translatedFormat('d M Y, H:i') }}</b>.</p>
        <p id="gate-sisa" class="text-2xl font-extrabold font-mono tracking-wider text-slate-700 dark:text-slate-200" aria-live="polite"></p>
        <p id="gate-sisa-ket" class="text-xs text-slate-400">Tetap di halaman ini — kolom token akan muncul otomatis saat waktunya tiba. Tidak perlu memuat ulang halaman.</p>
    </div>
    @endif

    @if($ujian->instruksi)
    <div class="card p-5">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-2 text-sm">Instruksi</h2>
        <div class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $ujian->instruksi }}</div>
    </div>
    @endif

    <div class="card p-5 space-y-3">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 text-sm flex items-center gap-2">
            <i data-lucide="shield-alert" class="w-4 h-4 text-amber-500"></i> Perhatian Sebelum Mulai
        </h2>
        <ul class="text-xs text-slate-500 dark:text-slate-400 space-y-1.5 list-disc list-inside">
            <li>Ujian akan berjalan dalam mode layar penuh. Keluar dari layar penuh atau berpindah tab akan <b>mengunci</b> ujian Anda dan perlu dibuka kembali oleh guru/panitia.</li>
            <li>Waktu berjalan otomatis sejak Anda menekan "Mulai Ujian" dan tidak bisa dijeda.</li>
            <li>Jawaban tersimpan otomatis setiap kali Anda menjawab.</li>
            <li>Salin-tempel dinonaktifkan selama ujian berlangsung.</li>
        </ul>
    </div>

    @unless($ujianKelas->belumDimulai())
    <form method="POST" action="{{ route('ujian.siswa.start', $ujian) }}" id="form-mulai-ujian" class="card p-5 space-y-3">
        @csrf
        <div>
            <label class="form-label">Token Masuk</label>
            <input type="text" name="token" required autocomplete="off" inputmode="numeric" pattern="[0-9]*"
                   class="form-input tracking-widest text-center font-mono text-lg" placeholder="0000" maxlength="4">
            <p class="text-xs text-slate-400 mt-1">Minta token ini ke guru/panitia ujian.</p>
        </div>
        <button type="submit" id="btn-mulai-ujian" class="btn-primary w-full py-3 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
            <i data-lucide="log-in" class="w-4 h-4"></i> Mulai Ujian
        </button>
    </form>
    @endunless
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tunggu = (ms) => new Promise(r => setTimeout(r, ms));
    const acak = (min, max) => min + Math.floor(Math.random() * (max - min + 1));

    // ── A. Hitung mundur sampai jam mulai, lalu muat ulang SEKALI (jeda acak 0–15 dtk) ──
    const boxTunggu = document.getElementById('gate-menunggu');
    if (boxTunggu) {
        const bukaMs = Number(boxTunggu.dataset.bukaMs);
        // Koreksi jam HP siswa thd jam server (HP sering meleset beberapa menit).
        const selisih = Number(boxTunggu.dataset.serverNowMs) - Date.now();
        const jedaAcak = acak(0, 15000);
        const elSisa = document.getElementById('gate-sisa');
        const elKet = document.getElementById('gate-sisa-ket');
        const dua = (n) => String(n).padStart(2, '0');
        let sudahReload = false;

        const tick = () => {
            const sisa = bukaMs - (Date.now() + selisih);
            if (sisa > 0) {
                const dtk = Math.ceil(sisa / 1000);
                const hari = Math.floor(dtk / 86400);
                const jam = Math.floor((dtk % 86400) / 3600);
                const mnt = Math.floor((dtk % 3600) / 60);
                elSisa.textContent = (hari > 0 ? hari + ' hari ' : '') + (hari > 0 || jam > 0 ? dua(jam) + ':' : '') + dua(mnt) + ':' + dua(dtk % 60);
                return;
            }
            const sisaJeda = sisa + jedaAcak;
            if (sisaJeda > 0) {
                elSisa.textContent = 'Membuka…';
                elKet.textContent = 'Menyiapkan kolom token dalam ' + Math.ceil(sisaJeda / 1000) + ' detik. Jangan memuat ulang halaman.';
                return;
            }
            if (!sudahReload) {
                sudahReload = true;
                elKet.textContent = 'Memuat kolom token…';
                window.location.reload();
            }
        };
        tick();
        setInterval(tick, 1000);
    }

    // ── Form token ──
    const form = document.getElementById('form-mulai-ujian');
    const btn = document.getElementById('btn-mulai-ujian');
    const inputToken = form?.querySelector('input[name="token"]');
    if (!form || !btn) return;

    let isSubmitting = false;

    // Bersihkan spasi secara otomatis jika ada spasi bawaan keyboard HP / copy-paste
    inputToken?.addEventListener('input', function () {
        this.value = this.value.replace(/\s+/g, '');
    });

    const spinner = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
    const setTombol = (teks) => { btn.innerHTML = spinner + ' ' + teks; };

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (isSubmitting) return;

        const tokenVal = inputToken ? inputToken.value.trim() : '';
        if (!tokenVal) {
            inputToken?.focus();
            return;
        }

        isSubmitting = true;

        // Kunci tombol seketika dan tampilkan indikator
        btn.disabled = true;
        btn.classList.add('opacity-80', 'cursor-not-allowed');
        const originalBtnHtml = btn.innerHTML;

        // Hapus alert error sebelumnya jika ada
        const existingAlert = document.getElementById('token-error-alert');
        if (existingAlert) existingAlert.remove();

        // Jeda antre acak 0,3–4 dtk SEBELUM token dikirim: begitu token diumumkan, satu ruangan
        // menekan "Mulai" hampir bersamaan. Waktu ujian BELUM berjalan selama jeda ini (baru
        // dihitung saat server menerima token), jadi aman & adil bagi siswa.
        setTombol('Masuk antrean…');
        await tunggu(acak(300, 4000));

        const csrf = form.querySelector('input[name="_token"]')?.value || '';
        let lastErrorMessage = '';
        // Jeda coba-ulang bertingkat (2 dtk, 5 dtk, 10 dtk) + acak — dulu 0,6–2,4 dtk,
        // terlalu rapat saat server memang sedang penuh. Retry-After dari server diutamakan.
        const jedaUlang = [2000, 5000, 10000];

        for (let attempt = 0; attempt < 4; attempt++) {
            setTombol(attempt === 0 ? 'Menghubungkan ke server…' : 'Mencoba lagi…');
            let res;
            try {
                res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ token: tokenVal }),
                });
            } catch (err) {
                res = null; // gangguan jaringan — dicoba lagi di bawah
            }

            if (res && res.ok) {
                const ct = res.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    // Bukan JSON (mis. sesi habis lalu diarahkan ke halaman login) — muat ulang
                    // halaman supaya siswa melihat keadaan sebenarnya.
                    window.location.reload();
                    return;
                }
                const data = await res.json().catch(() => null);
                if (data && data.redirect) {
                    // Jeda acak halus (200ms - 1200ms) sebelum pindah ke soal agar ratusan siswa
                    // tidak serentak menembak route /kerjakan di detik yang persis sama
                    await tunggu(acak(200, 1200));
                    window.location.href = data.redirect;
                    return;
                }
                window.location.reload();
                return;
            }

            if (res && res.status === 422) {
                // Token salah (kesalahan ketik siswa, tidak perlu retry ke server)
                const errData = await res.json().catch(() => ({}));
                lastErrorMessage = errData.message || 'Token salah. Minta token yang benar ke guru/panitia ujian.';
                break;
            }

            if (res && res.status === 403) {
                const errData = await res.json().catch(() => ({}));
                lastErrorMessage = errData.message || 'Ujian belum dibuka untuk kelas Anda atau waktu belum tiba.';
                break;
            }

            if (res && res.status === 419) {
                lastErrorMessage = 'Sesi Anda sudah berakhir. Muat ulang halaman ini (atau login lagi), lalu masukkan token kembali.';
                break;
            }

            if (res && res.status === 401) {
                window.location.reload();
                return;
            }

            // Sisa: 429 (terlalu sering), 5xx (server sibuk), atau gangguan jaringan → tunggu
            // lalu coba lagi diam-diam. (Dulu 429 dicoba ulang TANPA jeda sama sekali.)
            if (attempt < 3) {
                const retryAfter = res ? parseInt(res.headers.get('Retry-After') || '', 10) : NaN;
                let wait = Number.isFinite(retryAfter) && retryAfter > 0
                    ? retryAfter * 1000 + acak(0, 2000)
                    : jedaUlang[attempt] + acak(0, 2000);
                const sampai = Date.now() + wait;
                while (Date.now() < sampai) {
                    setTombol('Server sedang padat, mencoba lagi dalam ' + Math.ceil((sampai - Date.now()) / 1000) + ' detik…');
                    await tunggu(Math.min(1000, Math.max(0, sampai - Date.now())));
                }
                continue;
            }

            lastErrorMessage = res
                ? 'Koneksi ke server sedang padat. Tunggu sebentar lalu tekan Mulai Ujian sekali lagi.'
                : 'Gagal menghubungi server. Periksa koneksi internet Anda lalu coba lagi.';
        }

        // Jika sampai di sini berarti gagal
        isSubmitting = false;
        btn.disabled = false;
        btn.classList.remove('opacity-80', 'cursor-not-allowed');
        btn.innerHTML = originalBtnHtml;
        if (window.lucide) lucide.createIcons();

        const alertDiv = document.createElement('div');
        alertDiv.id = 'token-error-alert';
        alertDiv.className = 'card p-3 border-l-4 !border-l-rose-500 text-xs text-rose-700 dark:text-rose-300 font-semibold mb-3';
        alertDiv.textContent = lastErrorMessage || 'Terjadi kendala saat memvalidasi token. Silakan coba lagi.';
        form.parentNode.insertBefore(alertDiv, form);
    });
});
</script>
@endpush
