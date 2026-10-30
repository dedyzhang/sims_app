@extends('layouts.app')
@section('title', 'Edit RPM — ' . $rpm->title)

@section('content')
<div class="space-y-6" x-data="rpmEditor(@js($rpm->content))">
    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <a href="{{ route('rpm.show', $rpm) }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition flex-shrink-0 mt-0.5" title="Kembali ke Dokumen">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div class="min-w-0">
                <h1 class="page-title truncate">Edit Dokumen RPM Digital</h1>
                <p class="text-xs text-slate-400 mt-0.5">
                    Ubah rincian identitas atau sesuaikan teks dokumen RPM sesuai kebutuhan pembelajaran.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('rpm.show', $rpm) }}" class="btn-ghost px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                Batal
            </a>
        </div>
    </div>

    {{-- Form Edit & Live Preview (2 Kolom) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Kolom Kiri: Form Editor --}}
        <div class="lg:col-span-5 space-y-5">
            <div class="card p-5 space-y-4">
                <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <i data-lucide="edit-3" class="w-4 h-4 text-primary"></i>
                    <span>Formulir Perubahan Dokumen</span>
                </h2>

                <form method="POST" action="{{ route('rpm.update', $rpm) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="form-label text-xs">Judul Dokumen RPM <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title', $rpm->title) }}" required class="form-input text-xs">
                        @error('title') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Mata Pelajaran</label>
                            <input type="text" name="mapel" value="{{ old('mapel', $rpm->mapel) }}" class="form-input text-xs">
                        </div>
                        <div>
                            <label class="form-label text-xs">Kelas</label>
                            <input type="text" name="kelas" value="{{ old('kelas', $rpm->kelas) }}" class="form-input text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Semester</label>
                            <input type="text" name="semester" value="{{ old('semester', $rpm->semester) }}" class="form-input text-xs">
                        </div>
                        <div>
                            <label class="form-label text-xs">Alokasi Waktu</label>
                            <input type="text" name="alokasi_waktu" value="{{ old('alokasi_waktu', $rpm->alokasi_waktu) }}" class="form-input text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="form-label text-xs">Topik Pembelajaran</label>
                        <input type="text" name="topik" value="{{ old('topik', $rpm->topik) }}" class="form-input text-xs">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="form-label text-xs mb-0">Teks Lengkap Dokumen RPM <span class="text-rose-500">*</span></label>
                            <button type="button" @click="refreshPreview()" class="text-[11px] text-primary hover:underline flex items-center gap-1">
                                <i data-lucide="refresh-cw" class="w-3 h-3"></i> Segarkan Pratinjau
                            </button>
                        </div>
                        <textarea name="content" x-model="content" rows="16" required class="form-input font-mono text-[11px] leading-relaxed"></textarea>
                        @error('content') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full btn-primary py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 shadow-sm">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span>Simpan Perubahan RPM</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Kolom Kanan: Pratinjau Dokumen Real-Time --}}
        <div class="lg:col-span-7">
            <div class="card p-5 space-y-4 sticky top-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <i data-lucide="eye" class="w-4 h-4 text-primary"></i>
                        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">Pratinjau Hasil Edit</h3>
                    </div>
                    <button type="button" @click="refreshPreview()" class="btn-ghost py-1 px-2.5 rounded-lg text-xs text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 flex items-center gap-1">
                        <i data-lucide="refresh-cw" class="w-3 h-3"></i> Segarkan
                    </button>
                </div>

                <div class="bg-slate-100 dark:bg-slate-900/60 p-3 sm:p-5 rounded-xl border border-slate-200 dark:border-slate-800 max-h-[850px] overflow-y-auto">
                    <div x-html="previewHtml"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function rpmEditor(initialContent) {
    return {
        content: initialContent || '',
        previewHtml: '',

        init() {
            this.refreshPreview();
        },

        async refreshPreview() {
            if (!this.content.trim()) {
                this.previewHtml = '<p class="text-xs text-slate-400 text-center py-8">Konten kosong.</p>';
                return;
            }

            try {
                const res = await fetch('{{ route("rpm.preview") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ content: this.content }),
                });

                const data = await res.json();
                if (data.ok) {
                    this.previewHtml = data.html;
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                }
            } catch (e) {
                console.error(e);
            }
        }
    };
}
</script>
@endsection
