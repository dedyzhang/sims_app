@extends('layouts.app')
@section('title', 'Nilai Ekskul')

@section('content')
@php
    $rowsJs = [];
    if (!$readonly) {
        foreach ($siswas as $s) { $rowsJs[$s->uuid] = ['desc' => $saved[$s->uuid] ?? '']; }
    }
@endphp
<div class="space-y-4" x-data="ekskulNilai(@js($rowsJs), '{{ route('ekskul.nilai.cell', $ekskul->uuid) }}')">
    <div class="flex items-center gap-3 flex-wrap">
        <a href="{{ route('ekskul.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">{{ $ekskul->nama }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Pembina: {{ $ekskul->guru?->nama ?? '—' }}
                @if($readonly) <span class="text-slate-300">&bull;</span> otomatis dari mapel <b>{{ $ekskul->pelajaran?->nama }}</b> @else <span class="text-slate-300">&bull;</span> manual @endif
                @if($sem) <span class="text-slate-300">&bull;</span> {{ $sem->nama_lengkap }} @endif
            </p>
        </div>
    </div>

    {{-- Pilih kelas --}}
    <form method="GET" action="{{ route('ekskul.nilai', $ekskul->uuid) }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>

    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400"><i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada siswa di kelas ini.</p></div>
    @else
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 flex-wrap px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            @if($readonly)
            <p class="text-xs text-slate-400 flex items-start gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i> Deskripsi <b>otomatis</b> dari nilai rapor mapel — guru tidak perlu mengisi. Mapel ini akan muncul di <b>kolom Ekskul</b> saat cetak rapor (bukan sebagai nilai mapel).</p>
            @if($konfirmasi === true)
            <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 flex items-center gap-1 flex-shrink-0"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Rapor dikonfirmasi</span>
            @elseif($konfirmasi === false)
            <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 flex items-center gap-1 flex-shrink-0"><i data-lucide="clock" class="w-3.5 h-3.5"></i> Rapor belum dikonfirmasi</span>
            @endif
            @else
            <p class="text-xs text-slate-400 flex items-start gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i> Isi deskripsi untuk siswa yang mengikuti ekskul ini. Tersimpan otomatis. Kosongkan untuk menghapus.</p>
            @endif
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th class="text-center w-12">No</th><th class="text-left">Nama Siswa</th><th class="text-left">NIS</th><th class="text-center">L/P</th><th class="text-left">Deskripsi Ekskul</th></tr></thead>
                <tbody>
                    @foreach($siswas as $i => $s)
                    @php $sid = $s->uuid; @endphp
                    <tr>
                        <td class="text-center text-slate-400 align-top pt-3">{{ $i + 1 }}</td>
                        <td class="font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap align-top pt-3">{{ $s->nama }}</td>
                        <td class="text-slate-500 dark:text-slate-400 text-sm whitespace-nowrap align-top pt-3">{{ $s->nis }}</td>
                        <td class="text-center text-slate-500 dark:text-slate-400 text-sm align-top pt-3">{{ $s->jk }}</td>
                        <td>
                            @if($readonly)
                                @php $d = $auto[$sid] ?? ''; @endphp
                                @if($d)
                                <p class="text-sm text-slate-600 dark:text-slate-300 py-2.5 leading-relaxed">{{ $d }}</p>
                                @else
                                <p class="text-sm text-slate-300 dark:text-slate-600 italic py-2.5">— belum ada nilai rapor untuk siswa ini —</p>
                                @endif
                            @else
                                <textarea x-model="rows['{{ $sid }}'].desc" @blur="simpan('{{ $sid }}')" rows="2" class="form-input text-sm w-full min-w-[240px] my-1" placeholder="Deskripsi ekskul…"></textarea>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function ekskulNilai(rows, url) {
        return {
            rows: rows, url: url,
            simpan(sid) {
                fetch(this.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ id_siswa: sid, deskripsi: this.rows[sid].desc }),
                }).then(r => { if (!r.ok) throw new Error(); })
                  .catch(() => { if (window.showToast) showToast('Gagal menyimpan deskripsi.', 'error'); });
            },
        };
    }
</script>
@endpush
