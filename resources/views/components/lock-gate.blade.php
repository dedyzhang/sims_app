@props(['title', 'unlockUrl'])
{{-- Gerbang token untuk konten terkunci (siswa). --}}
<div class="max-w-md mx-auto card p-8 text-center mt-6">
    <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center mb-4"><i data-lucide="lock" class="w-8 h-8 text-amber-500"></i></div>
    <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $title }}</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 mb-5">Terkunci. Masukkan <b>token</b> dari guru. Setelah terbuka, layar masuk <b>mode penuh</b> dan Anda <b>tidak boleh berpindah tab</b> — jika keluar, akses tertutup &amp; guru menerima notifikasi.</p>
    @if(session('error'))<div class="rounded-lg bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-600 dark:text-rose-300 px-3 py-2 text-sm mb-3">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ $unlockUrl }}" class="space-y-3">
        @csrf
        <input type="text" name="token" required autofocus autocomplete="off" maxlength="16" class="form-input text-center text-2xl tracking-[0.4em] uppercase font-mono" placeholder="••••">
        <button class="w-full px-5 py-3 rounded-xl text-sm font-bold text-white" style="background:var(--cp)"><i data-lucide="unlock" class="w-4 h-4 inline"></i> Buka</button>
    </form>
</div>
