@if(!$kioskChrome && auth()->check() && !(\App\Support\ModeUjian::aktif() && $access === 'siswa'))
<nav class="sims-theme-dock" aria-label="Riwayat menu dan favorit, maksimal 10" x-show="dockItems().length" x-cloak>
    <div class="sims-dock-items">
        <template x-for="item in dockItems()" :key="item.route">
            <div class="sims-dock-item" :class="{'is-current': isNavActive(item)}">
                <a :href="item.url" :title="item.label" :aria-current="isNavActive(item) ? 'page' : null">
                    <span class="sims-dock-icon" aria-hidden="true"><i :data-lucide="item.icon" class="w-5 h-5"></i></span>
                    <span x-text="item.label"></span>
                </a>
                <button type="button" class="sims-dock-favorite" @click.stop="toggleNavFavorite(item.route)"
                    :class="{'is-favorite': isNavFavorite(item.route)}" :aria-pressed="isNavFavorite(item.route)"
                    :aria-label="(isNavFavorite(item.route) ? 'Lepas favorit: ' : 'Favoritkan: ') + item.label"
                    :title="isNavFavorite(item.route) ? 'Lepas favorit' : 'Jadikan favorit'">
                    <span aria-hidden="true" x-text="isNavFavorite(item.route) ? '★' : '☆'"></span>
                </button>
            </div>
        </template>
    </div>
</nav>
@endif
