@extends('layouts.app')
@section('title', 'Buat RPM Digital Baru')

@section('content')
<div class="space-y-6" x-data="rpmCreator()">
    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ route('rpm.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition flex-shrink-0 mt-0.5" title="Kembali ke Daftar">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="page-title">Buat RPM Digital</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    Pilih salah satu cara pembuatan: Murni dari AI, Upload PDF Pelajaran (file langsung dihapus), atau Ambil dari Asisten Guru.
                </p>
            </div>
        </div>
    </div>

    {{-- Pemilih Metode / Tab Generator --}}
    <div class="card p-2 sm:p-2.5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <button type="button" @click="activeTab = 'ai_direct'"
                    class="py-3 px-4 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-center gap-2.5 transition"
                    :class="activeTab === 'ai_direct' ? 'bg-primary text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                <div class="text-left">
                    <p class="leading-tight">1. Murni AI</p>
                    <p class="text-[10px] opacity-80 font-normal">Sama seperti Asisten Guru</p>
                </div>
            </button>

            <button type="button" @click="activeTab = 'pdf_upload'"
                    class="py-3 px-4 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-center gap-2.5 transition"
                    :class="activeTab === 'pdf_upload' ? 'bg-primary text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                <i data-lucide="file-up" class="w-4 h-4"></i>
                <div class="text-left">
                    <p class="leading-tight">2. Upload PDF Pelajaran</p>
                    <p class="text-[10px] opacity-80 font-normal">Mendukung &ge;10MB (auto-delete)</p>
                </div>
            </button>

            <button type="button" @click="activeTab = 'asisten_guru'"
                    class="py-3 px-4 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-center gap-2.5 transition"
                    :class="activeTab === 'asisten_guru' ? 'bg-primary text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'">
                <i data-lucide="history" class="w-4 h-4"></i>
                <div class="text-left">
                    <p class="leading-tight">3. Dari Asisten Guru</p>
                    <p class="text-[10px] opacity-80 font-normal">Ambil riwayat generate</p>
                </div>
            </button>
        </div>
    </div>

    {{-- Form Kontainer Per Metode --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Kolom Kiri: Form Generator & Editor --}}
        <div class="lg:col-span-5 space-y-5">
            {{-- TAB 1: AI LANGSUNG --}}
            <div x-show="activeTab === 'ai_direct'" class="card p-5 space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 grid place-items-center">
                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100">Cara 1: Murni AI Langsung</h2>
                        <p class="text-[11px] text-slate-400">Menyusun RPM lengkap berdasarkan topik dan mata pelajaran.</p>
                    </div>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="form-label text-xs">Mata Pelajaran <span class="text-rose-500">*</span></label>
                        <select x-model="formAi.mapel" class="form-select text-xs">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach($pelajarans as $p)
                                <option value="{{ $p->nama }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Kelas</label>
                            <select x-model="formAi.kelas" class="form-select text-xs">
                                <option value="VII">Kelas VII (7)</option>
                                <option value="VIII">Kelas VIII (8)</option>
                                <option value="IX">Kelas IX (9)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Semester</label>
                            <select x-model="formAi.semester" class="form-select text-xs">
                                <option value="1 (Ganjil)">Semester 1 (Ganjil)</option>
                                <option value="2 (Genap)">Semester 2 (Genap)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label text-xs">Alokasi Waktu</label>
                        <input type="text" x-model="formAi.alokasi_waktu" class="form-input text-xs" placeholder="mis. 2 JP (2 x 40 Menit)">
                    </div>

                    <div>
                        <label class="form-label text-xs">Topik / Materi Pembelajaran <span class="text-rose-500">*</span></label>
                        <textarea x-model="formAi.topik" rows="3" class="form-input text-xs" placeholder="mis. Teks Prosedur: Menyusun Petunjuk Penggunaan Teknologi Digital"></textarea>
                    </div>

                    <div>
                        <label class="form-label text-xs">Catatan Tambahan / Fokus Pedagogis (Opsional)</label>
                        <textarea x-model="formAi.detail" rows="2" class="form-input text-xs" placeholder="mis. Integrasikan proyek kelompok mini dan pemanfaatan Canva"></textarea>
                    </div>

                    <button type="button" @click="generateAiDirect()" :disabled="generating"
                            class="w-full btn-primary py-2.5 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 shadow-sm disabled:opacity-50">
                        <template x-if="!generating">
                            <span class="flex items-center gap-2">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                                <span>Generate RPM dengan AI</span>
                            </span>
                        </template>
                        <template x-if="generating">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span>Sedang Menyusun RPM... (~30 detik)</span>
                            </span>
                        </template>
                    </button>
                </div>
            </div>

            {{-- TAB 2: UPLOAD PDF PELAJARAN --}}
            <div x-show="activeTab === 'pdf_upload'" class="card p-5 space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 grid place-items-center">
                        <i data-lucide="file-up" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100">Cara 2: Upload PDF Pelajaran</h2>
                        <p class="text-[11px] text-slate-400">File &ge;10MB didukung. File fisik langsung dihapus setelah generate.</p>
                    </div>
                </div>

                {{-- Alert Keamanan Storage & Penghapusan File --}}
                <div class="p-3.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-[11px] text-blue-800 dark:text-blue-200 space-y-1">
                    <div class="flex items-center gap-1.5 font-bold">
                        <i data-lucide="shield-check" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
                        <span>Privasi & Keamanan Storage Server</span>
                    </div>
                    <p class="text-blue-700 dark:text-blue-300">
                        File PDF materi diekstrak teksnya untuk dianalisis oleh AI, kemudian <strong>file fisik di server langsung dihapus permanen</strong> setelah proses selesai. Server tidak menyimpan arsip file PDF Anda.
                    </p>
                </div>

                <div class="space-y-3.5">
                    {{-- File Input --}}
                    <div>
                        <label class="form-label text-xs">Pilih File PDF Buku/Modul Pelajaran <span class="text-rose-500">*</span></label>
                        <label class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 cursor-pointer hover:border-primary hover:bg-slate-50 dark:hover:bg-slate-800/50 transition group text-center">
                            <i data-lucide="file-text" class="w-8 h-8 text-slate-400 group-hover:text-primary mb-2 transition"></i>
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200" x-text="pdfFile ? pdfFile.name : 'Klik untuk memilih file PDF'"></span>
                            <span class="text-[10px] text-slate-400 mt-0.5" x-text="pdfFile ? (pdfFile.size / (1024*1024)).toFixed(2) + ' MB' : 'Format .pdf, mendukung minimal 10MB hingga 50MB'"></span>
                            <input type="file" accept="application/pdf" class="hidden" @change="onPdfSelected($event)">
                        </label>
                    </div>

                    <div>
                        <label class="form-label text-xs">Mata Pelajaran (Opsional / Otomatis dari PDF)</label>
                        <select x-model="formPdf.mapel" class="form-select text-xs">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach($pelajarans as $p)
                                <option value="{{ $p->nama }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Kelas</label>
                            <select x-model="formPdf.kelas" class="form-select text-xs">
                                <option value="VII">Kelas VII (7)</option>
                                <option value="VIII">Kelas VIII (8)</option>
                                <option value="IX">Kelas IX (9)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Semester</label>
                            <select x-model="formPdf.semester" class="form-select text-xs">
                                <option value="1 (Ganjil)">Semester 1 (Ganjil)</option>
                                <option value="2 (Genap)">Semester 2 (Genap)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label text-xs">Fokus Topik / Bab Tertentu (Opsional)</label>
                        <input type="text" x-model="formPdf.topik" class="form-input text-xs" placeholder="Kosongkan jika ingin AI mendeteksi topik utama dari PDF">
                    </div>

                    <div>
                        <label class="form-label text-xs">Catatan Tambahan untuk AI (Opsional)</label>
                        <textarea x-model="formPdf.detail" rows="2" class="form-input text-xs" placeholder="mis. Berikan perhatian khusus pada kegiatan asesmen proses"></textarea>
                    </div>

                    <button type="button" @click="generateFromPdf()" :disabled="generating || !pdfFile"
                            class="w-full btn-primary py-2.5 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 shadow-sm disabled:opacity-50">
                        <template x-if="!generating">
                            <span class="flex items-center gap-2">
                                <i data-lucide="cpu" class="w-4 h-4"></i>
                                <span>Ekstrak Teks & Generate RPM</span>
                            </span>
                        </template>
                        <template x-if="generating">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span>Mengekstrak & Menyusun RPM...</span>
                            </span>
                        </template>
                    </button>

                    <div x-show="pdfDeletedNotice" x-cloak class="p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-[11px] text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0"></i>
                        <span>File PDF berhasil diproses dan <strong>file fisik di server telah langsung dihapus</strong>.</span>
                    </div>
                </div>
            </div>

            {{-- TAB 3: DARI ASISTEN GURU --}}
            <div x-show="activeTab === 'asisten_guru'" class="card p-5 space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 grid place-items-center">
                        <i data-lucide="history" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100">Cara 3: Dari Asisten Guru</h2>
                        <p class="text-[11px] text-slate-400">Pilih dari RPM yang sudah pernah Anda buat di menu Asisten Guru.</p>
                    </div>
                </div>

                {{-- Pencarian Riwayat --}}
                <div class="relative">
                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" x-model="historySearch" placeholder="Cari riwayat RPM..." class="form-input pl-8 text-xs py-1.5">
                </div>

                {{-- Daftar Riwayat RPM --}}
                <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    @forelse($aiTeacherHistories as $h)
                    <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-primary/50 transition cursor-pointer"
                         :class="selectedHistoryUuid === '{{ $h->uuid }}' ? 'border-primary bg-primary-50/20 dark:bg-primary-950/20' : 'bg-slate-50/50 dark:bg-slate-800/40'"
                         @click="selectHistory(@js($h))"
                         x-show="!historySearch || '{{ strtolower(addslashes($h->title)) }}'.includes(historySearch.toLowerCase())">
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 line-clamp-1">{{ $h->title }}</h4>
                            <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ $h->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 mt-1">
                            {{ $h->excerpt ?: Str::limit(strip_tags($h->answer), 90) }}
                        </p>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium">
                                {{ $h->type_label ?: 'RPM' }}
                            </span>
                            <button type="button" class="btn-primary py-1 px-2.5 rounded-lg text-[10px] font-semibold flex items-center gap-1">
                                <i data-lucide="check" class="w-3 h-3"></i>
                                <span>Gunakan Ini</span>
                            </button>
                        </div>
                    </div>
                    @empty
                    <div class="p-6 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                        <p class="text-xs">Belum ada riwayat RPM di Asisten Guru.</p>
                        <p class="text-[10px] text-slate-400 mt-1">Gunakan Cara 1 (Murni AI) atau Cara 2 (Upload PDF) di atas.</p>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- FORM PENYIMPANAN RPM --}}
            <div class="card p-5 space-y-4" x-show="savedContent" x-cloak>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4 text-primary"></i>
                        <span>Simpan Dokumen RPM Digital</span>
                    </h3>
                    <span class="text-[10px] px-2 py-0.5 rounded-md font-semibold"
                          :class="sourceType === 'ai_direct' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (sourceType === 'pdf_upload' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-amber-50 text-amber-700 border border-amber-200')"
                          x-text="sourceType === 'ai_direct' ? 'Murni AI' : (sourceType === 'pdf_upload' ? 'Upload PDF' : 'Dari Asisten Guru')">
                    </span>
                </div>

                <form method="POST" action="{{ route('rpm.store') }}" class="space-y-3.5">
                    @csrf
                    <input type="hidden" name="source_type" :value="sourceType">
                    <input type="hidden" name="source_file_name" :value="sourceFileName">

                    <div>
                        <label class="form-label text-xs">Judul Dokumen RPM <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" x-model="docTitle" required class="form-input text-xs" placeholder="mis. RPM: Teks Prosedur Kelas VII">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Mata Pelajaran</label>
                            <input type="text" name="mapel" x-model="docMapel" class="form-input text-xs" placeholder="mis. Bahasa Indonesia">
                        </div>
                        <div>
                            <label class="form-label text-xs">Kelas</label>
                            <input type="text" name="kelas" x-model="docKelas" class="form-input text-xs" placeholder="mis. VII">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Semester</label>
                            <input type="text" name="semester" x-model="docSemester" class="form-input text-xs" placeholder="mis. 1 (Ganjil)">
                        </div>
                        <div>
                            <label class="form-label text-xs">Alokasi Waktu</label>
                            <input type="text" name="alokasi_waktu" x-model="docAlokasiWaktu" class="form-input text-xs" placeholder="mis. 2 JP (2 x 40 Menit)">
                        </div>
                    </div>

                    <div>
                        <label class="form-label text-xs">Topik Pembelajaran</label>
                        <input type="text" name="topik" x-model="docTopik" class="form-input text-xs" placeholder="Topik materi...">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="form-label text-xs mb-0">Teks Lengkap Dokumen RPM</label>
                            <button type="button" @click="refreshPreview()" class="text-[11px] text-primary hover:underline flex items-center gap-1">
                                <i data-lucide="refresh-cw" class="w-3 h-3"></i> Segarkan Pratinjau
                            </button>
                        </div>
                        <textarea name="content" x-model="savedContent" rows="8" class="form-input font-mono text-[11px]" placeholder="Konten RPM lengkap..."></textarea>
                    </div>

                    <button type="submit" class="w-full btn-primary py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 shadow-sm">
                        <i data-lucide="bookmark-check" class="w-4 h-4"></i>
                        <span>Simpan ke RPM Digital</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Kolom Kanan: Pratinjau Dokumen Formal --}}
        <div class="lg:col-span-7">
            <div class="card p-5 space-y-4 sticky top-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4 text-primary"></i>
                        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">Pratinjau Dokumen RPM Resmi</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="refreshPreview()" class="btn-ghost py-1 px-2.5 rounded-lg text-xs text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 flex items-center gap-1">
                            <i data-lucide="refresh-cw" class="w-3 h-3"></i> Segarkan
                        </button>
                    </div>
                </div>

                {{-- Area Pratinjau Dokumen Kertas --}}
                <div class="bg-slate-100 dark:bg-slate-900/60 p-3 sm:p-5 rounded-xl border border-slate-200 dark:border-slate-800 max-h-[800px] overflow-y-auto">
                    <template x-if="previewHtml">
                        <div x-html="previewHtml"></div>
                    </template>

                    <template x-if="!previewHtml && !generating">
                        <div class="p-16 text-center text-slate-400">
                            <i data-lucide="file-text" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                            <h4 class="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum Ada Pratinjau Dokumen</h4>
                            <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1">
                                Klik salah satu tombol generate di sebelah kiri atau pilih riwayat RPM dari Asisten Guru untuk melihat format RPM resmi di sini.
                            </p>
                        </div>
                    </template>

                    <template x-if="generating">
                        <div class="p-16 text-center text-slate-500 dark:text-slate-400 space-y-3">
                            <svg class="animate-spin h-8 w-8 text-primary mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Sedang menyusun dan memformat dokumen RPM resmi...</p>
                            <p class="text-[11px] text-slate-400">Mengintegrasikan Kop Sekolah, DPL, Desain, Pengalaman Belajar, Asesmen, dan 3 Lampiran.</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function rpmCreator() {
    return {
        activeTab: @js($selectedTab),
        generating: false,
        pdfFile: null,
        pdfDeletedNotice: false,
        selectedHistoryUuid: @js($selectedHistoryUuid),
        historySearch: '',

        // Form fields AI
        formAi: {
            mapel: '',
            kelas: 'VII',
            semester: '1 (Ganjil)',
            alokasi_waktu: '2 JP (2 x 40 Menit)',
            topik: '',
            detail: '',
        },

        // Form fields PDF
        formPdf: {
            mapel: '',
            kelas: 'VII',
            semester: '1 (Ganjil)',
            alokasi_waktu: '2 JP (2 x 40 Menit)',
            topik: '',
            detail: '',
        },

        // Saved document data for store
        savedContent: '',
        previewHtml: '',
        docTitle: '',
        docMapel: '',
        docKelas: 'VII',
        docSemester: '1 (Ganjil)',
        docAlokasiWaktu: '2 JP (2 x 40 Menit)',
        docTopik: '',
        sourceType: 'ai_direct',
        sourceFileName: '',

        onPdfSelected(event) {
            this.pdfFile = event.target.files[0] || null;
            this.pdfDeletedNotice = false;
        },

        async generateAiDirect() {
            if (!this.formAi.topik.trim()) {
                alert('Silakan isi topik pembelajaran.');
                return;
            }
            if (!this.formAi.mapel.trim()) {
                alert('Silakan pilih mata pelajaran.');
                return;
            }

            this.generating = true;
            this.previewHtml = '';

            try {
                const res = await fetch('{{ route("rpm.generate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.formAi),
                });

                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Gagal generate RPM.');
                }

                this.savedContent = data.answer;
                this.previewHtml = data.html;
                this.docTitle = data.title;
                this.docMapel = data.mapel;
                this.docKelas = data.kelas;
                this.docSemester = data.semester;
                this.docAlokasiWaktu = data.alokasi_waktu;
                this.docTopik = data.topik;
                this.sourceType = 'ai_direct';
                this.sourceFileName = '';

                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            } catch (err) {
                alert(err.message || 'Terjadi kesalahan saat generate RPM.');
            } finally {
                this.generating = false;
            }
        },

        async generateFromPdf() {
            if (!this.pdfFile) {
                alert('Silakan pilih file PDF pelajaran terlebih dahulu.');
                return;
            }

            this.generating = true;
            this.previewHtml = '';
            this.pdfDeletedNotice = false;

            const formData = new FormData();
            formData.append('file', this.pdfFile);
            formData.append('topik', this.formPdf.topik);
            formData.append('mapel', this.formPdf.mapel);
            formData.append('kelas', this.formPdf.kelas);
            formData.append('semester', this.formPdf.semester);
            formData.append('alokasi_waktu', this.formPdf.alokasi_waktu);
            formData.append('detail', this.formPdf.detail);

            try {
                const res = await fetch('{{ route("rpm.generate-pdf") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Gagal mengekstrak atau generate RPM dari PDF.');
                }

                this.savedContent = data.answer;
                this.previewHtml = data.html;
                this.docTitle = data.title;
                this.docMapel = data.mapel;
                this.docKelas = data.kelas;
                this.docSemester = data.semester;
                this.docAlokasiWaktu = data.alokasi_waktu;
                this.docTopik = data.topik;
                this.sourceType = 'pdf_upload';
                this.sourceFileName = data.source_file_name || this.pdfFile.name;
                this.pdfDeletedNotice = true;

                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            } catch (err) {
                alert(err.message || 'Terjadi kesalahan saat memproses file PDF.');
            } finally {
                this.generating = false;
            }
        },

        async selectHistory(history) {
            this.selectedHistoryUuid = history.uuid;
            this.savedContent = history.answer || '';
            this.docTitle = history.title || 'RPM dari Asisten Guru';
            this.sourceType = 'asisten_guru';
            this.sourceFileName = '';

            // Ekstrak meta jika ada
            if (history.metadata) {
                if (history.metadata.mapel) this.docMapel = history.metadata.mapel;
                if (history.metadata.kelas) this.docKelas = history.metadata.kelas;
                if (history.metadata.topik) this.docTopik = history.metadata.topik;
            }

            await this.refreshPreview();
        },

        async refreshPreview() {
            if (!this.savedContent.trim()) {
                this.previewHtml = '';
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
                    body: JSON.stringify({ content: this.savedContent }),
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
