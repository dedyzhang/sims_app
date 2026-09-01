{{-- Form tambah/edit paslon. Var: $action, $paslon (null saat tambah), $method (opsional, 'PUT' saat edit) --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-3">
    @csrf
    @if (($method ?? null) === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="form-label">Nomor Urut</label>
            <input type="number" name="nomor_urut" min="1" max="99" required class="form-input" value="{{ old('nomor_urut', $paslon->nomor_urut ?? '') }}">
        </div>
        <div>
            <label class="form-label">Foto Paslon</label>
            <input type="file" name="foto" accept="image/*" class="form-input text-xs">
        </div>
    </div>
    <div>
        <label class="form-label">Nama Calon Ketua</label>
        <input type="text" name="nama_ketua" required class="form-input" value="{{ old('nama_ketua', $paslon->nama_ketua ?? '') }}">
    </div>
    <div>
        <label class="form-label">Nama Calon Wakil</label>
        <input type="text" name="nama_wakil" class="form-input" value="{{ old('nama_wakil', $paslon->nama_wakil ?? '') }}">
    </div>
    <div>
        <label class="form-label">Visi</label>
        <textarea name="visi" rows="2" class="form-input" placeholder="Visi singkat...">{{ old('visi', $paslon->visi ?? '') }}</textarea>
    </div>
    <div>
        <label class="form-label">Misi <span class="font-normal text-slate-400">(1 poin per baris)</span></label>
        <textarea name="misi" rows="4" class="form-input" placeholder="Misi 1&#10;Misi 2&#10;Misi 3">{{ old('misi', $paslon->misi ?? '') }}</textarea>
    </div>
    <div>
        <label class="form-label">Urutan Tampil <span class="font-normal text-slate-400">(opsional)</span></label>
        <input type="number" name="urutan_tampil" min="0" class="form-input" value="{{ old('urutan_tampil', $paslon->urutan_tampil ?? '') }}">
    </div>
    @error('nomor_urut') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror

    <button type="submit" class="w-full py-2 rounded-lg text-sm font-semibold text-white" style="background:var(--cp)">
        {{ $paslon ? 'Simpan Perubahan' : 'Tambah Paslon' }}
    </button>
</form>
