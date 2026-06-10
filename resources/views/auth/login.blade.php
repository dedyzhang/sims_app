<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — Edu Nusantara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Override "indigo" → sage botanical palette agar seluruh markup login otomatis hangat
        tailwind.config = { theme: { extend: { colors: { indigo: {
            50:'#eef4ef',100:'#dcebe0',200:'#c3dac9',300:'#9db89f',400:'#88aa8d',
            500:'#7ba088',600:'#6b9080',700:'#5a7d6e',800:'#496656',900:'#3d5a48'
        } } } } }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans','Inter', sans-serif; }
        .brand-bg {
            background: linear-gradient(150deg, #3d5a48 0%, #5a7d6e 38%, #7ba088 72%, #9db89f 100%);
        }
        .brand-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .floating-card {
            animation: floatUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        @keyframes floatUp {
            to { opacity: 1; transform: translateY(0); }
        }
        .tab-pill {
            transition: all 0.2s ease;
        }
        .input-field {
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-field:focus {
            border-color: #7ba088;
            box-shadow: 0 0 0 3px rgba(123,160,136,0.18);
            outline: none;
        }
        .pin-dot {
            transition: all 0.15s ease;
        }
        .pin-btn {
            transition: all 0.1s ease;
        }
        .pin-btn:active {
            transform: scale(0.93);
            background: #dcebe0;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)}
            20%{transform:translateX(-10px)}
            40%{transform:translateX(10px)}
            60%{transform:translateX(-6px)}
            80%{transform:translateX(6px)}
        }
        .shake { animation: shake 0.5s ease; }
        .feature-item {
            animation: slideIn 0.5s ease forwards;
            opacity: 0;
        }
        @keyframes slideIn {
            from { opacity:0; transform:translateX(-20px); }
            to   { opacity:1; transform:translateX(0); }
        }
        .feature-item:nth-child(1) { animation-delay: 0.2s; }
        .feature-item:nth-child(2) { animation-delay: 0.35s; }
        .feature-item:nth-child(3) { animation-delay: 0.5s; }
        .feature-item:nth-child(4) { animation-delay: 0.65s; }
    </style>
</head>
<body class="min-h-screen lg:flex" style="background:linear-gradient(135deg,#fdf4ec 0%,#fbeadb 45%,#f5f1ea 100%)">

    {{-- ===== LEFT BRANDING PANEL ===== --}}
    <div class="brand-bg relative hidden lg:flex lg:w-[45%] flex-col justify-between p-12 overflow-hidden">
        {{-- Botanical decorations --}}
        <div class="absolute -right-8 -top-8 opacity-40">@include('partials.flower', ['s'=>160,'c1'=>'#ffffff','c2'=>'#ffffff','o'=>'.5'])</div>
        <div class="absolute -left-10 bottom-16 opacity-30">@include('partials.flower', ['s'=>120,'c1'=>'#ffffff','c2'=>'#ffffff','o'=>'.5'])</div>
        <div class="absolute right-12 bottom-6 opacity-25">@include('partials.leaf', ['s'=>90,'c'=>'#ffffff','o'=>'.6'])</div>

        {{-- Logo --}}
        <div class="flex items-center gap-3 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-white" stroke="currentColor" stroke-width="2">
                    <path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <span class="text-white font-bold text-xl">Edu Nusantara</span>
        </div>

        {{-- Main Text --}}
        <div class="relative z-10">
            <h1 class="text-4xl font-extrabold text-white leading-tight mb-4">
                Sistem Manajemen<br>
                <span class="text-amber-200">Sekolah Modern</span>
            </h1>
            <p class="text-white/60 text-lg mb-10">Kelola seluruh kegiatan akademik sekolah Anda dengan mudah, cepat, dan terorganisir.</p>

            <div class="space-y-4">
                @foreach([
                    ['📊','Dashboard Interaktif','Pantau semua data sekolah dalam satu tampilan'],
                    ['👥','Manajemen Guru & Siswa','Data lengkap dengan akun login otomatis'],
                    ['📚','Data Akademik','Jadwal, nilai, dan absensi terpusat'],
                    ['🔐','Multi-Metode Login','Password, PIN, dan biometrik'],
                ] as [$icon, $title, $desc])
                <div class="feature-item flex items-start gap-3 bg-white/8 rounded-xl p-3 backdrop-blur-sm border border-white/10">
                    <span class="text-2xl">{{ $icon }}</span>
                    <div>
                        <p class="text-white font-semibold text-sm">{{ $title }}</p>
                        <p class="text-white/50 text-xs mt-0.5">{{ $desc }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <p class="text-white/30 text-sm relative z-10">&copy; {{ date('Y') }} Edu Nusantara. All rights reserved.</p>
    </div>

    {{-- ===== RIGHT LOGIN PANEL ===== --}}
    <div class="flex-1 flex items-center justify-center p-6 lg:p-12">
        <div class="w-full max-w-md floating-card" x-data="loginApp()">

            {{-- Mobile logo --}}
            <div class="lg:hidden text-center mb-8">
                <div class="inline-flex items-center gap-2 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center">
                        <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-white" stroke="currentColor" stroke-width="2">
                            <path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="font-bold text-xl text-gray-800">Edu Nusantara</span>
                </div>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-1">Selamat Datang</h2>
            <p class="text-gray-500 text-sm mb-7">Silakan masuk ke akun Anda</p>

            {{-- Alerts --}}
            @if($errors->any())
            <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ $errors->first() }}
            </div>
            @endif
            @if(session('success'))
            <div class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm text-green-700">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
            @endif

            {{-- Tab Selector --}}
            <div class="flex bg-gray-100 rounded-xl p-1 mb-6 gap-1">
                <button @click="tab='password'"
                        :class="tab==='password' ? 'bg-white shadow-sm text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="tab-pill flex-1 py-2 rounded-lg text-sm transition">
                    🔑 Password
                </button>
                <button @click="tab='pin'"
                        :class="tab==='pin' ? 'bg-white shadow-sm text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="tab-pill flex-1 py-2 rounded-lg text-sm transition">
                    🔢 PIN
                </button>
                <button @click="tab='biometric'; tryBiometric()"
                        :class="tab==='biometric' ? 'bg-white shadow-sm text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="tab-pill flex-1 py-2 rounded-lg text-sm transition"
                        x-show="biometricAvailable">
                    👆 Biometrik
                </button>
            </div>

            {{-- ===== PASSWORD TAB ===== --}}
            <div x-show="tab==='password'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Username / NIK / NIS</label>
                        <div class="relative">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <input type="text" name="credential" value="{{ old('credential') }}" required autofocus
                                   placeholder="Masukkan username, NIK, atau NIS"
                                   class="input-field w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm bg-white text-gray-800 placeholder-gray-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <input :type="showPass ? 'text' : 'password'" name="password" required
                                   placeholder="Password"
                                   class="input-field w-full border border-gray-200 rounded-xl pl-10 pr-12 py-3 text-sm bg-white text-gray-800 placeholder-gray-400">
                            <button type="button" @click="showPass = !showPass"
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-indigo-600"> Ingat saya
                        </label>
                        <button type="button" @click="tab='forgot'" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition">Lupa password?</button>
                    </div>
                    <button type="submit"
                            class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm transition shadow-sm shadow-indigo-200">
                        Masuk ke Akun
                    </button>
                </form>
            </div>

            {{-- ===== PIN TAB ===== --}}
            <div x-show="tab==='pin'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Username / NIK / NIS</label>
                        <input type="text" x-model="pinCredential" placeholder="Masukkan username / NIK / NIS"
                               class="input-field w-full border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400">
                    </div>

                    {{-- PIN Dots --}}
                    <div class="py-4">
                        <p class="text-center text-xs text-gray-500 mb-3">Masukkan PIN 6 digit</p>
                        <div class="flex justify-center gap-3" :class="{ shake: pinError }">
                            <template x-for="i in 6">
                                <div :class="pin.length >= i ? 'bg-indigo-600 scale-110 border-indigo-600' : 'bg-white border-gray-300'"
                                     class="pin-dot w-4 h-4 rounded-full border-2 transition-all"></div>
                            </template>
                        </div>
                        <p x-show="pinError" class="text-center text-red-500 text-xs mt-2">PIN salah atau akun tidak ditemukan</p>
                    </div>

                    {{-- Numpad --}}
                    <div class="bg-gray-50 rounded-2xl p-4">
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="btn in ['1','2','3','4','5','6','7','8','9','','0','⌫']">
                                <button @click="pinPress(btn)"
                                        :class="btn === '' ? 'invisible' : 'bg-white hover:bg-indigo-50 hover:text-indigo-700 text-gray-700 shadow-sm'"
                                        class="pin-btn rounded-xl py-3.5 text-lg font-semibold transition border border-gray-100">
                                    <span x-text="btn"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== BIOMETRIK TAB ===== --}}
            <div x-show="tab==='biometric'" x-transition>
                <div class="text-center py-6 space-y-5">
                    <div @click="tryBiometric()"
                         class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-indigo-50 border-2 border-indigo-200 cursor-pointer hover:bg-indigo-100 hover:border-indigo-400 transition mx-auto group">
                        <svg class="w-12 h-12 text-indigo-500 group-hover:scale-110 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-700" x-text="biometricStatus">Ketuk untuk verifikasi biometrik</p>
                        <p class="text-xs text-gray-400 mt-1">Fingerprint atau Face ID</p>
                    </div>
                    <button @click="tab='password'" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition">Gunakan password saja</button>
                </div>
            </div>

            {{-- ===== LUPA PASSWORD ===== --}}
            <div x-show="tab==='forgot'" x-transition>
                <div class="space-y-4">
                    <div class="text-center mb-2">
                        <p class="text-sm text-gray-600">Masukkan username atau NIK Anda. Permintaan reset akan diteruskan ke admin.</p>
                    </div>
                    <form method="POST" action="{{ route('password.request') }}" class="space-y-4">
                        @csrf
                        <input type="text" name="credential" placeholder="Username / NIK / NIS" required
                               class="input-field w-full border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400">
                        <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm transition">
                            Kirim Permintaan Reset
                        </button>
                    </form>
                    <button type="button" @click="tab='password'" class="w-full text-center text-gray-500 hover:text-gray-700 text-sm transition">
                        ← Kembali ke login
                    </button>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 mt-8 lg:hidden">&copy; {{ date('Y') }} Edu Nusantara</p>
        </div>
    </div>

<script>
function loginApp() {
    return {
        tab: 'password',
        showPass: false,
        pin: '',
        pinCredential: '',
        pinError: false,
        biometricAvailable: false,
        biometricStatus: 'Ketuk untuk verifikasi biometrik',

        async init() {
            if (window.PublicKeyCredential) {
                const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
                this.biometricAvailable = available;
            }
        },

        pinPress(btn) {
            if (btn === '⌫') {
                this.pin = this.pin.slice(0, -1);
                this.pinError = false;
            } else if (btn !== '' && this.pin.length < 6) {
                this.pin += btn;
                if (this.pin.length === 6) this.submitPin();
            }
        },

        async submitPin() {
            if (!this.pinCredential) { this.pinError = true; this.pin = ''; return; }
            try {
                const res = await fetch('{{ route('login.pin') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ credential: this.pinCredential, pin: this.pin })
                });
                const data = await res.json();
                if (res.ok) { window.location.href = data.redirect || '/home'; }
                else { this.pinError = true; this.pin = ''; }
            } catch { this.pinError = true; this.pin = ''; }
        },

        async tryBiometric() {
            this.biometricStatus = 'Menunggu verifikasi...';
            try {
                const optRes = await fetch('{{ route('webauthn.login.options') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({})
                });
                const options = await optRes.json();
                options.challenge = this._b64(options.challenge);
                if (options.allowCredentials) options.allowCredentials = options.allowCredentials.map(c => ({...c, id: this._b64(c.id)}));
                const credential = await navigator.credentials.get({ publicKey: options });
                const verifyRes = await fetch('{{ route('webauthn.login') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ id: credential.id, rawId: this._buf(credential.rawId), type: credential.type,
                        response: { authenticatorData: this._buf(credential.response.authenticatorData),
                            clientDataJSON: this._buf(credential.response.clientDataJSON),
                            signature: this._buf(credential.response.signature),
                            userHandle: credential.response.userHandle ? this._buf(credential.response.userHandle) : null }})
                });
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
