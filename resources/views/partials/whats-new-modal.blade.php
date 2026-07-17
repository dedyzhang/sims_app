{{-- ─── Popup "Apa yang Baru" — tampil sekali per sesi login (dicek di halaman
     pertama yang benar-benar sampai ke layout ini, termasuk bila ada redirect
     wajib-ganti-password/wajib-daftar-wajah lebih dulu), atau tak pernah lagi
     bila user mencentang "Jangan tampilkan lagi". --}}
@php
    $pendingUpdate = null;
    if (auth()->check() && session('show_whats_new')) {
        $pendingUpdate = \App\Models\AppUpdate::pendingFor(auth()->user());
        session()->forget('show_whats_new');
    }
@endphp
@if($pendingUpdate)
<div x-data="{ open: true, dontShow: false }"
     x-show="open" x-cloak
     class="fixed inset-0 z-[9995] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/55" @click="open = false"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden">

        <div class="px-6 pt-6 pb-4 bg-gradient-to-br from-primary to-primary-700 text-white">
            <div class="flex items-center gap-2">
                <div class="grid h-9 w-9 place-items-center rounded-full bg-white/20 flex-shrink-0">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wide text-white/80 font-semibold">Apa yang Baru</p>
                    <p class="font-bold leading-tight">Versi {{ $pendingUpdate->version }}</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-5 max-h-[50vh] overflow-y-auto">
            <p class="font-semibold text-slate-800 dark:text-slate-100 mb-3">{{ $pendingUpdate->title }}</p>
            <div class="whats-new-content text-sm text-slate-600 dark:text-slate-300">
                {!! \App\Support\RichText::clean($pendingUpdate->content) !!}
            </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400 cursor-pointer">
                <input type="checkbox" x-model="dontShow" class="accent-[color:var(--cp)] w-4 h-4">
                Jangan tampilkan lagi
            </label>
            <button type="button" @click="whatsNewClose(dontShow, () => open = false)"
                    class="btn-primary px-5 py-2 rounded-xl text-sm font-bold">Tutup</button>
        </div>
    </div>
</div>

<script>
    function whatsNewClose(dontShow, hide) {
        if (dontShow) {
            fetch('{{ route('pembaruan.dismiss') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({ update_id: @js($pendingUpdate->uuid) }),
            }).finally(hide);
            return;
        }
        hide();
    }
</script>

<style>
    .whats-new-content p { margin: 0 0 0.6em; }
    .whats-new-content p:last-child { margin-bottom: 0; }
    .whats-new-content ul, .whats-new-content ol { margin: 0 0 0.6em; padding-left: 1.25em; }
    .whats-new-content ul { list-style: disc; }
    .whats-new-content ol { list-style: decimal; }
    .whats-new-content li { margin: 0.25em 0; }
    .whats-new-content strong { font-weight: 700; color: inherit; }
    .whats-new-content a { color: var(--cp); text-decoration: underline; }
</style>
@endif
