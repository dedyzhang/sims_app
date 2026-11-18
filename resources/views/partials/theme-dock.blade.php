@if(!$kioskChrome && auth()->check() && !(\App\Support\ModeUjian::aktif() && $access === 'siswa'))
<div class="sims-dock-drawer" :class="{ 'is-collapsed': !dockVisible }" x-show="dockItems().length" x-cloak>
    {{-- Tab Folder di atas bilah dock: klik untuk naik (buka) / turun (sembunyi) --}}
    <div class="sims-dock-handle-bar">
        <button type="button" class="sims-dock-folder-tab" @click="toggleDockVisibility()"
            :title="dockVisible ? 'Sembunyikan bilah tab (turun ke bawah)' : 'Buka bilah tab (naik ke atas)'"
            :aria-expanded="dockVisible ? 'true' : 'false'"
            aria-label="Toggle bilah riwayat tab">
            <span class="sims-folder-tab-icon flex items-center gap-1.5">
                <span x-show="!dockVisible" class="inline-flex items-center"><i data-lucide="folder" class="w-3.5 h-3.5 text-primary"></i></span>
                <span x-show="dockVisible" class="inline-flex items-center"><i data-lucide="folder-open" class="w-3.5 h-3.5 text-primary"></i></span>
            </span>
            <span class="sims-folder-tab-text font-semibold">
                Tab (<span x-text="dockItems().length"></span>)
            </span>
            <span class="inline-flex items-center transition-transform duration-200" :class="{ 'rotate-180': dockVisible }">
                <i data-lucide="chevron-up" class="w-3 h-3"></i>
            </span>
        </button>
    </div>

    {{-- Bilah tab item --}}
    <nav class="sims-theme-dock" aria-label="Riwayat menu dan favorit, maksimal 5">
        <div class="sims-dock-items" @dragover.prevent @dragenter.prevent @drop.prevent="onDockDropContainer($event)">
            <template x-for="item in dockItems()" :key="item.route">
                <div class="sims-dock-item"
                    :class="{
                        'is-current': isNavActive(item),
                        'is-dragging': dockDragSource === item.route,
                        'is-dragover': dockDragOverRoute === item.route && dockDragSource !== item.route
                    }"
                    draggable="true"
                    @dragstart="onDockDragStart($event, item.route)"
                    @dragover.prevent="onDockDragOver($event, item.route)"
                    @dragleave="onDockDragLeave($event, item.route)"
                    @drop.prevent.stop="onDockDrop($event, item.route)"
                    @dragend="onDockDragEnd($event)">
                    <a :href="item.url" :title="item.label + ' • Tarik untuk menggeser posisi'" :aria-current="isNavActive(item) ? 'page' : null" draggable="false"
                        @click="if (dockJustDragged) { $event.preventDefault(); }">
                        <span class="sims-dock-icon" aria-hidden="true"><i :data-lucide="item.icon" class="w-4 h-4"></i></span>
                        <span x-text="item.label"></span>
                    </a>
                    <button type="button" class="sims-dock-favorite" draggable="false" @mousedown.stop @click.stop="toggleNavFavorite(item.route)"
                        :class="{'is-favorite': isNavFavorite(item.route)}" :aria-pressed="isNavFavorite(item.route)"
                        :aria-label="(isNavFavorite(item.route) ? 'Lepas favorit: ' : 'Favoritkan: ') + item.label"
                        :title="isNavFavorite(item.route) ? 'Lepas favorit' : 'Jadikan favorit'">
                        <span aria-hidden="true" x-text="isNavFavorite(item.route) ? '★' : '☆'"></span>
                    </button>
                    <button type="button" class="sims-dock-remove" draggable="false" @mousedown.stop @click.stop.prevent="removeDockItem(item.route)"
                        :aria-label="'Hapus ' + item.label + ' dari daftar'"
                        :title="'Hapus ' + item.label + ' dari daftar'">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                </div>
            </template>
        </div>
    </nav>
</div>
@endif
