<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — {{ $namaSekolah ?? 'Edu Nusantara' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .field { transition: border-color .15s, box-shadow .15s; }
        .field:focus { border-color:#0f172a; box-shadow:0 0 0 3px rgba(15,23,42,.08); outline:none; }
        .pin-btn { transition: background .12s, transform .1s; }
        .pin-btn:active { transform: scale(.94); background:#f1f5f9; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)} 40%{transform:translateX(8px)} 60%{transform:translateX(-5px)} 80%{transform:translateX(5px)} }
        .shake { animation: shake .45s ease; }
        @keyframes rise { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .rise { animation: rise .4s ease both; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-10">

<div x-data="loginApp()" class="w-full max-w-sm rise">

    {{-- Brand --}}
    <div class="flex flex-col items-center mb-8">
        <div class="w-12 h-12 rounded-2xl bg-slate-900 grid place-items-center mb-3 shadow-sm">
            <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-white" stroke="currentColor" stroke-width="2.2"><path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h1 class="text-lg font-bold text-slate-800">{{ $namaSekolah ?? 'Edu Nusantara' }}</h1>
        <p class="text-sm text-slate-400 mt-0.5">Silakan masuk ke akun Anda</p>
    </div>

    {{-- Card --}}
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 sm:p-7">

        @if($errors->any())
        <div class="mb-5 flex items-center gap-2.5 bg-rose-50 border border-rose-100 rounded-xl px-3.5 py-2.5 text-sm text-rose-600">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            {{ $errors->first() }}
        </div>
        @endif
        @if(session('success'))
        <div class="mb-5 bg-emerald-50 border border-emerald-100 rounded-xl px-3.5 py-2.5 text-sm text-emerald-600">{{ session('success') }}</div>
        @endif

        {{-- Tabs --}}
        <div class="flex gap-1 bg-slate-100 rounded-xl p-1 mb-6">
            <button @click="tab='password'" :class="tab==='password' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-1.5 rounded-lg text-sm font-medium transition">Password</button>
            <button @click="tab='pin'" :class="tab==='pin' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-1.5 rounded-lg text-sm font-medium transition">PIN</button>
            <button @click="tab='biometric'; tryBiometric()" x-show="biometricAvailable"
                    :class="tab==='biometric' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 py-1.5 rounded-lg text-sm font-medium transition">Biometrik</button>
        </div>

        {{-- ===== Password ===== --}}
        <div x-show="tab==='password'">
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1.5">Username / NIK / NIS</label>
                    <input type="text" name="credential" value="{{ old('credential') }}" required autofocus placeholder="Masukkan username / NIK / NIS"
                           class="field w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-300 bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1.5">Password</label>
                    <div class="relative">
                        <input :type="showPass ? 'text' : 'password'" name="password" required placeholder="Password"
                               class="field w-full border border-slate-200 rounded-xl px-3.5 py-2.5 pr-10 text-sm text-slate-800 placeholder-slate-300 bg-white">
                        <button type="button" @click="showPass=!showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-slate-500 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-slate-900"> Ingat saya
                    </label>
                    <button type="button" @click="tab='forgot'" class="text-sm text-slate-500 hover:text-slate-800">Lupa password?</button>
                </div>
                <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold transition">Masuk</button>
            </form>
        </div>

        {{-- ===== PIN ===== --}}
        <div x-show="tab==='pin'">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1.5">Username / NIK / NIS</label>
                    <input type="text" x-model="pinCredential" placeholder="Masukkan username / NIK / NIS"
                           class="field w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-300 bg-white">
                </div>
                <div class="py-2">
                    <p class="text-center text-xs text-slate-400 mb-3">Masukkan PIN 6 digit</p>
                    <div class="flex justify-center gap-2.5" :class="{ shake: pinError }">
                        <template x-for="i in 6"><div :class="pin.length>=i ? 'bg-slate-900 border-slate-900' : 'bg-white border-slate-300'" class="w-3.5 h-3.5 rounded-full border-2 transition"></div></template>
                    </div>
                    <p x-show="pinError" class="text-center text-rose-500 text-xs mt-2">PIN salah atau akun tidak ditemukan</p>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="btn in ['1','2','3','4','5','6','7','8','9','','0','⌫']">
                        <button @click="pinPress(btn)" :class="btn==='' ? 'invisible' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-100'"
                                class="pin-btn rounded-xl py-3 text-lg font-semibold transition"><span x-text="btn"></span></button>
                    </template>
                </div>
            </div>
        </div>

        {{-- ===== Biometrik ===== --}}
        <div x-show="tab==='biometric'">
            <div class="text-center py-6 space-y-4">
                <div @click="tryBiometric()" class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-100 hover:bg-slate-200 cursor-pointer transition mx-auto">
                    <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                </div>
                <p class="text-sm text-slate-500" x-text="biometricStatus">Ketuk untuk verifikasi biometrik</p>
                <button @click="tab='password'" class="text-sm text-slate-500 hover:text-slate-800">Gunakan password</button>
            </div>
        </div>

        {{-- ===== Lupa Password ===== --}}
        <div x-show="tab==='forgot'">
            <form method="POST" action="{{ route('password.request') }}" class="space-y-4">
                @csrf
                <p class="text-sm text-slate-500">Masukkan username atau NIK Anda. Permintaan reset diteruskan ke admin.</p>
                <input type="text" name="credential" placeholder="Username / NIK / NIS" required
                       class="field w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-300 bg-white">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold transition">Kirim Permintaan</button>
                <button type="button" @click="tab='password'" class="w-full text-center text-sm text-slate-400 hover:text-slate-600">← Kembali</button>
            </form>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">&copy; {{ date('Y') }} {{ $namaSekolah ?? 'Edu Nusantara' }}</p>
</div>

<script>
function loginApp() {
    return {
        tab: 'password', showPass: false, pin: '', pinCredential: '', pinError: false,
        biometricAvailable: false, biometricStatus: 'Ketuk untuk verifikasi biometrik',
        async init() {
            if (window.PublicKeyCredential) {
                this.biometricAvailable = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
            }
        },
        pinPress(btn) {
            if (btn === '⌫') { this.pin = this.pin.slice(0, -1); this.pinError = false; }
            else if (btn !== '' && this.pin.length < 6) { this.pin += btn; if (this.pin.length === 6) this.submitPin(); }
        },
        async submitPin() {
            if (!this.pinCredential) { this.pinError = true; this.pin = ''; return; }
            try {
                const res = await fetch('{{ route('login.pin') }}', { method:'POST', headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ credential:this.pinCredential, pin:this.pin }) });
                const data = await res.json();
                if (res.ok) { window.location.href = data.redirect || '/home'; } else { this.pinError = true; this.pin = ''; }
            } catch { this.pinError = true; this.pin = ''; }
        },
        async tryBiometric() {
            this.biometricStatus = 'Menunggu verifikasi...';
            try {
                const optRes = await fetch('{{ route('webauthn.login.options') }}', { method:'POST', headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({}) });
                const options = await optRes.json();
                options.challenge = this._b64(options.challenge);
                if (options.allowCredentials) options.allowCredentials = options.allowCredentials.map(c => ({...c, id: this._b64(c.id)}));
                const credential = await navigator.credentials.get({ publicKey: options });
                const verifyRes = await fetch('{{ route('webauthn.login') }}', { method:'POST', headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ id:credential.id, rawId:this._buf(credential.rawId), type:credential.type, response:{ authenticatorData:this._buf(credential.response.authenticatorData), clientDataJSON:this._buf(credential.response.clientDataJSON), signature:this._buf(credential.response.signature), userHandle:credential.response.userHandle?this._buf(credential.response.userHandle):null } }) });
                const result = await verifyRes.json();
                if (verifyRes.ok) { this.biometricStatus = 'Berhasil! Mengalihkan...'; window.location.href = result.redirect || '/home'; }
                else { this.biometricStatus = 'Verifikasi gagal. Coba lagi.'; }
            } catch { this.biometricStatus = 'Biometrik tidak tersedia atau dibatalkan.'; }
        },
        _b64(s) { const b=atob(s.replace(/-/g,'+').replace(/_/g,'/')); return Uint8Array.from(b,c=>c.charCodeAt(0)).buffer; },
        _buf(b) { return btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,''); },
    };
}
</script>
</body>
</html>
