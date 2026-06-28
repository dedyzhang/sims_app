@extends('sarpras.layouts.app')
@section('title', $supplier->exists ? 'Edit Supplier' : 'Supplier Baru')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $supplier->exists ? 'Edit' : 'Tambah' }} Supplier</h2>
    <form method="POST" class="space-y-4 text-sm"
          action="{{ $supplier->exists ? route('sarpras.supplier.update', $supplier) : route('sarpras.supplier.store') }}">
        @csrf @if ($supplier->exists) @method('PUT') @endif
        @foreach (['nama'=>'Nama','kontak'=>'Kontak (PIC)','telepon'=>'Telepon','email'=>'Email','npwp'=>'NPWP (opsional)'] as $f=>$l)
            <div>
                <label class="block text-gray-700 mb-1">{{ $l }}</label>
                <input name="{{ $f }}" value="{{ old($f, $supplier->$f) }}" class="w-full border rounded px-3 py-2" @if($f==='nama') required @endif>
            </div>
        @endforeach
        <div>
            <label class="block text-gray-700 mb-1">Alamat</label>
            <textarea name="alamat" rows="2" class="w-full border rounded px-3 py-2">{{ old('alamat', $supplier->alamat) }}</textarea>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
            <a href="{{ route('sarpras.supplier.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
