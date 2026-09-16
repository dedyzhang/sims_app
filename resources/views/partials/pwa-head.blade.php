{{-- PWA iOS (Add to Home Screen). SW tidak didaftarkan di WebView Android native. --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ config('pwa.theme_color', '#1e1b4b') }}">
<meta name="color-scheme" content="light">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $namaSekolah ?? \App\Models\Setting::get('nama_sekolah', 'Edutive') }}">
@php
  // Logo sekolah yang diunggah admin menang atas ikon bawaan public/icons/ — kalau
  // tidak, tiap sekolah dapat ikon home-screen sekolah lain. $sekolahLogoUrl datang
  // dari view composer AppServiceProvider; partial ini juga dipakai view tanpa
  // composer itu, jadi fallback ke PwaManifestController::identity().
  // Sentinelnya $namaSekolah, BUKAN $sekolahLogoUrl: composer selalu mengisi nama,
  // sedangkan logo boleh null — dan isset(null) === false akan memaksa query ulang
  // ke Pengaturan di SETIAP render halaman untuk sekolah yang belum unggah logo.
  $pwaIdentity = isset($namaSekolah)
      ? ['logoUrl' => $sekolahLogoUrl ?? null, 'logoExt' => $sekolahLogoExt ?? null, 'nama' => $namaSekolah]
      : \App\Http\Controllers\PwaManifestController::identity();
  $pwaIcon = in_array(strtolower((string) ($pwaIdentity['logoExt'] ?? '')), ['png','jpg','jpeg','webp'], true)
      ? $pwaIdentity['logoUrl']
      : null;
@endphp
@if($pwaIcon)
<link rel="apple-touch-icon" href="{{ $pwaIcon }}">
@else
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="apple-touch-icon" sizes="152x152" href="{{ asset('icons/apple-touch-icon-152.png') }}">
<link rel="apple-touch-icon" sizes="167x167" href="{{ asset('icons/apple-touch-icon-167.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
@endif
@if(config('pwa.splash_enabled'))
@include('partials.pwa-splash')
@endif
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/favicon-16.png') }}">
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      if (window.AndroidFcm) return;
      navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
        .catch(function (err) { console.warn('SW gagal daftar:', err); });
    });
  }
</script>
