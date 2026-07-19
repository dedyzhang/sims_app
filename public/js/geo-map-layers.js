/**
 * Basemap gratis untuk absensi QR — OpenStreetMap (jalan) + Esri World Imagery (satelit).
 * Tanpa API key. Dipakai di Pengaturan pin sekolah & halaman Absen QR.
 */
(function (global) {
    'use strict';

    function streetLayer() {
        return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        });
    }

    function satelliteLayer() {
        // Esri World Imagery — citra satelit gratis untuk basemap (attribution wajib).
        return L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Earthstar Geographics',
            }
        );
    }

    /**
     * Pasang layer awal + kembalikan kontrol untuk ganti mode.
     * @param {L.Map} map
     * @param {'street'|'satellite'} initial
     * @returns {{ mode: string, setMode: function(string): void, street: L.TileLayer, satellite: L.TileLayer }}
     */
    function attach(map, initial) {
        var street = streetLayer();
        var satellite = satelliteLayer();
        var mode = initial === 'satellite' ? 'satellite' : 'street';
        var active = mode === 'satellite' ? satellite : street;
        active.addTo(map);

        function setMode(next) {
            next = next === 'satellite' ? 'satellite' : 'street';
            if (next === mode) return;
            var incoming = next === 'satellite' ? satellite : street;
            map.removeLayer(active);
            incoming.addTo(map);
            // Pastikan tile di bawah marker/circle.
            try { incoming.bringToBack(); } catch (e) {}
            active = incoming;
            mode = next;
        }

        return {
            get mode() { return mode; },
            setMode: setMode,
            street: street,
            satellite: satellite,
        };
    }

    global.SimsMapLayers = {
        streetLayer: streetLayer,
        satelliteLayer: satelliteLayer,
        attach: attach,
    };
})(typeof window !== 'undefined' ? window : this);
