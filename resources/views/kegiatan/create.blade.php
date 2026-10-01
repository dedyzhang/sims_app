@extends('layouts.app')

@section('title', 'Buat Kegiatan Baru')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-2xl">
    <div class="flex items-center mb-6">
        <a href="{{ route('kegiatan.index') }}" class="mr-4 text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Buat Kegiatan Baru</h1>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('kegiatan.store') }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kegiatan <span class="text-red-500">*</span></label>
                <input type="text" name="nama_kegiatan" required class="form-input w-full" placeholder="Contoh: Bimtek Literasi">
                @error('nama_kegiatan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tema (Opsional)</label>
                <input type="text" name="tema" class="form-input w-full">
                @error('tema') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_mulai" required class="form-input w-full">
                    @error('tanggal_mulai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Waktu Mulai</label>
                    <input type="time" name="waktu_mulai" class="form-input w-full" value="07:30">
                    @error('waktu_mulai') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tempat</label>
                <input type="text" name="tempat" class="form-input w-full">
                @error('tempat') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Pesan Sukses Pendaftaran</label>
                <textarea name="pesan_pendaftaran" rows="2" class="form-input w-full" placeholder="Cth: Pendaftaran berhasil. Silakan gabung ke grup WA berikut..."></textarea>
                <p class="text-xs text-gray-500 mt-1">Pesan ini akan muncul setelah peserta berhasil mengisi formulir.</p>
                @error('pesan_pendaftaran') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Tambahan</label>
                <textarea name="deskripsi" rows="3" class="form-input w-full"></textarea>
                @error('deskripsi') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            
            <!-- Dynamic Form Builder -->
            <div class="mb-6 border-t border-gray-200 pt-6" x-data="{
                fields: [
                    { name: 'nama', label: 'Nama Lengkap (Gelar)', type: 'text', required: true },
                    { name: 'instansi', label: 'Instansi', type: 'text', required: true },
                    { name: 'nohp', label: 'No. HP / WA', type: 'text', required: false }
                ],
                addField() {
                    this.fields.push({ name: '', label: '', type: 'text', required: false });
                },
                removeField(index) {
                    this.fields.splice(index, 1);
                }
            }">
                <h3 class="text-lg font-bold text-gray-800 mb-1">Formulir Pendaftaran (Kustom)</h3>
                <p class="text-sm text-gray-500 mb-4">Atur kolom data apa saja yang wajib diisi oleh calon peserta saat pendaftaran.</p>

                <template x-for="(field, index) in fields" :key="index">
                    <div class="flex flex-wrap md:flex-nowrap items-start gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200 mb-3 relative group">
                        <div class="w-full md:flex-1">
                            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wide">Label Pertanyaan</label>
                            <input type="text" x-model="field.label" @input="if(field.name !== 'nama' && field.name !== 'instansi') field.name = field.label.toLowerCase().replace(/[^a-z0-9]/g, '_')" class="form-input w-full text-sm" placeholder="Misal: Jabatan" required>
                        </div>
                        <div class="w-1/2 md:w-1/3">
                            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wide">Tipe Jawaban</label>
                            <select x-model="field.type" class="form-select w-full text-sm" :disabled="field.name === 'nama' || field.name === 'instansi'">
                                <option value="text">Teks Singkat</option>
                                <option value="number">Angka</option>
                                <option value="email">Email</option>
                                <option value="date">Tanggal</option>
                            </select>
                        </div>
                        <div class="w-auto flex items-center pt-6">
                            <label class="flex items-center" :class="(field.name === 'nama' || field.name === 'instansi') ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'">
                                <input type="checkbox" x-model="field.required" class="form-checkbox w-5 h-5 text-blue-600 rounded" :disabled="field.name === 'nama' || field.name === 'instansi'">
                                <span class="ml-2 text-sm font-medium text-gray-700">Wajib Diisi</span>
                            </label>
                        </div>
                        <button type="button" x-show="field.name !== 'nama' && field.name !== 'instansi'" @click="removeField(index)" class="text-red-500 hover:bg-red-100 p-2 rounded-lg mt-4 md:mt-4 transition-colors" title="Hapus Kolom">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </template>

                <button type="button" @click="addField()" class="mt-2 flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-lg text-sm font-semibold hover:bg-blue-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Pertanyaan Baru
                </button>

                <input type="hidden" name="form_fields" :value="JSON.stringify(fields)">
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('kegiatan.index') }}" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Batal</a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Simpan Kegiatan</button>
            </div>
        </form>
    </div>
</div>
@endsection
