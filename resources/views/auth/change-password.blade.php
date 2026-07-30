@extends('layouts.app')
@section('title', 'Keamanan Akun')

@section('content')
@php $breadcrumbs = [['label'=>'Profil','url'=>route('profile.index')], ['label'=>'Keamanan Akun','url'=>'#']]; @endphp

<div class="max-w-md mx-auto" x-data="{ tab: localStorage.getItem('security_tab') || 'password', setTab(name) { this.tab = name; localStorage.setItem('security_tab', name); } }">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('profile.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Keamanan Akun</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Atur metode masuk ke akun Anda</p>
        </div>
    </div>

    @if(auth()->user()->must_change_password)
        @if(!auth()->user()->username_customized)
            <div class="mb-5 p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 flex gap-3 text-amber-800 dark:text-amber-200 text-sm animate-pulse">
                <i data-lucide="shield-alert" class="w-5 h-5 flex-shrink-0 text-amber-600 dark:text-amber-400"></i>
                <div>
                    <p class="font-bold">Langkah Wajib: Kustomisasi Username</p>
                    <p class="mt-0.5 text-xs text-amber-700/90 dark:text-amber-300/90 leading-relaxed">
                        Anda saat ini menggunakan username bawaan (default) sistem. Demi perlindungan akun Anda, Anda <strong>wajib mengganti username terlebih dahulu</strong> sebelum dapat mengganti password, mengatur PIN, atau mengaktifkan autentikasi biometrik.
                    </p>
                </div>
            </div>

            {{-- Tabs (Locked) --}}
            <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1 mb-5 opacity-60">
                <button type="button" disabled class="flex-1 py-1.5 rounded-lg text-sm font-medium text-slate-400 cursor-not-allowed flex items-center justify-center gap-1.5">
                    <i data-lucide="lock" class="w-4 h-4"></i> Password (Locked)
                </button>
                <button type="button" disabled class="flex-1 py-1.5 rounded-lg text-sm font-medium text-slate-400 cursor-not-allowed flex items-center justify-center gap-1.5">
                    <i data-lucide="lock" class="w-4 h-4"></i> PIN (Locked)
                </button>
                <button type="button" disabled class="flex-1 py-1.5 rounded-lg text-sm font-medium text-slate-400 cursor-not-allowed flex items-center justify-center gap-1.5">
                    <i data-lucide="lock" class="w-4 h-4"></i> Biometrik (Locked)
                </button>
            </div>

            {{-- Form Kustomisasi Username --}}
            <div class="card p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <i data-lucide="user-cog" class="w-5 h-5 text-primary"></i> Langkah 1: Kustomisasi Username
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Silakan ubah username bawaan Anda ke nama pengguna kustom yang unik dan mudah diingat.
                </p>

                <form method="POST" action="{{ route('ganti.username') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="form-label">Username Kustom Baru</label>
                        <input type="text" name="username" value="{{ old('username', auth()->user()->username) }}" required class="form-input font-mono" placeholder="Masukkan username kustom Anda">
                        @error('username')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                        <p class="text-[11px] text-slate-400 mt-1.5">Hanya boleh berisi huruf, angka, titik (.), dan underscore (_). Minimal terdiri dari 4 karakter.</p>
                    </div>
                    <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Simpan Username & Lanjutkan
                    </button>
                </form>
            </div>
        @else
            <div class="mb-5 p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 flex gap-3 text-amber-800 dark:text-amber-200 text-sm">
                <i data-lucide="shield-check" class="w-5 h-5 flex-shrink-0 text-amber-600 dark:text-amber-400"></i>
                <div>
                    <p class="font-bold">Langkah 2: Amankan Akun Anda</p>
                    <p class="mt-0.5 text-xs text-amber-700/90 dark:text-amber-300/90 leading-relaxed">
                        Username Anda berhasil diperbarui. Silakan ubah password Anda di bawah ini untuk menyelesaikan proses keamanan.
                    </p>
                </div>
            </div>
        @endif
    @endif

    @if(!auth()->user()->must_change_password || auth()->user()->username_customized)
        {{-- ===== Password Tab ===== --}}
        <div>
            <form method="POST" action="/ganti-password" class="card p-6 space-y-4" x-data="{ s1:false, s2:false, s3:false }">
                @csrf
                <div>
                    <label class="form-label">Password Lama</label>
                    <div class="relative">
                        <input :type="s1?'text':'password'" name="current_password" required class="form-input pr-11">
                        <button type="button" @click="s1=!s1" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i :data-lucide="s1?'eye-off':'eye'" class="w-4 h-4"></i>
                        </button>
                    </div>
                    @error('current_password')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">Password Baru</label>
                    <div class="relative">
                        <input :type="s2?'text':'password'" name="new_password" required class="form-input pr-11">
                        <button type="button" @click="s2=!s2" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i :data-lucide="s2?'eye-off':'eye'" class="w-4 h-4"></i>
                        </button>
                    </div>
                    @error('new_password')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" name="new_password_confirmation" required class="form-input">
                </div>
                <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                    <i data-lucide="key-round" class="w-4 h-4"></i> Simpan Password
                </button>
            </form>
        </div>



    @endif
</div>

@push('scripts')
<script>
    // lucide sekarang defer (lihat layouts/app.blade.php) — dijaga spt konvensi file lain di
    // codebase ini, ikon halaman ini tetap ter-render ulang penuh lewat DOMContentLoaded di layout.
    if (window.lucide) lucide.createIcons();

    async function registerBiometric() {
        try {
            showToast('Menghubungkan ke authenticator...', 'info');
            
            // 1. Get options from server
            const optionsResponse = await fetch('{{ route('webauthn.register.options') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({})
            });
            if (!optionsResponse.ok) {
                throw new Error(await _errMessage(optionsResponse, 'Gagal mengambil opsi registrasi.'));
            }
            const options = await optionsResponse.json();
            
            // 2. Decode base64 strings to ArrayBuffers
            options.challenge = _b64(options.challenge);
            options.user.id = _b64(options.user.id);
            if (options.excludeCredentials) {
                options.excludeCredentials = options.excludeCredentials.map(c => ({
                    ...c,
                    id: _b64(c.id)
                }));
            }
            
            // 3. Prompt user for biometric credential (fingerprint/Face ID)
            const credential = await navigator.credentials.create({ publicKey: options });
            
            // 4. Send credential to server for registration
            const registerResponse = await fetch('{{ route('webauthn.register') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    id: credential.id,
                    rawId: _buf(credential.rawId),
                    type: credential.type,
                    response: {
                        attestationObject: _buf(credential.response.attestationObject),
                        clientDataJSON: _buf(credential.response.clientDataJSON),
                        transports: credential.response.getTransports ? credential.response.getTransports() : []
                    }
                })
            });
            
            if (registerResponse.ok) {
                showToast('Biometrik berhasil didaftarkan!', 'success');
                setTimeout(() => {
                    window.location.href = '{{ route('dashboard') }}';
                }, 1000);
            } else {
                showToast(await _errMessage(registerResponse, 'Gagal mendaftarkan biometrik.'), 'error');
            }
        } catch (err) {
            console.error(err);
            showToast(err.message || 'Pendaftaran biometrik dibatalkan atau tidak didukung.', 'error');
        }
    }

    // Ambil pesan error yang manusiawi walau server membalas HTML (mis. 419/500),
    // supaya tidak muncul "Unexpected token '<'" dari JSON.parse.
    async function _errMessage(res, fallback) {
        const text = await res.text().catch(() => '');
        try { const j = JSON.parse(text); if (j && j.message) return j.message; } catch (e) {}
        return fallback + ' (HTTP ' + res.status + ')';
    }

    function _b64(s) { const b=atob(s.replace(/-/g,'+').replace(/_/g,'/')); return Uint8Array.from(b,c=>c.charCodeAt(0)).buffer; }
    function _buf(b) { return btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,''); }
</script>
@endpush
@endsection
