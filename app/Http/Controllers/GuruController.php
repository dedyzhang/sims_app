<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuruController extends Controller
{
    public function index(Request $request)
    {
        $gurus = Guru::with('user', 'walikelas.kelas')
            ->when($request->search, fn($q) => $q->where('nama', 'like', "%{$request->search}%")
                ->orWhere('nik', 'like', "%{$request->search}%"))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('guru.index', compact('gurus'));
    }

    public function create()
    {
        return view('guru.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'          => 'required|string|max:100',
            'nik'           => 'nullable|string|max:20|unique:gurus,nik',
            'nip'           => 'nullable|string|max:20',
            'jk'            => 'required|in:L,P',
            'tempat_lahir'  => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'agama'         => 'nullable|string',
            'alamat'        => 'nullable|string',
            'tingkat_studi' => 'nullable|string',
            'program_studi' => 'nullable|string',
            'universitas'   => 'nullable|string',
            'tahun_tamat'   => 'nullable|string',
            'tmt_ngajar'    => 'nullable|date',
            'tmt_smp'       => 'nullable|date',
            'no_telp'       => 'nullable|string|max:20',
        ]);

        // Buat akun login
        $username = Str::slug($data['nama'], '.') . '.' . Str::random(4);
        $password = Str::random(8);
        $identifier = $data['nik'] ?? $data['nip'] ?? null;

        $user = User::create([
            'username'   => $username,
            'identifier' => $identifier,
            'password'   => $password,
            'access'     => 'guru',
            'must_change_password' => true,
        ]);

        $data['id_login'] = $user->uuid;
        Guru::create($data);

        return redirect()->route('guru.index')
            ->with('success', "Guru berhasil ditambah. Username: {$username} | Password: {$password}");
    }

    public function show(string $uuid)
    {
        $guru = Guru::with(['user', 'walikelas.kelas', 'ngajars.pelajaran', 'ngajars.kelas'])
            ->findOrFail($uuid);
        return view('guru.show', compact('guru'));
    }

    public function edit(string $uuid)
    {
        $guru = Guru::findOrFail($uuid);
        return view('guru.edit', compact('guru'));
    }

    public function update(Request $request, string $uuid)
    {
        $guru = Guru::findOrFail($uuid);
        $data = $request->validate([
            'nama'          => 'required|string|max:100',
            'nik'           => "nullable|string|max:20|unique:gurus,nik,{$uuid},uuid",
            'nip'           => 'nullable|string|max:20',
            'jk'            => 'required|in:L,P',
            'tempat_lahir'  => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'agama'         => 'nullable|string',
            'alamat'        => 'nullable|string',
            'tingkat_studi' => 'nullable|string',
            'program_studi' => 'nullable|string',
            'universitas'   => 'nullable|string',
            'tahun_tamat'   => 'nullable|string',
            'tmt_ngajar'    => 'nullable|date',
            'tmt_smp'       => 'nullable|date',
            'no_telp'       => 'nullable|string|max:20',
        ]);

        $guru->update($data);

        // Sync identifier di users
        if ($guru->user) {
            $guru->user->update(['identifier' => $data['nik'] ?? $data['nip'] ?? null]);
        }

        return redirect()->route('guru.show', $uuid)->with('success', 'Data guru diperbarui.');
    }

    public function destroy(string $uuid)
    {
        $guru = Guru::findOrFail($uuid);
        if ($guru->user) {
            $guru->user->delete();
        }
        $guru->delete();

        return redirect()->route('guru.index')->with('success', 'Guru dihapus.');
    }

    public function reset(string $uuid)
    {
        $guru = Guru::findOrFail($uuid);
        $password = Str::random(8);

        if ($guru->user) {
            $guru->user->update([
                'password' => $password,
                'must_change_password' => true,
            ]);
        }

        return back()->with('reset_account', [
            'role' => 'Guru',
            'name' => $guru->nama,
            'username' => $guru->user?->username ?? '-',
            'password' => $password,
        ])->with('success', "Password direset. Password baru: {$password}");
    }

    // Halaman assign pelajaran
    public function pelajaran(string $uuid)
    {
        $guru      = Guru::findOrFail($uuid);
        $pelajarans = Pelajaran::orderBy('urutan')->orderBy('nama')->get();
        $kelas     = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $ngajars   = Ngajar::with(['pelajaran', 'kelas'])->where('id_guru', $uuid)->get();

        return view('guru.pelajaran', compact('guru', 'pelajarans', 'kelas', 'ngajars'));
    }

    public function ngajar(Request $request, string $uuid)
    {
        $request->validate([
            'id_pelajaran' => 'required|exists:pelajarans,uuid',
            'id_kelas'     => 'nullable|exists:kelas,uuid',
        ]);

        Guru::findOrFail($uuid);

        Ngajar::firstOrCreate([
            'id_guru'      => $uuid,
            'id_pelajaran' => $request->id_pelajaran,
            'id_kelas'     => $request->id_kelas,
        ]);

        return back()->with('success', 'Pelajaran berhasil ditambahkan.');
    }

    public function hapusNgajar(string $ngajarUuid)
    {
        Ngajar::findOrFail($ngajarUuid)->delete();
        return back()->with('success', 'Pelajaran dihapus.');
    }
}
