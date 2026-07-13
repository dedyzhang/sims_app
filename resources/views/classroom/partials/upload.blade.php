{{-- Komponen upload multi-file (gambar/PDF) dgn preview ukuran.
     Var opsional: $label, $maxMb, $maxFiles, $acceptLabel, $acceptAttr --}}
<div x-data="cgUpload()" class="space-y-2">
    <label class="block">
        <div class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-5 text-center cursor-pointer hover:border-primary transition"
             @dragover.prevent="hover=true" @dragleave.prevent="hover=false" @drop.prevent="onDrop($event)" :class="hover ? 'border-primary bg-primary/5' : ''">
            <i data-lucide="upload-cloud" class="w-7 h-7 mx-auto text-slate-400 mb-1"></i>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label ?? 'Seret & lepas file, atau klik untuk pilih' }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ $acceptLabel ?? 'Gambar (JPG/PNG/WEBP/HEIC) atau PDF' }} · maks {{ $maxMb ?? config('classroom.max_file_mb', 20) }}MB · maks {{ $maxFiles ?? config('classroom.max_files', 10) }} file</p>
            <input type="file" name="files[]" multiple accept="{{ $acceptAttr ?? 'image/*,application/pdf' }}" class="hidden" x-ref="input" @change="onPick($event)">
        </div>
    </label>
    <template x-if="items.length">
        <ul class="space-y-1">
            <template x-for="(f,i) in items" :key="i">
                <li class="flex items-center justify-between text-xs bg-slate-50 dark:bg-slate-700/40 rounded-lg px-3 py-1.5">
                    <span class="truncate flex items-center gap-1.5"><i data-lucide="file" class="w-3.5 h-3.5 text-slate-400"></i> <span x-text="f.name"></span></span>
                    <span class="text-slate-400 flex-shrink-0" x-text="f.size"></span>
                </li>
            </template>
            <li class="text-[11px] text-slate-400">File akan dikompres otomatis sebelum disimpan.</li>
        </ul>
    </template>
</div>

@once
@push('scripts')
<script>
function cgUpload() {
    return {
        hover: false, items: [],
        human(b){ const u=['B','KB','MB','GB']; let i=0; while(b>=1024&&i<u.length-1){b/=1024;i++;} return b.toFixed(1)+' '+u[i]; },
        sync(files){ this.items = Array.from(files).map(f => ({ name: f.name, size: this.human(f.size) })); if(window.lucide) lucide.createIcons(); },
        onPick(e){ this.sync(e.target.files); },
        onDrop(e){ this.hover=false; this.$refs.input.files = e.dataTransfer.files; this.sync(e.dataTransfer.files); }
    };
}
</script>
@endpush
@endonce
