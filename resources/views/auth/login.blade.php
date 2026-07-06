<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — {{ $namaSekolah ?? 'Edutive' }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
        }
        
        /* Floating background blobs animation */
        @keyframes float-slow {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-20px) scale(1.08); }
        }
        @keyframes float-delayed {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(20px) scale(1.06); }
        }
        .blob-1 {
            animation: float-slow 8s ease-in-out infinite;
        }
        .blob-2 {
            animation: float-delayed 10s ease-in-out infinite;
        }
        
        /* Glassmorphism input focus state */
        .premium-input {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .premium-input:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.15);
            outline: none;
        }
        
        /* Keypad press animation */
        .key-btn {
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .key-btn:active {
            transform: scale(0.92);
            background-color: rgba(30, 58, 138, 0.1);
        }
        
        /* Shake animation for error */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        .shake {
            animation: shake 0.4s ease-in-out;
        }
        
        /* Gentle rise on load */
        @keyframes rise-up {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .rise-up {
            animation: rise-up 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }
        
        /* Pulse fingerprint */
        @keyframes fingerprint-pulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.06); opacity: 1; box-shadow: 0 0 20px rgba(30, 58, 138, 0.25); }
        }
        .pulse-fp {
            animation: fingerprint-pulse 2s infinite ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex overflow-x-hidden relative">

    {{-- 1. LEFT SIDE: Branding Panel (Visible only on Desktop) --}}
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-950 via-indigo-950 to-slate-900 relative items-center justify-center p-12 text-white overflow-hidden select-none">
        {{-- Mesh overlay & Glowing Blobs --}}
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:4rem_4rem]"></div>
        <div class="blob-1 absolute -top-40 -left-40 w-96 h-96 rounded-full bg-blue-600/20 filter blur-[90px]"></div>
        <div class="blob-2 absolute -bottom-40 -right-40 w-96 h-96 rounded-full bg-amber-500/15 filter blur-[90px]"></div>

        {{-- Content Container --}}
        <div class="relative z-10 max-w-md space-y-8">
            <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 grid place-items-center shadow-lg shadow-black/10 overflow-hidden">
                @if($sekolahLogoUrl)
                    <img src="{{ $sekolahLogoUrl }}" class="w-full h-full {{ in_array($sekolahLogoExt ?? 'png', ['jpg', 'jpeg']) ? 'object-contain' : 'object-cover' }}" alt="Logo">
                @else
                    <svg viewBox="0 0 24 24" fill="none" class="w-8 h-8 text-white" stroke="currentColor" stroke-width="2.2">
                        <path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                @endif
            </div>
            
            <div class="space-y-3">
                <h2 class="text-3xl font-extrabold tracking-tight leading-tight text-white">SIMS</h2>
                <p class="text-xs font-bold text-amber-400 uppercase tracking-widest -mt-1">Sistem Informasi Manajemen Sekolah</p>
                <p class="text-slate-300 text-sm leading-relaxed">Selamat datang di portal akademik dan manajemen terintegrasi. Akses data siswa, guru, rombongan belajar, jadwal pelajaran, presensi harian, dan administrasi sekolah secara real-time.</p>
            </div>

            <div class="space-y-4 pt-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/15 grid place-items-center text-amber-400">
                        <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold">Manajemen Akademik Terpadu</p>
                        <p class="text-xs text-slate-400">Pengelolaan data induk siswa, data guru, mata pelajaran, dan kelas dalam satu sistem.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/15 grid place-items-center text-blue-400">
                        <i data-lucide="map-pin" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold">Presensi Kelas & Geofencing GPS</p>
                        <p class="text-xs text-slate-400">Sistem kehadiran aman berbasis lokasi (GPS Geofence) dengan autentikasi ganda.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/15 grid place-items-center text-teal-400">
                        <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold">Laporan & Rekapitulasi Real-Time</p>
                        <p class="text-xs text-slate-400">Pemantauan statistik harian dan rekap data dinamis untuk memudahkan keputusan administrasi.</p>
                    </div>
                </div>
            </div>

            <p class="text-xs text-slate-400 pt-8 border-t border-white/10">Edutive — {{ $namaSekolah ?? 'Edutive' }} &copy; {{ date('Y') }}</p>
        </div>
    </div>

    {{-- 2. RIGHT SIDE: Login Card (100% on Mobile, 50% on Desktop) --}}
    <div class="w-full lg:w-1/2 min-h-screen flex flex-col items-center justify-center p-6 relative overflow-y-auto bg-slate-50">
        
        {{-- Background blobs for right side --}}
        <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] opacity-35"></div>
            <div class="blob-1 absolute -top-40 -right-40 w-96 h-96 rounded-full bg-blue-600/10 filter blur-[90px]"></div>
            <div class="blob-2 absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-amber-500/10 filter blur-[90px]"></div>
        </div>

        <div x-data="loginApp()" class="w-full max-w-[400px] z-10 rise-up">

            {{-- Mobile Logo/Header (Hidden on Desktop, shown on Mobile) --}}
            <div class="flex flex-col items-center mb-8 text-center lg:hidden">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-900 to-indigo-950 grid place-items-center mb-4 shadow-md shadow-blue-950/20 border border-blue-800/30 overflow-hidden">
                    @if($sekolahLogoUrl)
                        <img src="{{ $sekolahLogoUrl }}" class="w-full h-full {{ in_array($sekolahLogoExt ?? 'png', ['jpg', 'jpeg']) ? 'object-contain' : 'object-cover' }}" alt="Logo">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" class="w-7 h-7 text-white" stroke="currentColor" stroke-width="2.2">
                            <path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    @endif
                </div>
                <h1 class="text-xl font-black text-slate-800 tracking-tight">{{ $namaSekolah ?? 'Edutive' }}</h1>
                <p class="text-sm text-slate-500 mt-1">SIMS ( Sistem Informasi Manajemen Sekolah )</p>
            </div>

            {{-- Desktop Header (Hidden on Mobile, shown on Desktop) --}}
            <div class="hidden lg:block mb-6">
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Selamat Datang</h2>
                <p class="text-sm text-slate-500 mt-1">Silakan masuk menggunakan akun terdaftar Anda</p>
            </div>

            {{-- Main Glassmorphic Login Card --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/50 rounded-[28px] shadow-[0_20px_50px_rgba(15,23,42,0.06)] p-6 sm:p-8 relative overflow-hidden">
                
                {{-- Decorative card top line --}}
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500"></div>

                {{-- Alerts --}}
                @if($errors->any())
                <div class="mb-5 flex items-center gap-2.5 bg-rose-50 border border-rose-100 rounded-2xl px-4 py-3 text-sm text-rose-600">
                    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 text-rose-500"></i>
                    <span class="font-medium leading-normal">{{ $errors->first() }}</span>
                </div>
                @endif
                @if(session('success'))
                <div class="mb-5 flex items-center gap-2.5 bg-emerald-50 border border-emerald-100 rounded-2xl px-4 py-3 text-sm text-emerald-600">
                    <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0 text-emerald-500"></i>
                    <span class="font-medium leading-normal">{{ session('success') }}</span>
                </div>
                @endif

                {{-- Modern Tabs --}}
                <div class="flex bg-slate-200/60 p-1 rounded-2xl mb-6 border border-slate-300/20">
                    <button @click="tab='password'" :class="tab==='password' ? 'bg-white shadow-sm text-slate-800 font-bold' : 'text-slate-500 hover:text-slate-800'"
                            class="flex-1 py-2 rounded-xl text-xs font-semibold transition-all duration-200 flex items-center justify-center gap-1.5">
                        <i data-lucide="key" class="w-3.5 h-3.5"></i> Password
                    </button>
                    <button @click="tab='pin'" :class="tab==='pin' ? 'bg-white shadow-sm text-slate-800 font-bold' : 'text-slate-500 hover:text-slate-800'"
                            class="flex-1 py-2 rounded-xl text-xs font-semibold transition-all duration-200 flex items-center justify-center gap-1.5">
                        <i data-lucide="grid" class="w-3.5 h-3.5"></i> PIN
                    </button>
                    {{-- Biometric button disabled --}}
                </div>

                {{-- 1. Tab Password --}}
                <div x-show="tab==='password'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Username / NIK / NIS</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                </span>
                                <input type="text" name="credential" x-model="pinCredential" required autofocus placeholder="Masukkan username atau nomor induk"
                                       class="premium-input w-full border border-slate-200 rounded-2xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 bg-white">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Password</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i data-lucide="lock" class="w-4 h-4"></i>
                                </span>
                                <input :type="showPass ? 'text' : 'password'" name="password" required placeholder="Masukkan password Anda"
                                       class="premium-input w-full border border-slate-200 rounded-2xl pl-10 pr-10 py-3 text-sm text-slate-800 placeholder-slate-400 bg-white">
                                <button type="button" @click="showPass=!showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                                    <i x-show="!showPass" data-lucide="eye" class="w-4 h-4"></i>
                                    <i x-show="showPass" data-lucide="eye-off" class="w-4 h-4" x-cloak></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex items-center pt-1">
                            <label class="flex items-center gap-2 text-sm text-slate-500 cursor-pointer select-none">
                                <input type="checkbox" name="remember" class="w-4 h-4 rounded-lg border-slate-300 text-blue-900 focus:ring-blue-900/10"> 
                                <span>Ingat saya</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="w-full py-3 rounded-2xl bg-blue-900 hover:bg-blue-800 active:scale-[0.99] text-white text-sm font-bold transition-all shadow-md shadow-blue-900/15 flex items-center justify-center gap-2">
                            <span>Masuk ke Akun</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>

                {{-- 2. Tab PIN --}}
                <div x-show="tab==='pin'" 
                     @keydown.window="if(tab==='pin') handleKeyboard($event)"
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Username / NIK / NIS</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                </span>
                                <input type="text" x-model="pinCredential" @keydown.enter="document.activeElement.blur()" placeholder="Masukkan username atau nomor induk"
                                       class="premium-input w-full border border-slate-200 rounded-2xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 bg-white">
                            </div>
                        </div>
                        
                        <div class="py-2 text-center bg-slate-50 border border-slate-100 rounded-2xl p-4">
                            <p class="text-xs font-semibold text-slate-400 mb-3" x-text="pinLoading ? 'MEMVERIFIKASI PIN...' : 'MASUKKAN PIN 6 DIGIT'"></p>
                            <div class="flex justify-center gap-3.5" :class="{ shake: pinError }">
                                <template x-for="i in 6">
                                    <div :class="pin.length>=i ? 'bg-blue-900 scale-110 border-blue-900 shadow-sm shadow-blue-900/20' : 'bg-white border-slate-300'" 
                                         class="w-4 h-4 rounded-full border-2 transition-all duration-150"
                                         :style="pinLoading ? 'animation: fingerprint-pulse 1s infinite ' + (i*0.1) + 's' : ''"></div>
                                </template>
                            </div>
                            <p x-show="pinError" class="text-rose-500 font-semibold text-xs mt-3 flex items-center justify-center gap-1" x-cloak>
                                <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> PIN salah atau akun tidak ditemukan
                            </p>
                        </div>
                        
                        {{-- Numeric Keypad --}}
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="btn in ['1','2','3','4','5','6','7','8','9','','0','⌫']">
                                <button @click="pinPress(btn)" :disabled="pinLoading"
                                        :class="btn==='' ? 'opacity-0 pointer-events-none' : (btn==='⌫' ? 'bg-slate-100 hover:bg-slate-200 text-slate-500' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold')"
                                        class="key-btn rounded-2xl py-3.5 text-lg transition-all border border-slate-100 shadow-sm flex items-center justify-center disabled:opacity-50 disabled:pointer-events-none">
                                    <template x-if="btn==='⌫'">
                                        <i data-lucide="delete" class="w-5 h-5"></i>
                                    </template>
                                    <template x-if="btn!=='⌫'">
                                        <span x-text="btn"></span>
                                    </template>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- 3. Tab Biometrik (Disabled) --}}

                {{-- 4. Tab Lupa Password --}}
                <div x-show="tab==='forgot'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak>
                    <form method="POST" action="{{ route('password.request') }}" class="space-y-4">
                        @csrf
                        <div>
                            <h3 class="text-sm font-bold text-slate-700 mb-1">Minta Reset Password</h3>
                            <p class="text-xs text-slate-400 leading-normal mb-3">Masukkan username atau nomor induk (NIK/NIS) Anda. Permintaan pemulihan akan langsung diteruskan ke Admin sekolah.</p>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </span>
                            <input type="text" name="credential" placeholder="Username / NIK / NIS" required
                                   class="premium-input w-full border border-slate-200 rounded-2xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 bg-white">
                        </div>
                        <button type="submit" class="w-full py-3 rounded-2xl bg-blue-900 hover:bg-blue-800 text-white text-sm font-bold transition shadow-md shadow-blue-900/15">
                            Kirim Permintaan Reset
                        </button>
                        <button type="button" @click="tab='password'" class="w-full text-center text-xs font-bold text-slate-400 hover:text-slate-600 py-1 transition flex items-center justify-center gap-1">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Form Password
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-slate-400 mt-8 font-medium lg:hidden">&copy; {{ date('Y') }} {{ $namaSekolah ?? 'Edutive' }} • Hak Cipta Dilindungi</p>
        </div>
    </div>

    <script>
    function loginApp() {
        return {
            tab: 'password', showPass: false, pin: '', 
            pinCredential: @json(old('credential')) || localStorage.getItem('last_credential') || '', 
            pinError: false, pinLoading: false,
            biometricAvailable: false, biometricStatus: 'Ketuk untuk verifikasi biometrik',
            async init() {
                if (window.PublicKeyCredential) {
                    this.biometricAvailable = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
                }
                this.$nextTick(() => {
                    if (window.lucide) lucide.createIcons();
                });
                this.$watch('tab', () => {
                    this.$nextTick(() => {
                        if (window.lucide) lucide.createIcons();
                    });
                });
                this.$watch('pinCredential', (val) => {
                    localStorage.setItem('last_credential', val);
                });
            },
            handleKeyboard(e) {
                if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) {
                    return;
                }
                const key = e.key;
                if (key >= '0' && key <= '9') {
                    e.preventDefault();
                    this.pinPress(key);
                } else if (key === 'Backspace') {
                    e.preventDefault();
                    this.pinPress('⌫');
                }
            },
            pinPress(btn) {
                if (btn === '⌫') { this.pin = this.pin.slice(0, -1); this.pinError = false; }
                else if (btn !== '' && this.pin.length < 6) { this.pin += btn; if (this.pin.length === 6) this.submitPin(); }
            },
            async submitPin() {
                if (!this.pinCredential) { this.pinError = true; this.pin = ''; return; }
                this.pinLoading = true;
                try {
                    const res = await fetch('{{ route('login.pin') }}', { method:'POST', headers:{ 'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ credential:this.pinCredential, pin:this.pin }) });
                    const data = await res.json();
                    if (res.ok) { window.location.href = data.redirect || '/home'; } else { this.pinError = true; this.pin = ''; this.pinLoading = false; }
                } catch { this.pinError = true; this.pin = ''; this.pinLoading = false; }
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
