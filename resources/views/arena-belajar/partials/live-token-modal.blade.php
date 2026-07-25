{{-- Modal token Live Arena (siswa) --}}
<div x-show="showLiveToken" x-cloak
     class="fixed inset-0 z-50 grid place-items-center bg-slate-900/55 p-4"
     @keydown.escape.window="showLiveToken = false">
    <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-slate-900 shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-5 space-y-4"
         @click.outside="showLiveToken = false">
        <div>
            <h3 class="m-0 text-lg font-black text-slate-800 dark:text-slate-100">Token Live Arena</h3>
            <p class="m-0 mt-1 text-xs font-semibold text-slate-500">Ketik, pindai QR, atau scan barcode dari guru mapel.</p>
        </div>
        <form method="POST" :action="joinTokenUrl" class="space-y-3">
            @csrf
            <input type="hidden" name="redirect" :value="liveRedirect">
            <input type="text" name="join_token" x-model="liveToken"
                   maxlength="8" autocomplete="off"
                   class="form-input font-mono text-center text-xl tracking-[0.35em] uppercase"
                   placeholder="ABCD" required autofocus>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary flex-1 rounded-xl py-2.5 text-sm font-bold"
                        @click="openScan()">
                    <i data-lucide="qr-code" class="w-4 h-4 inline"></i> Pindai
                </button>
                <button type="button" class="btn-secondary rounded-xl py-2.5 px-3 text-sm font-bold"
                        @click="showLiveToken = false">Batal</button>
                <button type="submit" class="btn-primary flex-1 rounded-xl py-2.5 text-sm font-bold">
                    Masuk Live
                </button>
            </div>
        </form>
        @include('arena-belajar.partials.join-barcode-wedge')
    </div>
</div>
