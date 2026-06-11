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
                        Username Anda berhasil diperbarui. Silakan pilih salah satu metode pengamanan akun di bawah ini (Ubah Password, Atur PIN, atau Aktifkan Biometrik) untuk menyelesaikan proses keamanan.
                    </p>
                </div>
            </div>
        @endif
    @endif

    @if(!auth()->user()->must_change_password || auth()->user()->username_customized)
        {{-- Tabs --}}
        <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1 mb-5">
            <button @click="setTab('password')" :class="tab==='password' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-800 dark:text-slate-100' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="flex-1 py-1.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1.5">
                <i data-lucide="key-round" class="w-4 h-4"></i> Password
            </button>
            <button @click="setTab('pin')" :class="tab==='pin' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-800 dark:text-slate-100' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="flex-1 py-1.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1.5">
                <i data-lucide="lock-keyhole" class="w-4 h-4"></i> PIN
            </button>
            <button @click="setTab('biometric')" :class="tab==='biometric' ? 'bg-white dark:bg-slate-700 shadow-sm text-slate-800 dark:text-slate-100' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                    class="flex-1 py-1.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1.5">
                <i data-lucide="fingerprint" class="w-4 h-4"></i> Biometrik
            </button>
        </div>

        {{-- ===== Password Tab ===== --}}
        <div x-show="tab==='password'" x-transition>
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

        {{-- ===== PIN Tab ===== --}}
        <div x-show="tab==='pin'" x-transition>
            <div class="flex items-center gap-3 mb-4 p-4 rounded-xl bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700">
                <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900 grid place-items-center flex-shrink-0">
                    <i data-lucide="lock-keyhole" class="w-5 h-5 text-violet-600"></i>
                </div>
                <p class="text-xs text-violet-800 dark:text-violet-300">PIN 6 digit angka memungkinkan login kilat di perangkat mobile tanpa mengetik password.</p>
            </div>

            <form method="POST" action="/ganti-pin" class="card p-6 space-y-4">
                @csrf
                <div>
                    <label class="form-label">Password (verifikasi)</label>
                    <input type="password" name="password" required placeholder="Masukkan password Anda" class="form-input">
                    @error('password')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">PIN Baru (6 digit)</label>
                    <input type="password" name="pin" inputmode="numeric" maxlength="6" pattern="\d{6}" required placeholder="••••••" class="form-input text-center text-2xl tracking-[0.5em] font-mono">
                </div>
                <div>
                    <label class="form-label">Konfirmasi PIN</label>
                    <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="6" pattern="\d{6}" required placeholder="••••••" class="form-input text-center text-2xl tracking-[0.5em] font-mono">
                </div>
                <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                    <i data-lucide="lock-keyhole" class="w-4 h-4"></i> Simpan PIN
                </button>
            </form>
        </div>

        {{-- ===== Biometric Tab ===== --}}
        <div x-show="tab==='biometric'" x-transition class="card p-6 text-center space-y-5">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 grid place-items-center mx-auto shadow-sm">
                <i data-lucide="fingerprint" class="w-8 h-8"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 text-base">Verifikasi Biometrik (WebAuthn)</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Daftarkan sidik jari atau Face ID perangkat Anda untuk login cepat di peramban (browser) ini tanpa mengetik password.</p>
            </div>
            <button type="button" onclick="registerBiometric()" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Daftarkan Perangkat Biometrik
            </button>
        </div>
    @endif
</div>

@push('scripts')
<script>
    lucide.createIcons();

    async function registerBiometric() {
        try {
            showToast('Menghubungkan ke authenticator...', 'info');
            
            // 1. Get options from server
            const optionsResponse = await fetch('/webauthn/register/options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({})
            });
            if (!optionsResponse.ok) {
                const errData = await optionsResponse.json();
                throw new Error(errData.message || 'Gagal mengambil opsi registrasi.');
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
            const registerResponse = await fetch('/webauthn/register', {
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
                const errData = await registerResponse.json();
                showToast(errData.message || 'Gagal mendaftarkan biometrik.', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast(err.message || 'Pendaftaran biometrik dibatalkan atau tidak didukung.', 'error');
        }
    }

    function _b64(s) { const b=atob(s.replace(/-/g,'+').replace(/_/g,'/')); return Uint8Array.from(b,c=>c.charCodeAt(0)).buffer; }
    function _buf(b) { return btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,''); }
</script>
@endpush
@endsection
