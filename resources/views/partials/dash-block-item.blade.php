{{-- Satu blok dashboard yang bisa di-drag/sembunyikan. Butuh: $block, $spans, $hiddenBlocks, $blockLabel --}}
<div class="dash-block {{ $spans[$block] ?? 'col-span-12' }} {{ in_array($block, $hiddenBlocks) ? 'dash-hidden' : '' }}" data-block="{{ $block }}" :class="{ 'dash-hidden': hidden.includes('{{ $block }}'), 'dash-collapsed': collapsed.includes('{{ $block }}') }">
    <span class="dash-handle"><i data-lucide="grip-vertical" class="w-3.5 h-3.5"></i> {{ $blockLabel[$block] ?? $block }}</span>
    <button type="button" class="dash-collapse" @click.stop.prevent="toggleCollapse('{{ $block }}')"
            :title="collapsed.includes('{{ $block }}') ? 'Buka blok' : 'Ciutkan blok'">
        <i data-lucide="chevron-up" class="w-3.5 h-3.5" x-show="!collapsed.includes('{{ $block }}')"></i>
        <i data-lucide="chevron-down" class="w-3.5 h-3.5" x-show="collapsed.includes('{{ $block }}')" x-cloak></i>
    </button>
    <button type="button" class="dash-remove" @click.stop.prevent="toggleHide('{{ $block }}')"
            :title="hidden.includes('{{ $block }}') ? 'Tampilkan blok' : 'Sembunyikan blok'">
        <i data-lucide="x" class="w-3.5 h-3.5" x-show="!hidden.includes('{{ $block }}')"></i>
        <i data-lucide="plus" class="w-3.5 h-3.5" x-show="hidden.includes('{{ $block }}')" x-cloak></i>
    </button>
    <span class="dash-hidden-badge">Disembunyikan</span>
    <span class="dash-collapsed-badge">Diciutkan</span>
    <div class="dash-content">
        @includeIf('dashboard.blocks.'.$block)
    </div>
</div>
