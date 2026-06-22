@extends('layouts.app')
@section('title', isset($material) ? 'Sunting Materi' : 'Tambah Materi')

@php $editing = isset($material); @endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ $editing ? route('classroom.material.show', $material) : route('classroom.show', $classroom) }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <nav class="text-xs text-slate-400">{{ $classroom->pelajaran?->nama }} · Kelas {{ $classroom->rombel?->tingkat }}{{ $classroom->rombel?->kelas }}</nav>
            <h1 class="page-title">{{ $editing ? 'Sunting Materi' : 'Tambah Materi' }}</h1>
        </div>
    </div>

    @if($errors->any())
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm"><ul class="list-disc ml-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $editing ? route('classroom.material.update', $material) : route('classroom.material.store', $classroom) }}" enctype="multipart/form-data" class="card p-6 space-y-4">
        @csrf

        <div>
            <label class="form-label">Judul Materi</label>
            <input type="text" name="title" value="{{ old('title', $material->title ?? '') }}" required maxlength="160" class="form-input" placeholder="Judul materi">
        </div>

        <div>
            <label class="form-label">Deskripsi singkat <span class="text-slate-400 font-normal">(opsional)</span></label>
            <input type="text" name="description" value="{{ old('description', $material->description ?? '') }}" class="form-input" placeholder="Ringkasan singkat">
        </div>

        <div>
            <label class="form-label">Isi Materi</label>
            <p class="text-[11px] text-slate-400 mb-1.5">Gunakan tombol <b>∑ Rumus</b> untuk menyisipkan rumus matematika (otomatis jadi gambar SVG) dan <b>▶ YouTube</b> untuk menyematkan video.</p>
            @include('classroom.partials.editor', ['name' => 'body', 'value' => old('body', $material->body ?? '')])
        </div>

        <div>
            <label class="form-label">Tautan referensi <span class="text-slate-400 font-normal">(opsional)</span></label>
            <input type="url" name="link_url" value="{{ old('link_url', $material->link_url ?? '') }}" class="form-input" placeholder="https://...">
        </div>

        {{-- Kelas Online (Google Meet) --}}
        <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4">
            <label class="form-label flex items-center gap-2"><i data-lucide="video" class="w-4 h-4 text-emerald-500"></i> Kelas Online (Google Meet) <span class="text-slate-400 font-normal">(opsional)</span></label>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <input type="text" id="meet-input" name="meet_url" value="{{ old('meet_url', $material->meet_url ?? '') }}" class="form-input flex-1 min-w-48" placeholder="Tempel link meet.google.com/... atau kode rapat">
                <button type="button" id="meet-paste" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 whitespace-nowrap"><i data-lucide="clipboard-paste" class="w-4 h-4"></i> Tempel</button>
                <a href="https://meet.google.com/new" target="_blank" rel="noopener" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-bold text-white whitespace-nowrap" style="background:#16a34a"><i data-lucide="plus" class="w-4 h-4"></i> Buat Meet Baru</a>
                <a id="meet-open" href="#" target="_blank" rel="noopener" class="hidden items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold border border-emerald-300 text-emerald-700 dark:text-emerald-300"><i data-lucide="external-link" class="w-4 h-4"></i> Tes Buka</a>
            </div>
            <p id="meet-hint" class="text-[11px] text-slate-400 mt-1.5">Langkah: <b>1)</b> klik <b>Buat Meet Baru</b> → Google membuat rapat → klik ikon salin link di Google. <b>2)</b> kembali ke sini, klik <b>Tempel</b> (otomatis terisi). Siswa lalu melihat tombol <b>Gabung Google Meet</b> di materi.</p>
        </div>

        {{-- Taut / duplikat ke beberapa kelas --}}
        <div>
            <label class="form-label">Tampilkan / Duplikat ke Kelas</label>
            <p class="text-[11px] text-slate-400 mb-2">Centang semua kelas yang akan memuat materi ini. Mengedit materi akan langsung mengubahnya di <b>semua kelas tertaut</b>.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @forelse($kelasOptions as $k)
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 cursor-pointer text-sm hover:border-primary">
                    <input type="checkbox" name="kelas[]" value="{{ $k->uuid }}" @checked(in_array($k->uuid, old('kelas', $checked), true)) @disabled($k->uuid === $classroom->id_kelas) class="rounded">
                    <span>Kelas {{ $k->tingkat }}{{ $k->kelas }}</span>
                </label>
                @empty
                <p class="text-sm text-slate-400">Tidak ada kelas lain untuk mapel ini.</p>
                @endforelse
            </div>
            @if($classroom->id_kelas)<input type="hidden" name="kelas[]" value="{{ $classroom->id_kelas }}">@endif
        </div>

        {{-- Kunci materi (mode ujian / layar penuh) --}}
        <div x-data="{ locked: {{ old('is_locked', ($material->is_locked ?? false)) ? 'true' : 'false' }} }" class="rounded-xl border border-slate-200 dark:border-slate-600 p-4">
            <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer select-none">
                <input type="checkbox" name="is_locked" value="1" x-model="locked" class="rounded">
                <i data-lucide="lock" class="w-4 h-4 text-amber-500"></i> Kunci materi (siswa harus masukkan token &amp; masuk layar penuh)
            </label>
            <div x-show="locked" x-cloak class="mt-3">
                <label class="form-label text-xs">Token akses</label>
                <input type="text" name="access_token" value="{{ old('access_token', $material->access_token ?? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4))) }}" maxlength="16" class="form-input font-mono tracking-widest uppercase max-w-40">
                <p class="text-[11px] text-slate-400 mt-1">Bagikan token ini ke siswa secara lisan. Saat dikunci: siswa wajib memasukkan token, layar masuk mode penuh, dan <b>otomatis ter-keluar bila berpindah tab/keluar layar penuh</b> — Anda akan menerima notifikasi.</p>
            </div>
        </div>

        <div>
            <label class="form-label">Lampiran <span class="text-slate-400 font-normal">(gambar/PDF, opsional)</span></label>
            @include('classroom.partials.upload', ['label' => 'Tambah lampiran'])
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ $editing ? route('classroom.material.show', $material) : route('classroom.show', $classroom) }}" class="px-5 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</a>
            <button class="px-6 py-2.5 rounded-xl text-sm font-bold text-white" style="background:var(--cp)">{{ $editing ? 'Simpan Perubahan' : 'Simpan Materi' }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('meet-input');
    const pasteBtn = document.getElementById('meet-paste');
    const openBtn = document.getElementById('meet-open');
    const hint = document.getElementById('meet-hint');
    if (!input) return;

    // Normalisasi → URL meet bersih atau null.
    function normalize(v) {
        v = (v || '').trim();
        if (!v) return null;
        let m = v.match(/meet\.google\.com\/([a-z0-9-]+)/i);
        if (m) return 'https://meet.google.com/' + m[1];
        if (/^[a-z]{3}-[a-z]{4}-[a-z]{3}$/i.test(v)) return 'https://meet.google.com/' + v.toLowerCase();
        return null;
    }
    function refresh() {
        const url = normalize(input.value);
        if (url) {
            input.value = url;
            openBtn.href = url;
            openBtn.classList.remove('hidden'); openBtn.classList.add('inline-flex');
            input.classList.add('border-emerald-400');
            hint.innerHTML = '✓ Link Meet valid. Siswa akan melihat tombol <b>Gabung Google Meet</b>.';
            hint.className = 'text-[11px] text-emerald-600 dark:text-emerald-400 mt-1.5 font-medium';
        } else {
            openBtn.classList.add('hidden'); openBtn.classList.remove('inline-flex');
            input.classList.remove('border-emerald-400');
            if (input.value.trim()) {
                hint.textContent = 'Belum berupa link Google Meet yang valid (contoh: meet.google.com/abc-defg-hij).';
                hint.className = 'text-[11px] text-rose-500 mt-1.5';
            }
        }
    }
    pasteBtn?.addEventListener('click', async () => {
        try {
            const text = await navigator.clipboard.readText();
            if (text) { input.value = text; refresh(); }
            if (!normalize(input.value)) {
                hint.textContent = 'Clipboard belum berisi link Meet. Salin dulu link dari tab Google Meet, lalu klik Tempel.';
                hint.className = 'text-[11px] text-amber-600 mt-1.5';
            }
        } catch (e) {
            input.focus();
            hint.textContent = 'Browser tidak mengizinkan baca clipboard otomatis. Tempel manual dengan Ctrl+V pada kolom.';
            hint.className = 'text-[11px] text-amber-600 mt-1.5';
        }
    });
    input.addEventListener('blur', refresh);
    input.addEventListener('paste', () => setTimeout(refresh, 50));
    if (input.value) refresh();
})();
</script>
@endpush
