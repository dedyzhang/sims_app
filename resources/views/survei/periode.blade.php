@extends('layouts.app')
@section('title','Detail Survei')
@section('content')
@include('survei.gaya')
<div class="survei-detail">
    <header class="survei-detail-kepala">
        <div>
            <a href="{{ route('survei.index') }}" class="survei-detail-kembali">← Daftar survei</a>
            <h1 class="page-title">{{ $periode->judul }}</h1>
            <p class="survei-detail-waktu">{{ $periode->mulai->format('d/m/Y H:i') }} – {{ $periode->selesai->format('d/m/Y H:i') }}</p>
        </div>
        <div class="survei-detail-status">
            <span class="survei-sesi-status {{ $periode->terbuka() ? 'survei-sesi-status-jalan' : '' }}">{{ $periode->ditutup ? 'Ditutup' : ($periode->terbuka() ? 'Terbuka' : 'Belum terbuka / berakhir') }}</span>
            @if($periode->wajib_kios)
                <span class="survei-sesi-status">Wajib SIMS Kios</span>
            @elseif($periode->wajib_seb)
                <span class="survei-sesi-status">Wajib Safe Exam Browser</span>
            @endif
        </div>
    </header>

    <div class="survei-detail-ruang {{ $memandu && $periode->terbuka() ? 'survei-detail-ruang-sesi' : '' }}">
        @if($memandu && $periode->terbuka())
            @include('survei.sesi')
        @endif
        <aside class="card survei-detail-bagikan" aria-labelledby="survei-judul-bagikan">
            <h2 id="survei-judul-bagikan">Bagikan ke siswa</h2>
            <div class="survei-detail-qr" role="img" aria-label="QR tautan masuk survei">
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(4)->generate(route('survei.masuk',$periode)) !!}
            </div>
            <p>Pindai QR, lalu masuk dengan akun SIMS. @if($periode->wajib_token) Gunakan token sesi untuk kelas terpilih. @endif</p>
            <a class="survei-detail-tautan" href="{{ route('survei.masuk',$periode) }}" target="_blank" rel="noopener">Buka tautan survei ↗</a>
            <details class="survei-detail-alamat">
                <summary>Lihat alamat tautan</summary>
                <p>{{ route('survei.masuk',$periode) }}</p>
            </details>
            <small>Tautan terbuka di tab baru; pemantauan tetap di sini.</small>
        </aside>
    </div>

    @php($sasaranPerKelas = $targets->groupBy('kelas_id'))
    <details class="card survei-detail-sasaran" open>
        <summary>
            <span>Sasaran survei <small>{{ $targets->count() }} sasaran · {{ $sasaranPerKelas->count() }} kelas</small></span>
            <span class="survei-detail-petunjuk">Rincian per kelas</span>
        </summary>
        <div class="survei-detail-sasaran-isi">
            <p>Pilih kelas untuk melihat guru, mata pelajaran, dan jumlah respons. Hasil tersedia setelah survei ditutup, minimal lima siswa mengisi.</p>
            <div class="survei-sasaran-grid">
                @forelse($sasaranPerKelas as $daftar)
                    <details class="survei-sasaran-kelas">
                        <summary>
                            <strong>{{ $daftar->first()->kelas_nama }}</strong>
                            <span>{{ $daftar->count() }} sasaran</span>
                        </summary>
                        <ul>
                            @foreach($daftar as $t)
                                <li>
                                    <div>
                                        <strong>{{ $t->guru_nama }}</strong>
                                        <span>{{ $t->jenis === 'wali' ? 'Wali kelas' : $t->pelajaran_nama }}</span>
                                    </div>
                                    <div class="survei-sasaran-respons">
                                        <span>{{ $t->respons_count }} respons</span>
                                        @if(($periode->ditutup || now()->greaterThan($periode->selesai)) && (auth()->user()->canViewSurveiResults() || (auth()->user()->canViewOwnSurveiResults() && $t->guru_id === auth()->user()->guru?->uuid)))
                                            <a class="survei-detail-tautan" href="{{ route('survei.hasil',$t) }}">Lihat hasil</a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @empty
                    <p>Belum ada sasaran survei.</p>
                @endforelse
            </div>
            <small class="survei-sasaran-privasi">Jumlah respons tidak menampilkan identitas siswa.</small>
        </div>
    </details>

    {{-- Sama dengan otorisasi SurveiPembelajaranController::tutup(): hanya admin; periode bertoken hanya pembuatnya. --}}
    @if(!$periode->berakhir() && auth()->user()->isAdmin() && ($pembuat || !$periode->wajib_token))
        <form class="survei-detail-tutup" action="{{ route('survei.tutup',$periode) }}" method="post" onsubmit="return confirm('Tutup seluruh periode survei? Semua sesi akan berakhir dan siswa tidak dapat lagi mengirim jawaban.')">
            @csrf
            <button class="rounded-lg bg-red-700 text-white px-4 py-2">Tutup periode survei</button>
        </form>
    @endif
    @if($periode->berakhir() && (auth()->user()->canViewSurveiResults() || auth()->user()->canViewOwnSurveiResults()))
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('survei.analisis', $periode) }}" class="btn-primary">Lihat analisis per guru</a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('survei.export',$periode) }}" class="survei-detail-tautan">Unduh rekap CSV</a>
            @endif
            @if($periode->bolehMelihatIdentitas(auth()->user()))
                <a href="{{ route('survei.responden', $periode) }}" class="survei-detail-tautan">Lihat daftar responden rahasia</a>
            @endif
        </div>
    @endif
</div>
@endsection
