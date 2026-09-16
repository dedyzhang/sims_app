<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title>Pasang Aplikasi iPhone — {{ $namaSekolah ?? 'SMP Maitreyawira TPI' }}</title>
@include('partials.pwa-head')
<style>
  :root{--brand:#1e3a8a;--brand-dark:#1e1b4b;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--bg:#f1f5f9;--card:#fff;color-scheme:light}
  *{box-sizing:border-box}html,body{margin:0}
  body{font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:var(--bg);
    padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left)}
  .wrap{max-width:520px;margin:0 auto;padding:0 18px 48px}
  header{text-align:center;color:#fff;border-radius:0 0 28px 28px;background:linear-gradient(160deg,var(--brand),var(--brand-dark));padding:40px 24px 32px;margin:0 -18px 24px}
  .app-icon{width:96px;height:96px;border-radius:22px;background:#fff;padding:12px;margin:0 auto 16px;display:block;object-fit:contain;box-shadow:0 12px 30px rgba(0,0,0,.25)}
  header h1{font-size:22px;margin:0 0 4px;font-weight:700}
  header .sub{font-size:14px;opacity:.85;margin:0}
  .badge{display:inline-flex;align-items:center;gap:6px;margin-top:14px;font-size:12px;font-weight:600;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);padding:6px 12px;border-radius:999px}
  .banner{border-radius:16px;padding:14px 16px;margin:0 0 18px;font-size:14px;display:none}
  .banner.show{display:block}
  .banner.ok{background:#dcfce7;border:1px solid #86efac;color:#166534}
  .banner.warn{background:#fef3c7;border:1px solid #fcd34d;color:#92400e}
  .card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:20px;margin-bottom:18px}
  .card h2{font-size:15px;margin:0 0 16px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
  ol.steps{list-style:none;counter-reset:s;margin:0;padding:0}
  ol.steps li{counter-increment:s;position:relative;padding:0 0 20px 52px;min-height:36px}
  ol.steps li:last-child{padding-bottom:0}
  ol.steps li::before{content:counter(s);position:absolute;left:0;top:0;width:34px;height:34px;border-radius:50%;background:var(--brand);color:#fff;font-weight:700;display:grid;place-items:center;font-size:15px}
  ol.steps li::after{content:"";position:absolute;left:16px;top:36px;bottom:6px;width:2px;background:var(--line)}
  ol.steps li:last-child::after{display:none}
  .steptitle{font-weight:600;margin:5px 0 2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .steptitle small{color:var(--muted);font-weight:400;font-size:13px}
  .ic{display:inline-grid;place-items:center;width:26px;height:26px;border-radius:7px;background:#eef2ff;color:var(--brand);vertical-align:-6px}
  .ic svg{width:16px;height:16px}
  .btn{display:block;text-align:center;text-decoration:none;font-weight:600;padding:16px;border-radius:14px;background:var(--brand);color:#fff;margin-bottom:12px}
  .btn.secondary{background:#eef2ff;color:var(--brand)}
  .btn:active{transform:scale(.99)}
  .foot{text-align:center;color:var(--muted);font-size:12px;margin-top:24px}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <img class="app-icon" src="{{ asset('icons/apple-touch-icon.png') }}" alt="Logo sekolah" />
    <h1>{{ $namaSekolah ?? 'SMP Maitreyawira TPI' }}</h1>
    <p class="sub">Sistem Informasi Manajemen Sekolah</p>
    <span class="badge">Versi iPhone / iPad — Gratis</span>
  </header>

  <div id="bannerInstalled" class="banner ok">
    Aplikasi sudah terpasang &amp; sedang berjalan. Anda bisa langsung <a href="{{ url('/') }}">membuka portal</a>.
  </div>
  <div id="bannerBrowser" class="banner warn">
    Sepertinya halaman ini dibuka di browser lain (mis. Chrome/Instagram/WhatsApp). Agar bisa dipasang, buka alamat ini memakai <b>Safari</b>.
  </div>

  <div class="card">
    <h2>Cara Pasang di iPhone / iPad</h2>
    <ol class="steps">
      <li><div class="steptitle">Buka di aplikasi <b>Safari</b></div><small>Salin alamat ini lalu buka lewat Safari bila belum.</small></li>
      <li>
        <div class="steptitle">Ketuk tombol <b>Bagikan</b>
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg></span>
        </div><small>Ikon kotak dengan panah ke atas, di bar bawah Safari.</small>
      </li>
      <li>
        <div class="steptitle">Pilih <b>Tambahkan ke Layar Utama</b>
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg></span>
        </div><small>Gulir menu bila belum kelihatan (“Add to Home Screen”).</small>
      </li>
      <li><div class="steptitle">Ketuk <b>Tambah / Add</b></div><small>Ikon sekolah akan muncul di layar utama seperti aplikasi biasa.</small></li>
    </ol>
  </div>

  <a class="btn" href="{{ url('/') }}">Buka Portal Sekarang</a>
  <a class="btn secondary" id="copyLink" href="#">Salin Alamat Situs</a>

  <p class="foot">Aplikasi berbasis web (PWA), tidak perlu App Store.</p>
</div>

<script>
  (function(){
    var isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    var ua = navigator.userAgent || '';
    var isIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var inApp = /CriOS|FxiOS|EdgiOS|GSA|FBAN|FBAV|Instagram|Line|WhatsApp|OKHttp/i.test(ua);
    if (isStandalone) document.getElementById('bannerInstalled').classList.add('show');
    else if (isIOS && inApp) document.getElementById('bannerBrowser').classList.add('show');
    document.getElementById('copyLink').addEventListener('click', function(e){
      e.preventDefault();
      var url = location.origin + '/';
      var done = function(){ this.textContent = 'Alamat tersalin'; }.bind(this);
      if (navigator.clipboard) navigator.clipboard.writeText(url).then(done, done);
      else prompt('Salin alamat berikut:', url);
    });
  })();
</script>
</body>
</html>
