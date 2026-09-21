@extends('layouts.app')
@section('title', 'Jadwal Susulan - ' . $ujian->judul)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> / 
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / 
            Susulan
        </nav>
        <h1 class="page-title">Jadwal Susulan Ujian</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Berikan akses ujian kepada siswa tertentu pada tanggal khusus, mengabaikan syarat QR code atau jadwal reguler.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="md:col-span-1">
            <div class="card p-5 sticky top-24">
                <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4">Tambah Susulan</h3>
                <form action="{{ route('ujian.susulan.store', $ujian) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Siswa</label>
                        <select name="id_siswa" required class="form-input w-full p-2 text-sm select2-siswa">
                            <option value="">-- Pilih Siswa --</option>
                            @foreach($siswas as $siswa)
                                <option value="{{ $siswa->uuid }}">{{ $siswa->nama }} ({{ $siswa->kelas->nama }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tanggal Akses</label>
                        <input type="date" name="tanggal" required class="form-input w-full p-2 text-sm" value="{{ date('Y-m-d') }}">
                        <p class="text-xs text-slate-400 mt-1">Ujian hanya akan muncul di daftar ujian siswa pada tanggal ini.</p>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="btn-primary w-full py-2.5 rounded-xl font-bold">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="card p-0 overflow-hidden">
                @if($susulans->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs text-slate-500 uppercase tracking-wider">
                                <th class="p-4 font-semibold">Nama Siswa</th>
                                <th class="p-4 font-semibold">Kelas</th>
                                <th class="p-4 font-semibold">Tanggal Akses</th>
                                <th class="p-4 font-semibold w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                            @foreach($susulans as $susulan)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/20">
                                <td class="p-4 font-medium text-slate-800 dark:text-slate-200">{{ $susulan->siswa->nama }}</td>
                                <td class="p-4 text-slate-600 dark:text-slate-400">{{ $susulan->siswa->kelas->nama }}</td>
                                <td class="p-4">
                                    @if($susulan->tanggal->isToday())
                                        <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Hari ini</span>
                                    @elseif($susulan->tanggal->isPast())
                                        <span class="text-slate-400">{{ $susulan->tanggal->translatedFormat('d M Y') }}</span>
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">{{ $susulan->tanggal->translatedFormat('d M Y') }}</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <form action="{{ route('ujian.susulan.destroy', $susulan) }}" method="POST" onsubmit="return confirmAction(this, 'Hapus jadwal susulan untuk {{ addslashes($susulan->siswa->nama) }}?', 'red')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30 rounded-lg transition" title="Hapus">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-10 text-center text-slate-400">
                    <i data-lucide="calendar-clock" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                    <p class="font-medium text-slate-600 dark:text-slate-400">Belum ada jadwal susulan</p>
                    <p class="text-sm mt-1">Tambahkan dari form di samping.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && $.fn.select2) {
        $('.select2-siswa').select2({
            placeholder: "-- Pilih Siswa --",
            allowClear: true,
            width: '100%'
        });
    }
});
</script>
@endpush
