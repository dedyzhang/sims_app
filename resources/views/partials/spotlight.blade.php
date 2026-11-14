<div class="min-w-0 flex-1 max-w-md" x-data="{ query: '', selected: 0,
    get results() { const q = this.query.trim().toLocaleLowerCase('id'); return this.navSearchCatalog.filter(item => !q || (item.label + ' ' + (item.group || '')).toLocaleLowerCase('id').includes(q)).slice(0, 12); },
    openSearch() { this.query = ''; this.selected = 0; if (!this.$refs.palette.open) this.$refs.palette.showModal(); this.$nextTick(() => this.$refs.search.focus()); },
    move(step) { this.selected = Math.max(0, Math.min(this.results.length - 1, this.selected + step)); this.$nextTick(() => this.$refs.palette.querySelector('[data-selected=true]')?.scrollIntoView({block: 'nearest'})); },
    go() { const item = this.results[this.selected]; if (item) window.location.assign(item.url); }
}" @keydown.window="if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k') { $event.preventDefault(); openSearch(); }">
    <button type="button" @click="openSearch()" class="w-full flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-500 dark:text-slate-300 text-left" aria-haspopup="dialog">
        <span aria-hidden="true">⌕</span><span class="truncate">Cari menu…</span><kbd class="hidden md:inline ml-auto text-xs whitespace-nowrap">Ctrl / ⌘ K</kbd>
    </button>
    <dialog x-ref="palette" aria-labelledby="spotlight-title" class="sims-spotlight" @click="if ($event.target === $el) $el.close()" @close="$el.previousElementSibling.focus()">
        <div class="p-4 sm:p-6" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)">
            <div class="flex items-center justify-between mb-4"><h2 id="spotlight-title" class="font-bold">Pencarian cepat</h2><button type="button" @click="$refs.palette.close()" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600" aria-label="Tutup pencarian">Esc</button></div>
            <input x-ref="search" x-model="query" @input="selected = 0" @keydown.enter.prevent="go()" type="search" placeholder="Cari absensi, jadwal, kelas…" aria-label="Cari menu SIMS" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 p-3 bg-transparent focus:outline-2 focus:outline-blue-500">
            <p class="text-xs text-slate-500 dark:text-slate-400 my-3" x-text="query ? results.length + ' menu ditemukan' : 'Menu yang tersedia untuk akun Anda'"></p>
            <div class="max-h-[50dvh] overflow-y-auto space-y-1">
                <template x-for="(item, index) in results" :key="item.route">
                    <a :href="item.url" :data-selected="selected === index" @focus="selected = index" class="flex items-center justify-between gap-3 p-3 rounded-xl" :class="selected === index ? 'bg-blue-50 text-blue-900 dark:bg-slate-700 dark:text-white' : ''">
                        <span x-text="item.label"></span><span class="text-xs opacity-70" x-text="item.group || 'Menu'"></span>
                    </a>
                </template>
                <p x-show="!results.length" class="py-8 text-center text-sm">Menu tidak ditemukan. Coba kata lain.</p>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-4">↑ ↓ Pilih · Enter Buka · Esc Tutup</p>
        </div>
    </dialog>
</div>
<style>
.sims-spotlight { width: min(640px, calc(100vw - 24px)); max-height: 85dvh; margin: 10dvh auto auto; padding: 0; border: 1px solid #e2e8f0; border-radius: 22px; background: #fff; color: #0f172a; box-shadow: 0 24px 80px #02061740; }
.sims-spotlight::backdrop { background: #0f172a66; backdrop-filter: blur(4px); }
.dark .sims-spotlight { background: #0f172a; color: #f1f5f9; border-color: #334155; }
</style>
