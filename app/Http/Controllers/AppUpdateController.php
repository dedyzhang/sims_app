<?php

namespace App\Http\Controllers;

use App\Models\AppUpdate;
use App\Support\RichText;
use Illuminate\Http\Request;

/** Pengumuman "apa yang baru" per versi — dikelola admin, ditampilkan sbg popup ke semua user. */
class AppUpdateController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Hanya admin yang dapat mengelola info pembaruan.');
    }

    public function index()
    {
        $this->guard();

        $updates = AppUpdate::orderByDesc('released_at')
            ->orderByDesc('created_at')
            ->get();

        return view('pembaruan.index', compact('updates'));
    }

    public function create()
    {
        $this->guard();
        $update = new AppUpdate(['released_at' => now()->toDateString(), 'is_published' => true]);

        return view('pembaruan.form', ['update' => $update]);
    }

    public function store(Request $request)
    {
        $this->guard();
        $data = $this->validated($request);

        AppUpdate::create([
            'version'      => $data['version'],
            'title'        => $data['title'],
            'content'      => $data['content'],
            'released_at'  => $data['released_at'],
            'is_published' => $data['is_published'],
            'created_by'   => auth()->id(),
        ]);

        return redirect()->route('pembaruan.index')->with('success', 'Info pembaruan berhasil dibuat.');
    }

    public function edit(AppUpdate $update)
    {
        $this->guard();

        return view('pembaruan.form', ['update' => $update]);
    }

    public function update(Request $request, AppUpdate $update)
    {
        $this->guard();
        $data = $this->validated($request);

        $update->update([
            'version'      => $data['version'],
            'title'        => $data['title'],
            'content'      => $data['content'],
            'released_at'  => $data['released_at'],
            'is_published' => $data['is_published'],
        ]);

        return redirect()->route('pembaruan.index')->with('success', 'Info pembaruan berhasil diperbarui.');
    }

    public function destroy(AppUpdate $update)
    {
        $this->guard();
        $update->delete();

        return back()->with('success', 'Info pembaruan dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'version'      => 'required|string|max:30',
            'title'        => 'required|string|max:150',
            'content'      => 'nullable|string',
            'released_at'  => 'required|date',
            'is_published' => 'nullable|boolean',
        ]);
        $data['content'] = RichText::clean($data['content'] ?? '');
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    /** Riwayat pembaruan untuk SEMUA pengguna — supaya bisa dibuka lagi kapan saja lewat sidebar. */
    public function riwayat()
    {
        $updates = AppUpdate::where('is_published', true)
            ->orderByDesc('released_at')
            ->orderByDesc('created_at')
            ->get();

        return view('pembaruan.riwayat', compact('updates'));
    }

    /** Ditandai user lewat checkbox "jangan tampilkan lagi" di popup. */
    public function dismiss(Request $request)
    {
        $data = $request->validate(['update_id' => 'required|string']);
        auth()->user()->update(['dismissed_update_id' => $data['update_id']]);

        return response()->json(['ok' => true]);
    }
}
