{{--
    Tombol Import Denah dari file gambar (jpg/jpeg/png/webp/gif/bmp).
    Variabel: $denah (wajib), $gaya ('tombol' default | 'link').
--}}
@php($gaya = $gaya ?? 'tombol')
<form method="POST" action="{{ route('sarpras.denah.import', $denah) }}" enctype="multipart/form-data" class="inline-block">
    @csrf
    @if ($gaya === 'link')
        <label class="cursor-pointer text-amber-600 hover:underline">
            Import
            <input type="file" name="gambar" accept="image/jpeg,image/png,image/webp,image/gif,image/bmp"
                   class="hidden" onchange="this.form.submit()">
        </label>
    @else
        <label class="cursor-pointer inline-flex items-center gap-1 bg-amber-500 text-white px-3 py-1.5 rounded text-xs hover:bg-amber-600">
            ⬆️ Import Denah
            <input type="file" name="gambar" accept="image/jpeg,image/png,image/webp,image/gif,image/bmp"
                   class="hidden" onchange="this.form.submit()">
        </label>
    @endif
</form>
