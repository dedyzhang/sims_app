<?php

namespace App\Http\Controllers;

use App\Models\Pengumuman;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/*
| Pengumuman sekolah. Semua user yang login bisa melihat riwayat pengumuman
| yang menyasar perannya; membuat/mengubah/menghapus butuh izin RBAC
| 'manage_pengumuman' (dijaga middleware di route + double-check di sini).
*/
class PengumumanController extends Controller
{
    private function bolehKelola(): bool
    {
        return auth()->user()?->canAccess('manage_pengumuman') ?? false;
    }

    /** Riwayat pengumuman (untuk semua user, terfilter per peran). Admin lihat semua. */
    public function index(Request $request)
    {
        $user = $request->user();
        $kelola = $this->bolehKelola();

        $query = Pengumuman::with('pembuat')->latest();

        // Pengelola melihat SEMUA pengumuman (termasuk yang bertarget peran lain);
        // user biasa hanya yang menyasar perannya atau ditujukan ke semua.
        if (! $kelola) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('target_roles')
                  ->orWhereJsonContains('target_roles', (string) $user->access);
            });
        }

        $pengumuman = $query->paginate(15);

        return view('pengumuman.index', [
            'pengumuman' => $pengumuman,
            'bolehKelola' => $kelola,
        ]);
    }

    public function show(Request $request, Pengumuman $pengumuman)
    {
        // User biasa tak boleh membuka pengumuman yang bukan sasarannya.
        if (! $this->bolehKelola() && ! $pengumuman->menyasar($request->user())) {
            abort(404);
        }

        // Tandai notifikasi pengumuman ini sebagai sudah dibaca → badge sidebar turun.
        $request->user()->unreadNotifications()
            ->where('data->pengumuman_id', $pengumuman->uuid)
            ->get()
            ->markAsRead();

        $pengumuman->load('pembuat');

        return view('pengumuman.show', [
            'pengumuman' => $pengumuman,
            'bolehKelola' => $this->bolehKelola(),
        ]);
    }

    public function create()
    {
        return view('pengumuman.form', [
            'pengumuman' => new Pengumuman(),
            'targetRoles' => Pengumuman::TARGET_ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $pengumuman = Pengumuman::create([
            'judul'        => $data['judul'],
            'isi'          => $data['isi'],
            'target_roles' => $data['target_roles'],
            'created_by'   => $request->user()->uuid,
        ]);

        $this->broadcastNotifikasi($pengumuman);

        return redirect()
            ->route('pengumuman.show', $pengumuman)
            ->with('success', 'Pengumuman diterbitkan & notifikasi dikirim.');
    }

    public function edit(Pengumuman $pengumuman)
    {
        return view('pengumuman.form', [
            'pengumuman' => $pengumuman,
            'targetRoles' => Pengumuman::TARGET_ROLES,
        ]);
    }

    public function update(Request $request, Pengumuman $pengumuman)
    {
        $data = $this->validated($request);

        $pengumuman->update([
            'judul'        => $data['judul'],
            'isi'          => $data['isi'],
            'target_roles' => $data['target_roles'],
        ]);

        // Sengaja TIDAK mengirim ulang notifikasi saat edit — hindari spam;
        // notifikasi hanya dikirim saat pengumuman pertama kali diterbitkan.
        return redirect()
            ->route('pengumuman.show', $pengumuman)
            ->with('success', 'Pengumuman diperbarui.');
    }

    public function destroy(Pengumuman $pengumuman)
    {
        $pengumuman->delete();

        return redirect()
            ->route('pengumuman.index')
            ->with('success', 'Pengumuman dihapus.');
    }

    /** Validasi + normalisasi target_roles (kosong / "semua" → null). */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'judul'          => 'required|string|max:150',
            'isi'            => 'required|string|max:20000',
            'target_roles'   => 'nullable|array',
            'target_roles.*' => 'string|in:'.implode(',', array_keys(Pengumuman::TARGET_ROLES)),
        ]);

        $roles = array_values(array_unique($validated['target_roles'] ?? []));

        return [
            'judul'        => $validated['judul'],
            'isi'          => $validated['isi'],
            'target_roles' => empty($roles) ? null : $roles,
        ];
    }

    /** Kirim notifikasi database + FCM ke user sasaran, di-chunk agar hemat memori. */
    private function broadcastNotifikasi(Pengumuman $pengumuman): void
    {
        $pengumuman->penerima()
            ->select('uuid')
            ->chunkById(500, function ($users) use ($pengumuman) {
                Notification::send($users, new PengumumanBaru($pengumuman));
            }, 'uuid');
    }
}
