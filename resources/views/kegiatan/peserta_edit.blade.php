@extends('layouts.app')

@section('title', 'Edit Peserta')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-2xl">
    <div class="flex items-center mb-6">
        <a href="{{ route('kegiatan.show', $kegiatan) }}" class="mr-4 text-gray-500 hover:text-gray-700">
            <i data-lucide="arrow-left" class="w-6 h-6"></i>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Edit Peserta Kegiatan: {{ $kegiatan->nama_kegiatan }}</h1>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('kegiatan.peserta.update', $peserta) }}" method="POST">
            @csrf
            @method('PUT')
            
            @foreach($kegiatan->form_fields ?? [] as $field)
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $field['label'] }}
                    @if($field['required']) <span class="text-red-500">*</span> @endif
                </label>
                
                @if($field['type'] == 'text')
                <input type="text" name="{{ $field['name'] }}" {{ $field['required'] ? 'required' : '' }} 
                    class="form-input w-full" 
                    value="{{ old($field['name'], $peserta->biodata[$field['name']] ?? '') }}">
                @elseif($field['type'] == 'number')
                <input type="number" name="{{ $field['name'] }}" {{ $field['required'] ? 'required' : '' }} 
                    class="form-input w-full" 
                    value="{{ old($field['name'], $peserta->biodata[$field['name']] ?? '') }}">
                @elseif($field['type'] == 'textarea')
                <textarea name="{{ $field['name'] }}" {{ $field['required'] ? 'required' : '' }} 
                    class="form-input w-full" rows="3">{{ old($field['name'], $peserta->biodata[$field['name']] ?? '') }}</textarea>
                @endif
                
                @error($field['name']) <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            @endforeach

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <a href="{{ route('kegiatan.show', $kegiatan) }}" class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
