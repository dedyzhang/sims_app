/*
 * Service Worker — SIMS (dipakai semua sekolah; tidak boleh memuat identitas sekolah
 * tertentu. Nama & ikon datang dari /manifest.webmanifest yang dirender per-deployment).
 * Strategi aman untuk portal ber-login (SIMS):
 *  - Navigasi halaman (HTML): NETWORK-FIRST -> kalau offline tampilkan offline.html.
 *    (Tidak meng-cache halaman ber-sesi supaya login/keamanan tidak bocor / basi.)
 *  - Aset statis (ikon, gambar, css/js publik): CACHE-FIRST (stale-while-revalidate).
 *  - Request POST / API / storage privat: SELALU lewat jaringan, tidak pernah di-cache.
 */

const VERSION = 'sims-v1.2.0';
const STATIC_CACHE = `static-${VERSION}`;

// Aset shell yang aman untuk di-precache (publik, non-sesi)
// Manifest TIDAK diprecache: isinya dirender per-sekolah dan bisa berubah kapan pun
// admin mengganti nama/logo — versi basi berarti ikon sekolah lama menempel di iOS.
const PRECACHE_URLS = [
  '/offline.html',
  '/icons/apple-touch-icon.png',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/favicon-32.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) =>
      // addAll gagal-total kalau satu file 404; pakai per-file agar tahan banting.
      Promise.all(
        PRECACHE_URLS.map((url) =>
          cache.add(url).catch((err) => console.warn('[SW] gagal precache', url, err))
        )
      )
    )
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== STATIC_CACHE).map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// Apakah URL termasuk aset statis publik yang aman di-cache?
// Sengaja DIBATASI ke folder aset build/publik saja. TIDAK meng-cache /storage
// (foto siswa/guru & logo yang bisa berubah) agar tidak menyimpan data privat
// dan tidak menampilkan gambar basi setelah diganti.
function isStaticAsset(url) {
  return /^\/(icons|splash|build|assets|css|js|fonts)\//.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Hanya tangani GET & origin yang sama. Sisanya (POST login, API, lintas-origin) langsung ke jaringan.
  if (req.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  // Jangan cache endpoint sensitif / dinamis.
  if (/^\/(api|storage\/privat|logout|sanctum|livewire)/i.test(url.pathname)) {
    return;
  }

  // Aset statis: cache-first + revalidate di belakang layar.
  if (isStaticAsset(url)) {
    // waitUntil() WAJIB di cabang cache-hit: tanpa itu SW bisa dimatikan browser
    // sebelum cache.put() selesai, jadi aset tak pernah benar-benar direvalidasi.
    const revalidating = caches.open(STATIC_CACHE).then(async (cache) => {
      const cached = await cache.match(req);
      const network = fetch(req)
        .then((res) => {
          if (res && res.status === 200) return cache.put(req, res.clone()).then(() => res);
          return res;
        })
        .catch(() => cached);
      if (cached) {
        event.waitUntil(network);
        return cached;
      }
      return network;
    });
    event.respondWith(revalidating);
    return;
  }

  // Navigasi halaman: network-first, fallback offline.html saat tidak ada jaringan.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() =>
        caches.match('/offline.html').then((res) => res || Response.error())
      )
    );
    return;
  }
});

/* ---- Notifikasi (siap pakai bila server nanti mengirim Web Push, iOS 16.4+) ---- */
self.addEventListener('push', (event) => {
  let data = { title: 'SIMS Sekolah', body: 'Ada informasi baru.', url: '/' };
  try { if (event.data) data = Object.assign(data, event.data.json()); } catch (e) {}
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/icons/icon-192.png',
      badge: '/icons/favicon-32.png',
      data: { url: data.url || '/' }
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) {
        if ('focus' in c) { c.navigate(target); return c.focus(); }
      }
      return clients.openWindow(target);
    })
  );
});
