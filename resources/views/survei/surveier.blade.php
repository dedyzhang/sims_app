@extends('layouts.app')
@section('title','Papan Surveyor')
@include('survei.gaya')
@section('content')
@php
    $petugas = auth()->user();
    $sesiBerjalan = $terbuka->filter(fn ($periode) => $periode->sesi->isNotEmpty())->count();
    $langkah = [
        ['Buka periode', 'Pilih survei yang sedang terbuka di daftar bawah.'],
        ['Centang kelas', 'Kelas yang dicentang dimulai sekarang. Tidak ada batas durasi.'],
        ['Tampilkan QR', 'Siswa memindai QR, atau membuka tautan yang sama.'],
        ['Akun siswa', 'Setiap siswa masuk dengan akun SIMS-nya sendiri.'],
        ['Bacakan token', 'Buka Tampilkan token, lalu bacakan enam digit itu.'],
        ['Satu kali isi', 'Satu formulir untuk semua mapel dan wali kelas. Satu siswa satu kali.'],
        ['Tekan Siap', 'Waktu mulai dan Siap yang menutup sesi. Kelas berikutnya mulai sesi baru.'],
    ];
@endphp
<div class="survei-papan mx-auto max-w-5xl">
    <header class="survei-hero">
        <div class="survei-identitas">
            <div class="survei-logo" aria-hidden="true">
                <i data-lucide="clipboard-check"></i>
            </div>
            <div>
                <p class="survei-sekolah">{{ $petugas->roleLabel() }} · {{ $petugas->displayName() }}</p>
                <h1>Papan Surveyor</h1>
            </div>
        </div>
        <p>Anda memandu pengisian di kelas. Jawaban siswa tetap rahasia. Anda tidak melihat nama pengisi.</p>
        <div class="survei-papan-statistik">
            <div>
                <strong>{{ $terbuka->count() }}</strong>
                <span>Periode terbuka</span>
            </div>
            <div>
                <strong>{{ $sesiBerjalan }}</strong>
                <span>Sesi sedang berjalan</span>
            </div>
            <div>
                <strong>{{ $rekap->count() }}</strong>
                <span>Rekap yang sudah berakhir</span>
            </div>
        </div>
    </header>

    <section class="card survei-papan-periode" aria-labelledby="alur-surveyor">
        <div class="survei-papan-kepala">
            <div>
                <h2 id="alur-surveyor" class="font-bold">Dari surveyor ke siswa</h2>
                <p>Ikuti urutan ini di setiap gelombang kelas. Sesi tidak memakai durasi menit.</p>
            </div>
        </div>
        <ol class="survei-papan-langkah">
            @foreach($langkah as $index => $item)
                <li>
                    <span class="survei-papan-nomor">{{ $index + 1 }}</span>
                    <div>
                        <strong>{{ $item[0] }}</strong>
                        <p>{{ $item[1] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="space-y-3" aria-labelledby="periode-surveyor">
        <div class="survei-papan-kepala">
            <div>
                <h2 id="periode-surveyor" class="text-lg font-bold">Periode yang bisa dipandu</h2>
                <p>Centang kelas di dalam periode. Gelombang berikutnya baru bisa dimulai setelah Siap.</p>
            </div>
        </div>
        <div class="survei-papan-daftar">
            @forelse($terbuka as $periode)
                @php
                    $sesi = $periode->sesi->first();
                    $namaKelas = $periode->targets->unique('kelas_id')->mapWithKeys(fn ($target) => [$target->kelas_id => $target->kelas_nama]);
                    $kelasBerjalan = $sesi ? $namaKelas->only($sesi->kelas_ids ?? [])->filter()->values() : collect();
                    $mode = $periode->wajib_kios ? 'SIMS Kios' : ($periode->wajib_seb ? 'Safe Exam Browser' : 'Token kelas');
                @endphp
                <article class="card survei-papan-periode">
                    <div class="survei-papan-baris">
                        <div>
                            <h3>{{ $periode->judul }}</h3>
                            <p class="survei-papan-waktu">{{ $periode->mulai->format('d/m/Y H:i') }} – {{ $periode->selesai->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="survei-papan-chip {{ $sesi ? 'survei-papan-chip-jalan' : 'survei-papan-chip-hangat' }}">
                            {{ $sesi ? 'Sesi berjalan' : 'Siap dipandu' }}
                        </span>
                    </div>
                    <div class="survei-papan-meta">
                        <span class="survei-papan-chip">{{ $namaKelas->count() }} kelas</span>
                        <span class="survei-papan-chip">{{ $mode }}</span>
                        @unless($sesi)
                            <span class="survei-papan-chip">Belum ada sesi</span>
                        @endunless
                    </div>
                    @if($namaKelas->isNotEmpty())
                        <div class="survei-papan-kelas" aria-label="Kelas pada periode ini">
                            @foreach($namaKelas as $nama)
                                <span class="survei-papan-chip">{{ $nama }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if($sesi)
                        <p class="survei-papan-sesi">
                            Dimulai {{ $sesi->created_at->format('d/m/Y H:i') }}.
                            @if($kelasBerjalan->isNotEmpty())
                                Kelas yang sedang mengisi: {{ $kelasBerjalan->implode(', ') }}.
                            @endif
                            Tekan Siap di dalam periode bila gelombang ini selesai.
                        </p>
                    @endif
                    <div>
                        <a class="survei-papan-aksi" href="{{ route('survei.periode', $periode) }}">
                            <i data-lucide="qr-code" class="w-4 h-4" aria-hidden="true"></i>
                            Buka sesi dan QR
                        </a>
                    </div>
                </article>
            @empty
                <div class="card survei-papan-kosong">
                    <strong>Belum ada periode yang sedang terbuka.</strong>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Periode muncul di sini setelah admin membuat survei dan jadwalnya sedang berlangsung.</p>
                </div>
            @endforelse
        </div>
    </section>

    @if($rekap->isNotEmpty())
        <section class="space-y-3" aria-labelledby="rekap-surveyor">
            <h2 id="rekap-surveyor" class="text-lg font-bold">Rekap yang sudah berakhir</h2>
            <div class="survei-papan-daftar">
                @foreach($rekap as $periode)
                    <article class="card survei-papan-periode">
                        <div class="survei-papan-baris">
                            <div>
                                <h3>{{ $periode->judul }}</h3>
                                <p class="survei-papan-waktu">Berakhir {{ $periode->selesai->format('d/m/Y H:i') }}</p>
                            </div>
                            <span class="survei-papan-chip">Ditutup</span>
                        </div>
                        <div>
                            <a class="survei-papan-aksi survei-papan-aksi-tenang" href="{{ route('survei.analisis', $periode) }}">
                                {{ $lihatSemua ? 'Lihat rekap dan analisis guru' : 'Lihat rekap saya' }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
