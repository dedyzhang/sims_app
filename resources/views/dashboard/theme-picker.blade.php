<div class="flex justify-end mb-4" x-data="{ previewTheme: document.body.dataset.uiTheme }" @sims-theme-changed.window="previewTheme = $event.detail">
    <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/70 py-1 pl-3 pr-1 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-400"
        title="Pratinjau tema. Simpan lewat Personalisasi.">
        <i data-lucide="palette" class="w-3.5 h-3.5 shrink-0" aria-hidden="true"></i>
        <span class="sr-only">Gaya Tampilan</span>
        <select class="rounded-full border-0 bg-transparent py-1 pl-1 pr-7 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-slate-300 dark:text-slate-200 dark:[color-scheme:dark] dark:focus:ring-slate-600"
            x-model="previewTheme" @change="window.setDashboardTheme(previewTheme)">
            @foreach(['windows11'=>'Windows 11', 'macos'=>'Mac OS', 'fluent'=>'Fluent', 'macos-minimal'=>'macOS Minimal', 'bento'=>'Bento'] as $id=>$label)
                <option value="{{ $id }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>
