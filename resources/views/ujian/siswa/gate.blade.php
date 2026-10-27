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
    <div class="card p-6 text-center space-y-2">
        <i data-lucide="clock" class="w-8 h-8 mx-auto text-amber-500"></i>
        <p class="font-bold text-slate-800 dark:text-slate-100">Ujian Belum Dimulai</p>
        <p class="text-sm text-slate-500 dark:text-slate-400">Ujian ini baru bisa dikerjakan mulai <b>{{ $ujianKelas->dibuka_mulai->translatedFormat('d M Y, H:i') }}</b>. Kembali lagi ke halaman ini saat waktunya tiba.</p>
    </div>
    @else

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
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-mulai-ujian');
    const btn = document.getElementById('btn-mulai-ujian');
    const inputToken = form?.querySelector('input[name="token"]');
    if (!form || !btn) return;

    let isSubmitting = false;

    // Bersihkan spasi secara otomatis jika ada spasi bawaan keyboard HP / copy-paste
    inputToken?.addEventListener('input', function () {
        this.value = this.value.replace(/\s+/g, '');
    });

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
        btn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Menghubungkan ke server...';

        // Hapus alert error sebelumnya jika ada
        const existingAlert = document.getElementById('token-error-alert');
        if (existingAlert) existingAlert.remove();

        // Rencana 5: Micro-Jitter (50ms - 1200ms) untuk meratakan lonjakan serempak
        const jitterMs = Math.floor(Math.random() * 1150) + 50;
        await new Promise(r => setTimeout(r, jitterMs));

        // Submit dengan Fetch dan 3x Silent Retry diam-diam jika server sedang sibuk sesaat
        const csrf = form.querySelector('input[name="_token"]')?.value || '';
        let lastErrorMessage = '';

        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ token: tokenVal }),
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    window.location.reload();
                    return;
                }

                if (res.status === 422) {
                    // Token salah (kesalahan ketik siswa, tidak perlu retry ke server)
                    const errData = await res.json().catch(() => ({}));
                    lastErrorMessage = errData.message || 'Token salah. Minta token yang benar ke guru/panitia ujian.';
                    break;
                }

                if (res.status === 403) {
                    const errData = await res.json().catch(() => ({}));
                    lastErrorMessage = errData.message || 'Ujian belum dibuka untuk kelas Anda atau waktu belum tiba.';
                    break;
                }

                // Jika status 503 (database busy) atau 500, tunggu sejenak lalu coba lagi diam-diam
                if ((res.status === 503 || res.status >= 500) && attempt < 2) {
                    await new Promise(r => setTimeout(r, (attempt + 1) * 400));
                    continue;
                }

                lastErrorMessage = 'Koneksi ke server sedang padat. Silakan tekan Mulai Ujian sekali lagi.';
            } catch (err) {
                if (attempt < 2) {
                    await new Promise(r => setTimeout(r, (attempt + 1) * 400));
                    continue;
                }
                lastErrorMessage = 'Gagal menghubungi server. Periksa koneksi internet Anda lalu coba lagi.';
            }
        }

        // Jika sampai di sini berarti gagal setelah dicoba 3x
        isSubmitting = false;
        btn.disabled = false;
        btn.classList.remove('opacity-80', 'cursor-not-allowed');
        btn.innerHTML = originalBtnHtml;

        const alertDiv = document.createElement('div');
        alertDiv.id = 'token-error-alert';
        alertDiv.className = 'card p-3 border-l-4 !border-l-rose-500 text-xs text-rose-700 dark:text-rose-300 font-semibold mb-3';
        alertDiv.textContent = lastErrorMessage || 'Terjadi kendala saat memvalidasi token. Silakan coba lagi.';
        form.parentNode.insertBefore(alertDiv, form);
    });
});
</script>
@endpush
