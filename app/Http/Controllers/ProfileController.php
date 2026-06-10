<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;

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
        $request->validate([
            'username' => 'required|string|unique:users,username,' . auth()->id() . ',uuid',
        ]);

        auth()->user()->update(['username' => $request->username]);

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
            'motif'            => 'nullable|in:botanical,ocean,forest,sunset,robot,space,minimal',
            'ui_style'         => 'nullable|in:soft,corporate',
            'font_size'        => 'required|in:sm,md,lg',
            'compact_mode'     => 'boolean',
        ]);

        $data['motif'] = $data['motif'] ?? 'botanical';
        $data['ui_style'] = $data['ui_style'] ?? 'soft';

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
