@extends('layouts.app')
@section('title', $rpm->title . ' — RPM Digital Interaktif')

@section('content')
<div class="space-y-6" x-data="interactiveRpm(@js($interactiveState), @js($quizQuestions))">
    {{-- Top Action Bar (Kembali, Status, Edit, Export) --}}
    <div class="flex items-center justify-between flex-wrap gap-4 no-print">
        <div class="flex items-center gap-3">
            <a href="{{ route('rpm.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition flex-shrink-0" title="Kembali ke Daftar">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border {{ $rpm->sourceTypeBadgeClass() }}">
                        {{ $rpm->sourceTypeLabel() }}
                    </span>
                    <span class="text-xs text-slate-400">
                        Diperbarui {{ $rpm->updated_at?->diffForHumans() }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @can('update', $rpm)
            <a href="{{ route('rpm.edit', $rpm) }}" class="btn-ghost px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-primary hover:text-primary flex items-center gap-1.5">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                <span>Edit Dokumen</span>
            </a>
            @endcan

            {{-- Tombol Pratinjau Export Sesuai Gambar --}}
            <button type="button" @click="exportModalOpen = true" class="btn-primary px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm shadow-primary/20">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Pratinjau Export</span>
            </button>
        </div>
    </div>

    {{-- KARTU UTAMA RPM DIGITAL INTERAKTIF (PERSIS SESUAI GAMBAR & PRD) --}}
    <div class="card p-6 sm:p-10 space-y-6 max-w-5xl mx-auto bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-800">
        {{-- KOP SEKOLAH RESMI DENGAN DUA LOGO & DOUBLE LINE --}}
        <div class="kop-container">
            <div class="flex items-center justify-between gap-4 pb-2">
                {{-- Logo Kiri --}}
                <div class="w-16 h-16 sm:w-20 sm:h-20 flex-shrink-0 grid place-items-center border-2 border-slate-800 dark:border-slate-200 rounded-xl text-slate-800 dark:text-slate-200 p-2">
                    @if(\App\Support\SchoolLetterhead::logoKiriUrl())
                        <img src="{{ \App\Support\SchoolLetterhead::logoKiriUrl() }}" alt="Logo Sekolah" class="max-h-full max-w-full object-contain">
                    @else
                        <i data-lucide="book-open" class="w-9 h-9 sm:w-11 sm:h-11"></i>
                    @endif
                </div>

                {{-- Teks Kop Tengah --}}
                <div class="text-center flex-1 min-w-0">
                    <p class="text-xs sm:text-sm font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-widest leading-tight">
                        {{ $doc['kop'][0] ?? 'YAYASAN PENDIDIKAN CONTOH' }}
                    </p>
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-[#1e3a8a] dark:text-blue-400 font-serif tracking-wide leading-tight mt-0.5">
                        {{ $doc['kop'][1] ?? $identity['satuan_pendidikan'] }}
                    </h2>
                    @if(isset($doc['kop'][2]))
                        <p class="text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $doc['kop'][2] }}
                        </p>
                    @endif
                    <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 leading-snug mt-0.5">
                        {{ implode(' • ', array_slice($doc['kop'], 3)) ?: 'Terakreditasi A • NPSN 00000000 • Tanjungpinang, Kepulauan Riau' }}
                    </p>
                </div>

                {{-- Logo Kanan --}}
                <div class="w-16 h-16 sm:w-20 sm:h-20 flex-shrink-0 grid place-items-center border-2 border-slate-800 dark:border-slate-200 rounded-full text-slate-800 dark:text-slate-200 p-2">
                    @if(\App\Support\SchoolLetterhead::logoKananUrl())
                        <img src="{{ \App\Support\SchoolLetterhead::logoKananUrl() }}" alt="Logo Yayasan" class="max-h-full max-w-full object-contain">
                    @else
                        <i data-lucide="star" class="w-9 h-9 sm:w-11 sm:h-11"></i>
                    @endif
                </div>
            </div>

            {{-- Double Horizontal Rule --}}
            <div class="border-t-2 border-b border-black dark:border-slate-300 py-[1.5px] my-3"></div>
        </div>

        {{-- SUB-HEADER & JUDUL BESAR DOKUMEN --}}
        <div class="space-y-1">
            <p class="text-[11px] sm:text-xs font-bold text-amber-700 dark:text-amber-400 tracking-widest uppercase">
                CONTOH RPM DIGITAL • PEMBELAJARAN MENDALAM • TA 2026/2027
            </p>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold font-serif text-[#1e3a8a] dark:text-blue-400 leading-tight">
                Rencana Pembelajaran Mendalam: {{ $rpm->topik ?: $rpm->title }}
            </h1>
        </div>

        {{-- BOX CATATAN PENYUSUN (PERSIS SESUAI GAMBAR & PRD) --}}
        <div class="p-4 sm:p-5 rounded-r-xl border-l-4 border-amber-500 bg-amber-50/80 dark:bg-amber-950/30 text-amber-950 dark:text-amber-200 text-xs sm:text-[13px] leading-relaxed space-y-1">
            <p>
                <strong>Catatan penyusun:</strong> "RPM Digital" bukan format resmi Kemendikdasmen. Istilah regulasi tetap <strong>RPP</strong> (Permendikdasmen No. 1 Tahun 2026). Versi digital ini memuat 4 komponen inti RPM (A–D) dan 3 pelengkap (E–G), ditambah fitur interaktif: tab per komponen, ceklis pelaksanaan, kuis asesmen awal, dan tiket refleksi. CP dan TP contoh, sesuaikan dengan CP terbaru untuk Pendidikan Agama dan Budi Pekerti.
            </p>
        </div>

        {{-- LABEL SUMBER RUJUKAN (SESUAI GAMBAR) --}}
        <div class="space-y-1.5 pt-1">
            <div class="flex items-center gap-2 flex-wrap text-xs">
                <span class="font-bold text-slate-700 dark:text-slate-300 mr-1">Label sumber:</span>
                <span class="px-2.5 py-1 rounded-md text-xs font-semibold border border-sky-500 bg-sky-50 dark:bg-sky-950/50 text-sky-800 dark:text-sky-300">
                    Regulasi / Kemendikdasmen
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-semibold border border-emerald-500 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300">
                    Buku BSE/SIBI
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-semibold border border-amber-500 bg-amber-50 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300">
                    Asisten Guru
                </span>
            </div>
            <p class="text-[11px] text-slate-400 font-mono">
                Daftar lengkap ada di bagian Sumber di bawah.
            </p>
        </div>

        {{-- GRID IDENTITAS 6 KOTAK (2 BARIS X 3 KOLOM) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-slate-200 dark:border-slate-700 rounded-xl divide-y sm:divide-y-0 sm:divide-x divide-slate-200 dark:divide-slate-700 bg-slate-50/50 dark:bg-slate-800/20 overflow-hidden">
            {{-- Kotak 1: SATUAN PENDIDIKAN --}}
            <div class="p-3.5 sm:p-4 space-y-1">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">SATUAN PENDIDIKAN</p>
                <p class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100">{{ $identity['satuan_pendidikan'] }}</p>
            </div>

            {{-- Kotak 2: MATA PELAJARAN --}}
            <div class="p-3.5 sm:p-4 space-y-1">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">MATA PELAJARAN</p>
                <p class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100">{{ $identity['mapel'] }}</p>
            </div>

            {{-- Kotak 3: FASE / KELAS --}}
            <div class="p-3.5 sm:p-4 space-y-1">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">FASE / KELAS</p>
                <p class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100">{{ $identity['kelas'] }}</p>
            </div>

            {{-- Kotak 4: ALOKASI WAKTU --}}
            <div class="p-3.5 sm:p-4 space-y-1 border-t border-slate-200 dark:border-slate-700">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">ALOKASI WAKTU</p>
                <p class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100">{{ $identity['alokasi_waktu'] }}</p>
            </div>

            {{-- Kotak 5: MODEL --}}
            <div class="p-3.5 sm:p-4 space-y-1 border-t border-slate-200 dark:border-slate-700">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">MODEL</p>
                <p class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100">{{ $identity['model'] }}</p>
            </div>

            {{-- Kotak 6: PENYUSUN --}}
            <div class="p-3.5 sm:p-4 space-y-1 border-t border-slate-200 dark:border-slate-700">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">PENYUSUN</p>
                <p class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100">{{ $identity['penyusun'] }}</p>
            </div>
        </div>

        {{-- 7 TAB NAVIGASI KOMPONEN (A-D INTI, E-G PELENGKAP SESUAI PRD) --}}
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-2 scrollbar-thin">
                <button type="button" @click="activeTab = 'A'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                        :class="activeTab === 'A' ? 'bg-[#1e3a8a] text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                    <span>A. Identifikasi</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded font-normal" :class="activeTab === 'A' ? 'bg-white/20' : 'bg-slate-200 dark:bg-slate-700'">Inti</span>
                </button>

                <button type="button" @click="activeTab = 'B'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                        :class="activeTab === 'B' ? 'bg-[#1e3a8a] text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                    <span>B. Desain</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded font-normal" :class="activeTab === 'B' ? 'bg-white/20' : 'bg-slate-200 dark:bg-slate-700'">Inti</span>
                </button>

                <button type="button" @click="activeTab = 'C'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                        :class="activeTab === 'C' ? 'bg-[#1e3a8a] text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                    <span>C. Pengalaman Belajar</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded font-normal bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Ceklis</span>
                </button>

                <button type="button" @click="activeTab = 'D'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                        :class="activeTab === 'D' ? 'bg-[#1e3a8a] text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                    <span>D. Asesmen</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded font-normal bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300">Kuis & Refleksi</span>
                </button>

                <button type="button" @click="activeTab = 'E'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                        :class="activeTab === 'E' ? 'bg-[#1e3a8a] text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                    <span>E. Tindak Lanjut</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded font-normal" :class="activeTab === 'E' ? 'bg-white/20' : 'bg-slate-200 dark:bg-slate-700'">Pelengkap</span>
                </button>

                <button type="button" @click="activeTab = 'F'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                        :class="activeTab === 'F' ? 'bg-[#1e3a8a] text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                    <span>F. Lampiran</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded font-normal" :class="activeTab === 'F' ? 'bg-white/20' : 'bg-slate-200 dark:bg-slate-700'">3 Dokumen</span>
                </button>

                <button type="button" @click="activeTab = 'G'"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap"
                        :class="activeTab === 'G' ? 'bg-[#1e3a8a] text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'">
                    <span>G. Refleksi Guru</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded font-normal" :class="activeTab === 'G' ? 'bg-white/20' : 'bg-slate-200 dark:bg-slate-700'">Pelengkap</span>
                </button>
            </div>

            {{-- KONTEN TAB A: IDENTIFIKASI --}}
            <div x-show="activeTab === 'A'" class="pt-5 space-y-6">
                @foreach($doc['identifikasi'] as $row)
                    @if(str_starts_with($row['label'], 'Dimensi Profil Lulusan'))
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                <i data-lucide="check-square" class="w-4 h-4 text-primary"></i>
                                <span>Dimensi Profil Lulusan (DPL) yang Dicapai</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach($row['dpl'] as $dpl)
                                    <div class="p-3 rounded-xl border flex items-center gap-3 transition {{ $dpl['checked'] ? 'bg-emerald-50/60 border-emerald-300 dark:bg-emerald-950/30 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 font-semibold' : 'bg-slate-50 border-slate-200 dark:bg-slate-800/40 dark:border-slate-700 text-slate-400 opacity-70' }}">
                                        <span class="w-6 h-6 rounded-lg grid place-items-center flex-shrink-0 {{ $dpl['checked'] ? 'bg-emerald-600 text-white' : 'border border-slate-300 dark:border-slate-600 text-transparent' }}">
                                            <i data-lucide="check" class="w-4 h-4"></i>
                                        </span>
                                        <span class="text-xs">{{ $dpl['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-1.5">
                            <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $row['label'] }}</h4>
                            <div class="text-xs sm:text-sm text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line">
                                {{ implode("\n", $row['lines']) }}
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- KONTEN TAB B: DESAIN PEMBELAJARAN --}}
            <div x-show="activeTab === 'B'" class="pt-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($doc['desain'] as $d)
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-1.5">
                            <h4 class="text-xs font-bold text-primary flex items-center gap-1.5">
                                <i data-lucide="compass" class="w-3.5 h-3.5"></i>
                                <span>{{ $d['label'] }}</span>
                            </h4>
                            <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-line">
                                {{ implode("\n", $d['lines']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- KONTEN TAB C: PENGALAMAN BELAJAR (CEKLIS PELAKSANAAN INTERAKTIF) --}}
            <div x-show="activeTab === 'C'" class="pt-5 space-y-6">
                {{-- Banner 3 Pilar Pembelajaran Mendalam --}}
                <div class="p-3.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 flex items-center justify-between flex-wrap gap-2 text-xs text-blue-900 dark:text-blue-200">
                    <div class="flex items-center gap-2 font-bold">
                        <i data-lucide="sparkles" class="w-4 h-4 text-blue-600"></i>
                        <span>Alur 3 Pilar: Berkesadaran, Bermakna, Menggembirakan</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="font-bold" x-text="completedStepsCount() + ' dari ' + totalStepsCount() + ' langkah selesai'"></span>
                        <span class="text-slate-400">•</span>
                        <span class="font-bold text-emerald-600" x-text="completionPercentage() + '%'"></span>
                    </div>
                </div>

                {{-- Progress Bar Pelaksanaan --}}
                <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                    <div class="bg-emerald-600 h-full transition-all duration-300" :style="'width: ' + completionPercentage() + '%'"></div>
                </div>

                {{-- Tahapan Pembelajaran (Awal, Inti, Penutup) dengan Checkbox Interaktif --}}
                <div class="space-y-6">
                    @php $stepIndex = 0; @endphp
                    @foreach($doc['pengalaman'] as $stage)
                        <div class="p-4 sm:p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 space-y-3.5">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-700 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-extrabold bg-[#1e3a8a] text-white">
                                        {{ $stage['heading'] }}
                                    </span>
                                    @if($stage['subtitle'])
                                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400 italic">
                                            {{ $stage['subtitle'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-2">
                                @foreach($stage['items'] as $item)
                                    @if($item['type'] === 'check')
                                        @php $currentStepKey = 'step_'.$stepIndex++; @endphp
                                        <label class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition select-none group">
                                            <input type="checkbox"
                                                   class="form-checkbox w-4 h-4 rounded text-primary mt-0.5 cursor-pointer"
                                                   :checked="checkedSteps['{{ $currentStepKey }}']"
                                                   @change="toggleStep('{{ $currentStepKey }}')">
                                            <span class="text-xs sm:text-sm text-slate-700 dark:text-slate-200 group-hover:text-primary transition"
                                                  :class="checkedSteps['{{ $currentStepKey }}'] ? 'line-through text-slate-400 dark:text-slate-500' : ''">
                                                {{ $item['text'] }}
                                            </span>
                                        </label>
                                    @elseif($item['type'] === 'quote')
                                        <div class="pl-7 pr-3 py-1.5 text-xs text-amber-800 dark:text-amber-300 font-serif italic border-l-2 border-amber-400">
                                            {{ $item['text'] }}
                                        </div>
                                    @else
                                        <div class="pl-7 text-xs text-slate-600 dark:text-slate-400">
                                            {{ $item['text'] }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Tombol Simpan Ceklis --}}
                <div class="flex items-center justify-between pt-2">
                    <span class="text-xs text-slate-400" x-text="saveStatusMessage || 'Perubahan tersimpan otomatis di server.'"></span>
                    <button type="button" @click="saveState()" class="btn-primary px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="save" class="w-3.5 h-3.5"></i>
                        <span>Simpan Status Ceklis</span>
                    </button>
                </div>
            </div>

            {{-- KONTEN TAB D: ASESMEN (KUIS ASESMEN AWAL & REFLEKSI INTERAKTIF) --}}
            <div x-show="activeTab === 'D'" class="pt-5 space-y-6">
                {{-- Deskripsi Asesmen Pembelajaran --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach($doc['asesmen'] as $as)
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-1">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $as['label'] }}</h4>
                            <p class="text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                                {{ implode("\n", $as['lines']) }}
                            </p>
                        </div>
                    @endforeach
                </div>

                {{-- FITUR INTERAKTIF 1: KUIS ASESMEN AWAL (SESUAI PRD) --}}
                <div class="p-5 sm:p-6 rounded-2xl border-2 border-primary/20 bg-primary-50/10 dark:bg-primary-950/20 space-y-5">
                    <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-primary/10">
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-[#1e3a8a] dark:text-blue-300 flex items-center gap-2">
                                <i data-lucide="help-circle" class="w-4 h-4 text-primary"></i>
                                <span>Kuis Asesmen Awal Siswa (Interaktif)</span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Siswa atau guru dapat menguji pemahaman awal secara interaktif. Skor dinilai langsung di sistem.
                            </p>
                        </div>
                        <template x-if="quizScore !== null">
                            <div class="px-3 py-1 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-bold text-sm">
                                Skor Anda: <span x-text="quizScore + ' / 100'"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Daftar Soal Kuis Pilihan Ganda --}}
                    <div class="space-y-4">
                        <template x-for="(q, idx) in questions" :key="idx">
                            <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
                                <p class="text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    <span class="text-primary font-bold mr-1" x-text="q.number + '.'"></span>
                                    <span x-text="q.question"></span>
                                </p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pl-4">
                                    <template x-for="(optText, optKey) in q.options" :key="optKey">
                                        <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-primary/50 cursor-pointer text-xs transition"
                                               :class="quizAnswers[q.number] === optKey ? 'bg-primary-50/40 border-primary dark:bg-primary-950/40' : ''">
                                            <input type="radio" :name="'quiz_q_' + q.number" :value="optKey"
                                                   class="form-radio text-primary w-3.5 h-3.5 cursor-pointer"
                                                   x-model="quizAnswers[q.number]">
                                            <span class="font-bold uppercase text-slate-500" x-text="optKey + '.'"></span>
                                            <span class="text-slate-700 dark:text-slate-300" x-text="optText"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <button type="button" @click="checkQuizAnswers()" class="btn-primary px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                            <span>Periksa Jawaban & Hitung Skor</span>
                        </button>
                    </div>
                </div>

                {{-- FITUR INTERAKTIF 2: TIKET REFLEKSI SISWA & KELAS --}}
                <div class="p-5 sm:p-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40 space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <i data-lucide="message-square-heart" class="w-4 h-4 text-rose-500"></i>
                        <span>Tiket Refleksi Pembelajaran Siswa</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Pertanyaan panduan refleksi diri siswa setelah menyelesaikan pembelajaran topik ini.
                    </p>

                    <div class="space-y-3">
                        <div class="p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1">
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300">1. Apa konsep terpenting yang Anda pelajari hari ini?</p>
                            <input type="text" x-model="reflectionResponses[1]" class="form-input text-xs" placeholder="Tuliskan respon refleksi singkat...">
                        </div>
                        <div class="p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 space-y-1">
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300">2. Apa tantangan atau pertanyaan yang masih membingungkan?</p>
                            <input type="text" x-model="reflectionResponses[2]" class="form-input text-xs" placeholder="Tuliskan respon refleksi singkat...">
                        </div>
                    </div>
                </div>
            </div>

            {{-- KONTEN TAB E: TINDAK LANJUT --}}
            <div x-show="activeTab === 'E'" class="pt-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800 space-y-2">
                        <h4 class="text-xs font-bold text-rose-800 dark:text-rose-300 flex items-center gap-1.5">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i>
                            <span>Program Remedial (Intervensi Khusus)</span>
                        </h4>
                        <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                            Bagi peserta didik yang belum mencapai tujuan pembelajaran:
                        </p>
                        <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-1 list-disc pl-5">
                            <li>Bimbingan individual atau kelompok kecil pada konsep inti yang belum dipahami.</li>
                            <li>Penyederhanaan bahan ajar visual atau latihan bertahap dengan pendampingan teman sebaya.</li>
                        </ul>
                    </div>

                    <div class="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 space-y-2">
                        <h4 class="text-xs font-bold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                            <i data-lucide="trending-up" class="w-4 h-4"></i>
                            <span>Program Pengayaan (Eksplorasi Mendalam)</span>
                        </h4>
                        <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                            Bagi peserta didik yang telah mencapai kriteria ketuntasan lebih cepat:
                        </p>
                        <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-1 list-disc pl-5">
                            <li>Tugas proyek mandiri atau pembuatan media digital interaktif.</li>
                            <li>Menjadi tutor sebaya untuk membantu rekan dalam diskusi kelompok.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- KONTEN TAB F: LAMPIRAN (3 DOKUMEN RESMI) --}}
            <div x-show="activeTab === 'F'" class="pt-5 space-y-4">
                @foreach($doc['lampiran'] as $lamp)
                    <div class="p-4 sm:p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 space-y-3">
                        <h4 class="text-xs sm:text-sm font-bold text-[#1e3a8a] dark:text-blue-400 flex items-center gap-2">
                            <i data-lucide="paperclip" class="w-4 h-4"></i>
                            <span>{{ $lamp['heading'] }}</span>
                        </h4>

                        @foreach($lamp['blocks'] as $block)
                            @if($block['type'] === 'table')
                                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                                    <table class="w-full text-xs text-left border-collapse">
                                        @foreach($block['rows'] as $rIdx => $row)
                                            <tr class="{{ $rIdx === 0 ? 'bg-amber-100 dark:bg-amber-950/60 font-bold text-slate-800 dark:text-slate-200' : 'border-t border-slate-200 dark:border-slate-700' }}">
                                                @foreach($row as $cell)
                                                    <td class="p-2 sm:p-2.5 border-r border-slate-200 dark:border-slate-700 last:border-r-0">{{ $cell }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            @else
                                <div class="text-xs text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed pl-1">
                                    {{ implode("\n", $block['lines']) }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- KONTEN TAB G: REFLEKSI GURU --}}
            <div x-show="activeTab === 'G'" class="pt-5 space-y-4">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <i data-lucide="pen-tool" class="w-4 h-4 text-primary"></i>
                        <span>Catatan Refleksi Pendidik</span>
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Tuliskan catatan evaluasi Anda mengenai ketercapaian tujuan, keaktifan peserta didik, dan hal yang perlu ditingkatkan untuk pertemuan selanjutnya.
                    </p>
                    <textarea x-model="reflectionText" rows="6" class="form-input text-xs font-sans leading-relaxed" placeholder="Tuliskan refleksi guru di sini..."></textarea>
                    <div class="flex justify-end">
                        <button type="button" @click="saveState()" class="btn-primary px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="save" class="w-3.5 h-3.5"></i>
                            <span>Simpan Catatan Refleksi</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- BAGIAN DAFTAR SUMBER DI BAWAH (PERSIS SESUAI GAMBAR & PRD) --}}
        <div class="pt-6 border-t-2 border-slate-200 dark:border-slate-800 space-y-2 text-xs text-slate-500 dark:text-slate-400">
            <h4 class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider text-[11px]">Daftar Sumber Rujukan</h4>
            <ol class="list-decimal pl-5 space-y-1">
                <li>Permendikdasmen No. 1 Tahun 2026 tentang Perencanaan Pembelajaran dan Format Standar RPP.</li>
                <li>Naskah Akademik dan Pedoman Implementasi Pembelajaran Mendalam Kemendikdasmen 2025/2026.</li>
                <li>Buku Siswa dan Buku Guru Elektronik (BSE / SIBI) Kemendikdasmen.</li>
                <li>Sistem Informasi Manajemen Sekolah (SIMS B'tive) — Modul Asisten Pembelajaran Digital.</li>
            </ol>
        </div>
    </div>

    {{-- MODAL PRATINJAU EXPORT (FORMAT RESMI CETAK A4, PDF, DAN WORD) --}}
    <template x-teleport="body">
        <div x-show="exportModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm p-4 sm:p-6 flex items-center justify-center" @click.self="exportModalOpen = false">
            <div class="bg-white dark:bg-slate-900 w-full max-w-5xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {{-- Header Modal --}}
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4 flex-shrink-0">
                    <div>
                        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <i data-lucide="file-check" class="w-4 h-4 text-primary"></i>
                            <span>Pratinjau Dokumen Export Resmi</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Format formal A4 lengkap dengan Kop, Identitas, Tabel RPM, dan Tanda Tangan.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" onclick="window.print()" class="btn-primary px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                            <span>Cetak</span>
                        </button>
                        <a href="{{ route('rpm.export-pdf', $rpm) }}" class="btn-ghost px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 border border-rose-200 dark:border-rose-800 hover:bg-rose-50 flex items-center gap-1">
                            <i data-lucide="file-down" class="w-3.5 h-3.5"></i>
                            <span>PDF</span>
                        </a>
                        <a href="{{ route('rpm.export-word', $rpm) }}" class="btn-ghost px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 border border-blue-200 dark:border-blue-800 hover:bg-blue-50 flex items-center gap-1">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                            <span>Word</span>
                        </a>
                        <button type="button" @click="exportModalOpen = false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                {{-- Konten Modal: Kertas Dokumen Formal --}}
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 bg-slate-100 dark:bg-slate-950">
                    <div class="rpm-paper-container mx-auto max-w-4xl bg-white shadow-md rounded-xl p-6 sm:p-10 border border-slate-200 dark:border-slate-800">
                        @include('ai.teacher-document-preview', [
                            'doc' => $doc,
                            'content' => $content,
                        ])
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function interactiveRpm(savedState, initialQuestions) {
    return {
        activeTab: 'A',
        exportModalOpen: false,
        checkedSteps: (savedState && savedState.checked_steps) || {},
        quizAnswers: (savedState && savedState.quiz_answers) || {},
        reflectionResponses: {},
        reflectionText: (savedState && savedState.reflection_text) || '',
        saveStatusMessage: '',
        questions: initialQuestions || [],
        quizScore: null,

        toggleStep(stepKey) {
            this.checkedSteps[stepKey] = !this.checkedSteps[stepKey];
            this.saveState();
        },

        totalStepsCount() {
            let count = 0;
            @php $countSteps = $stepIndex; @endphp
            return {{ $countSteps > 0 ? $countSteps : 6 }};
        },

        completedStepsCount() {
            return Object.values(this.checkedSteps).filter(Boolean).length;
        },

        completionPercentage() {
            const total = this.totalStepsCount();
            if (total === 0) return 0;
            return Math.min(100, Math.round((this.completedStepsCount() / total) * 100));
        },

        checkQuizAnswers() {
            if (this.questions.length === 0) return;
            let correctCount = 0;
            this.questions.forEach(q => {
                const userAns = this.quizAnswers[q.number];
                if (userAns && userAns.toLowerCase() === (q.correct || 'a').toLowerCase()) {
                    correctCount++;
                }
            });
            this.quizScore = Math.round((correctCount / this.questions.length) * 100);
            this.saveState();
        },

        async saveState() {
            this.saveStatusMessage = 'Menyimpan...';
            try {
                const res = await fetch('{{ route("rpm.save-state", $rpm) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        checked_steps: this.checkedSteps,
                        quiz_answers: this.quizAnswers,
                        reflection_text: this.reflectionText,
                    }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.saveStatusMessage = 'Tersimpan otomatis.';
                    setTimeout(() => { this.saveStatusMessage = ''; }, 3000);
                }
            } catch (e) {
                console.error(e);
                this.saveStatusMessage = 'Gagal menyimpan ke server.';
            }
        }
    };
}
</script>
@endsection
