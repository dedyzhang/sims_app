<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load(['guru', 'siswa']);
        return view('profile.index', compact('user'));
    }

    public function edit()
    {
        $user = auth()->user()->load(['guru', 'siswa']);
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'username' => 'required|string|min:4|max:50|regex:/^[a-zA-Z0-9_.]+$/|unique:users,username,' . $user->uuid . ',uuid',
        ];

        // Guru boleh mengedit data dirinya sendiri
        if ($user->guru) {
            $rules += [
                'nama'          => 'required|string|max:100',
                'nik'           => 'nullable|string|max:30|unique:gurus,nik,' . $user->guru->uuid . ',uuid',
                'nip'           => 'nullable|string|max:30',
                'jk'            => 'required|in:L,P',
                'tempat_lahir'  => 'nullable|string|max:100',
                'tanggal_lahir' => 'nullable|date',
                'agama'         => 'nullable|string|max:30',
                'alamat'        => 'nullable|string',
                'no_telp'       => 'nullable|string|max:20',
                'tingkat_studi' => 'nullable|string|max:30',
                'program_studi' => 'nullable|string|max:100',
                'universitas'   => 'nullable|string|max:100',
                'tahun_tamat'   => 'nullable|string|max:10',
                'tanda_tangan'  => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
                'hapus_tanda_tangan' => 'nullable|boolean',
            ];
        }

        $data = $request->validate($rules, [
            'username.regex'  => 'Username hanya boleh huruf, angka, titik (.), dan underscore (_).',
            'username.unique' => 'Username sudah dipakai pengguna lain.',
            'nik.unique'      => 'NIK tersebut sudah digunakan guru lain.',
        ]);

        $user->update([
            'username' => $data['username'],
        ]);

        if ($user->guru) {
            $guruData = collect($data)->only([
                'nama', 'nik', 'nip', 'jk', 'tempat_lahir', 'tanggal_lahir',
                'agama', 'alamat', 'no_telp', 'tingkat_studi', 'program_studi',
                'universitas', 'tahun_tamat',
            ])->toArray();

            if ($request->hasFile('tanda_tangan')) {
                $old = $user->guru->tanda_tangan;
                if ($old && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
                $ext = Uploads::safeExtension($request->file('tanda_tangan'), ['png', 'jpg', 'jpeg', 'webp'], 'png');
                $guruData['tanda_tangan'] = $request->file('tanda_tangan')->storeAs('ttd', 'ttd_guru_'.$user->guru->uuid.'_'.now()->format('YmdHis').'.'.$ext, 'public');
            } elseif ($request->boolean('hapus_tanda_tangan')) {
                $old = $user->guru->tanda_tangan;
                if ($old && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
                $guruData['tanda_tangan'] = null;
            }

            $user->guru->update($guruData);

            // sinkronkan identifier login dengan NIK/NIP
            $idf = ($guruData['nik'] ?? null) ?: ($guruData['nip'] ?? null);
            if ($idf) $user->update(['identifier' => $idf]);
        }

        return redirect()->route('profile.index')->with('success', 'Profil diperbarui.');
    }

    // ---- Preferensi / Tema ----

    public function preferenceEdit()
    {
        $pref = auth()->user()->preference()->firstOrCreate(
            ['user_uuid' => auth()->id()],
            UserPreference::defaults()
        );
        return view('profile.preference', compact('pref'));
    }

    public function preferenceUpdate(Request $request)
    {
        $data = $request->validate([
            'primary_color'    => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color'  => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color'     => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_style'    => 'required|in:default,compact,icon-only',
            'sidebar_bg'       => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_text'     => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_mode'       => 'required|in:light,dark',
            'motif'            => 'nullable|in:botanical,ocean,forest,sunset,robot,space,minimal,nightocean,rainbow',
            'ui_style'         => 'nullable|in:soft,corporate',
            'dashboard_theme'  => 'nullable|in:windows11,macos',
            'font_size'        => 'required|in:sm,md,lg',
            'compact_mode'     => 'boolean',
        ]);

        $data['motif'] = $data['motif'] ?? 'botanical';
        $data['ui_style'] = $data['ui_style'] ?? 'soft';
        $data['dashboard_theme'] = $data['dashboard_theme'] ?? 'windows11';

        $data['compact_mode'] = $request->boolean('compact_mode');

        auth()->user()->preference()->updateOrCreate(
            ['user_uuid' => auth()->id()],
            $data
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Tampilan berhasil disimpan.']);
        }

        return back()->with('success', 'Tampilan berhasil diperbarui.');
    }

    public function setStyle(Request $request)
    {
        $request->validate(['ui_style' => 'required|in:soft,corporate']);
        auth()->user()->preference()->updateOrCreate(
            ['user_uuid' => auth()->id()],
            ['ui_style' => $request->ui_style]
        );
        return response()->json(['success' => true, 'ui_style' => $request->ui_style]);
    }

    public function preferenceReset()
    {
        auth()->user()->preference()->updateOrCreate(
            ['user_uuid' => auth()->id()],
            UserPreference::defaults()
        );

        return back()->with('success', 'Tampilan direset ke default.');
    }
}
