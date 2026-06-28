<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Login dengan username / NIK / NIP / NIS
     * Superadmin login seperti biasa tapi tidak pernah ditampilkan di UI daftar user.
     */
    public function login(Request $request)
    {
        $request->validate([
            'credential' => 'required|string',
            'password'   => 'required|string',
        ], [
            'credential.required' => 'Username / NIK / NIS wajib diisi.',
            'password.required'   => 'Password wajib diisi.',
        ]);

        $credential = trim($request->credential);

        // Cari user berdasarkan username ATAU identifier (NIK/NIP/NIS)
        $user = User::where('username', $credential)
            ->orWhere('identifier', $credential)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['credential' => 'Username / NIK / NIS atau password salah.'])->withInput(['credential' => $credential]);
        }

        Auth::login($user, $request->boolean('remember'));

        return $this->redirectAfterLogin($user);
    }

    /**
     * Login dengan PIN (untuk mobile)
     */
    public function loginPin(Request $request)
    {
        $request->validate([
            'credential' => 'required|string',
            'pin'        => 'required|digits:6',
        ]);

        $user = User::where('username', $request->credential)
            ->orWhere('identifier', $request->credential)
            ->first();

        if (!$user || !$user->pin || !Hash::check($request->pin, $user->pin)) {
            return response()->json(['message' => 'Kredensial atau PIN salah.'], 401);
        }

        Auth::login($user);

        return response()->json([
            'message'  => 'Login berhasil.',
            'redirect' => $this->getRedirectUrl($user),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function home()
    {
        return $this->redirectAfterLogin(auth()->user());
    }

    public function changePasswordPage()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();

        if ($user->must_change_password && !$user->username_customized) {
            return redirect()->route('ganti.password')->with('error', 'Silakan kustomisasi username Anda terlebih dahulu.');
        }

        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password lama salah.']);
        }

        $user->update([
            'password'             => $request->new_password,
            'must_change_password' => false,
        ]);

        // Refresh session auth to update the password hash in the session
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Password berhasil diperbarui.');
    }

    public function changeUsername(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'username' => [
                'required',
                'string',
                'min:4',
                'max:50',
                'unique:users,username,' . $user->uuid . ',uuid',
                'regex:/^[a-zA-Z0-9_.]+$/',
                function ($attribute, $value, $fail) use ($user) {
                    if (strtolower(trim($value)) === strtolower($user->username)) {
                        $fail('Anda wajib mengganti username bawaan sistem dengan username kustom Anda sendiri.');
                    }
                }
            ],
        ], [
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan pengguna lain. Pilih username lain.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik (.), dan underscore (_).',
            'username.min' => 'Username minimal terdiri dari 4 karakter.',
        ]);

        $user->update([
            'username' => trim($request->username),
            'username_customized' => true,
        ]);

        return redirect()->route('ganti.password')->with('success', 'Username berhasil diperbarui. Silakan ubah password, set PIN, atau aktifkan biometrik Anda.');
    }

    public function changePinPage()
    {
        return view('auth.change-pin');
    }

    public function changePin(Request $request)
    {
        $user = auth()->user();

        if ($user->must_change_password && !$user->username_customized) {
            return redirect()->route('ganti.password')->with('error', 'Silakan kustomisasi username Anda terlebih dahulu.');
        }

        $request->validate([
            'password' => 'required',
            'pin'      => 'required|digits:6|confirmed',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }

        $user->update([
            'pin' => Hash::make($request->pin),
            'must_change_password' => false,
        ]);

        // Refresh session auth to update the session state
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'PIN berhasil diset.');
    }

    public function requestResetPassword(Request $request)
    {
        $request->validate(['credential' => 'required']);

        $user = User::where('username', $request->credential)
            ->orWhere('identifier', $request->credential)
            ->first();

        if (!$user) {
            return back()->withErrors(['credential' => 'Akun tidak ditemukan.']);
        }

        // Token disimpan ter-hash (selaras konvensi Laravel) agar bocoran DB
        // tidak mengekspos token yang bisa dipakai ulang.
        // CATATAN: saat ini token hanya penanda "ada permintaan reset" — belum
        // ada alur self-service yang memverifikasinya; reset password aktual
        // dilakukan admin (siswa.reset / guru.reset). Bila kelak dibuat alur
        // self-service, tambahkan kolom kedaluwarsa (reset_token_expires_at).
        $token = Str::random(40);
        $user->update(['reset_token' => Hash::make($token)]);

        return back()->with('success', 'Permintaan reset dikirim ke admin.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function redirectAfterLogin(User $user)
    {
        if ($user->must_change_password) {
            return redirect()->route('ganti.password')->with('warning', 'Demi keamanan, silakan ubah password bawaan atau password yang baru saja direset.');
        }
        return redirect($this->getRedirectUrl($user));
    }

    /**
     * Tujuan setelah login. Saat ini semua role diarahkan ke dashboard;
     * method ini dipertahankan sebagai satu titik ubah bila nanti perlu
     * landing page berbeda per role.
     */
    private function getRedirectUrl(User $user): string
    {
        return route('dashboard');
    }
}
