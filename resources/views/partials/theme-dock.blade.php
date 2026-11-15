@if(!$kioskChrome && auth()->check() && !(\App\Support\ModeUjian::aktif() && $access === 'siswa'))
<nav class="sims-theme-dock" aria-label="Riwayat menu dan favorit, maksimal 6" x-show="dockVisible && dockItems().length" x-cloak>
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
                    <span class="sims-dock-icon" aria-hidden="true"><i :data-lucide="item.icon" class="w-5 h-5"></i></span>
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
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        </template>
    </div>
    <div class="sims-dock-controls">
        <button type="button" class="sims-dock-toggle-btn" @click="toggleDockVisibility()"
            title="Sembunyikan bilah riwayat (Hide)" aria-label="Sembunyikan bilah riwayat">
            <i data-lucide="eye-off" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Sembunyikan</span>
        </button>
    </div>
</nav>

<div class="sims-dock-launcher" x-show="!dockVisible && dockItems().length" x-cloak>
    <button type="button" class="sims-dock-launcher-btn" @click="toggleDockVisibility()"
        title="Tampilkan riwayat halaman (Show)" aria-label="Tampilkan riwayat halaman">
        <i data-lucide="history" class="w-3.5 h-3.5"></i>
        <span>Riwayat (<span x-text="dockItems().length"></span>)</span>
        <i data-lucide="eye" class="w-3.5 h-3.5 opacity-70"></i>
    </button>
</div>
@endif
