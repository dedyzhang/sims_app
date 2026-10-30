<div x-data="{ prosemProtaSubTab: 'prosem_ganjil' }" class="space-y-4">
    {{-- Sub-tabs Navigasi Dokumen --}}
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-700 pb-3">
        <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800 text-xs font-semibold">
            <button type="button" @click="prosemProtaSubTab = 'prosem_ganjil'"
                    class="rounded-lg px-3 py-1.5 transition"
                    :class="prosemProtaSubTab === 'prosem_ganjil' ? 'bg-white text-primary shadow-sm dark:bg-slate-900 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'">
                <i data-lucide="calendar" class="w-3.5 h-3.5 inline mr-1"></i> Prosem (Ganjil)
            </button>
            <button type="button" @click="prosemProtaSubTab = 'prosem_genap'"
                    class="rounded-lg px-3 py-1.5 transition"
                    :class="prosemProtaSubTab === 'prosem_genap' ? 'bg-white text-primary shadow-sm dark:bg-slate-900 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'">
                <i data-lucide="calendar" class="w-3.5 h-3.5 inline mr-1"></i> Prosem (Genap)
            </button>
            <button type="button" @click="prosemProtaSubTab = 'prota'"
                    class="rounded-lg px-3 py-1.5 transition"
                    :class="prosemProtaSubTab === 'prota' ? 'bg-white text-primary shadow-sm dark:bg-slate-900 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'">
                <i data-lucide="table-properties" class="w-3.5 h-3.5 inline mr-1"></i> Prota (Tahunan)
            </button>
        </div>

        <div class="text-[11px] text-slate-500 font-medium">
            Format resmi sesuai acuan Kurikulum Merdeka SMP
        </div>
    </div>

    {{-- Konten Prosem Ganjil --}}
    <div x-show="prosemProtaSubTab === 'prosem_ganjil'" x-cloak class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white shadow-xs overflow-hidden">
        @include('ai.partials.prosem-document', ['prosem' => $doc['prosem']['ganjil']])
    </div>

    {{-- Konten Prosem Genap --}}
    <div x-show="prosemProtaSubTab === 'prosem_genap'" x-cloak class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white shadow-xs overflow-hidden">
        @include('ai.partials.prosem-document', ['prosem' => $doc['prosem']['genap']])
    </div>

    {{-- Konten Prota --}}
    <div x-show="prosemProtaSubTab === 'prota'" x-cloak class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white shadow-xs overflow-hidden">
        @include('ai.partials.prota-document', ['prota' => $doc['prota']])
    </div>
</div>
