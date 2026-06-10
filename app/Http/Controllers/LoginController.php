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
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password lama salah.']);
        }

        $user->update(['password' => $request->new_password]);

        return back()->with('success', 'Password berhasil diubah.');
    }

    public function changePinPage()
    {
        return view('auth.change-pin');
    }

    public function changePin(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'pin'      => 'required|digits:6|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }

        $user->update(['pin' => Hash::make($request->pin)]);

        return back()->with('success', 'PIN berhasil diset.');
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

        $token = Str::random(40);
        $user->update(['reset_token' => $token]);

        // Token dikirim ke admin untuk diinformasikan ke user
        return back()->with('success', 'Permintaan reset dikirim ke admin.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function redirectAfterLogin(User $user)
    {
        return redirect($this->getRedirectUrl($user));
    }

    private function getRedirectUrl(User $user): string
    {
        return match ($user->access) {
            'superadmin', 'admin', 'kurikulum', 'kesiswaan', 'sapras', 'kepala' => route('dashboard'),
            'guru'      => route('dashboard'),
            'walikelas' => route('dashboard'),
            'siswa'     => route('dashboard'),
            'ortu'      => route('dashboard'),
            default     => route('dashboard'),
        };
    }
}
