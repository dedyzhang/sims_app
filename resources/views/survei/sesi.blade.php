@include('survei.gaya')
@php
    $rombongan = $kelasSesi->map(function ($nama, $id) {
        preg_match('/\d+/', (string) $nama, $angka);

        return ['id' => $id, 'nama' => $nama, 'tingkat' => $angka[0] ?? '0'];
    })->groupBy('tingkat')->sortKeys();
    $kelasAktif = $sesi ? $kelasSesi->only(array_map(strval(...), (array) $sesi->kelas_ids)) : collect();
    $kelasTertutup = $sesi ? $kelasSesi->except($kelasAktif->keys()->all()) : collect();
@endphp
<section class="card survei-sesi" aria-labelledby="judul-sesi">
    <div class="survei-sesi-kepala">
        <div>
            <p class="survei-sesi-kicker">Pengaktifan kelas</p>
            <h2 id="judul-sesi">Sesi pengisian</h2>
        </div>
        @if($sesi)
            <span class="survei-sesi-status survei-sesi-status-jalan">Berjalan</span>
        @else
            <span class="survei-sesi-status">Belum dimulai</span>
        @endif
    </div>

    @if($errors->any())
        <p class="survei-sesi-alert" role="alert">{{ $errors->first() }}</p>
    @endif
    @if($periode->wajib_kios && ! $kiosSiap)
        <p>Daftarkan perangkat SIMS Kios sebelum memulai sesi.</p>
    @elseif($periode->wajib_seb && ! $sebSiap)
        <p>Mode Safe Exam Browser dipilih. Hubungi pengelola SIMS untuk menyiapkan konfigurasi sebelum memulai sesi.</p>
    @elseif($sesi)
        <p>Dimulai {{ $sesi->created_at->format('d/m/Y H:i') }}. Sesi berakhir ketika tombol Siap ditekan.</p>
        <div class="survei-sesi-kolom">
            <div class="survei-sesi-panel survei-sesi-panel-buka">
                <h3>Boleh mengisi</h3>
                <p>Hanya kelas ini yang dapat membuka borang dan mengirim jawaban.</p>
                <ul class="survei-sesi-chip">
                    @foreach($kelasAktif as $namaKelas)
                        <li>{{ $namaKelas }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="survei-sesi-panel survei-sesi-panel-tutup">
                <h3>Tertutup pada sesi ini</h3>
                <p>Kelas ini tidak dapat mengisi, meskipun mengetahui token.</p>
                @if($kelasTertutup->isEmpty())
                    <p>Semua kelas periode ini sedang dalam sesi.</p>
                @else
                    <ul class="survei-sesi-chip">
                        @foreach($kelasTertutup as $namaKelas)
                            <li>{{ $namaKelas }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <details class="survei-sesi-token">
            <summary>Tampilkan token</summary>
            <p class="survei-sesi-kode">{{ $sesi->token }}</p>
            <p>Bacakan hanya kepada kelas yang boleh mengisi.</p>
        </details>
        <div class="survei-sesi-aksi">
            <form method="post" action="{{ route('survei.sesi.token', [$periode, $sesi]) }}"
                onsubmit="return confirm('Ganti token? Siswa yang sedang mengisi perlu membuka formulir dengan token baru.')">
                @csrf
                <button type="submit" class="survei-sesi-tombol">Ganti token</button>
            </form>
            <form method="post" action="{{ route('survei.sesi.akhiri', [$periode, $sesi]) }}"
                onsubmit="return confirm('Tandai siap? Siswa yang belum mengirim tidak dapat mengirim setelah sesi berakhir.')">
                @csrf
                <button type="submit" class="survei-sesi-tombol survei-sesi-siap">Siap</button>
            </form>
        </div>
    @else
        @if($pembuat && !$periode->wajib_seb && !$periode->wajib_kios)
            <form method="post" action="{{ route('survei.seb.wajib', $periode) }}"
                onsubmit="return confirm('Wajibkan Safe Exam Browser untuk periode ini?')">
                @csrf
                <button type="submit" class="survei-sesi-tombol">Wajibkan Safe Exam Browser</button>
            </form>
        @endif
        <form method="post" action="{{ route('survei.sesi.mulai', $periode) }}" class="survei-sesi-form"
            x-data="{
                terpilih: 0,
                hitung() {
                    this.terpilih = this.$root.querySelectorAll('input[name=\'kelas_sesi[]\']:checked').length
                },
                centang(event, nilai) {
                    event.target.closest('[data-tingkat]').querySelectorAll('input[type=checkbox]').forEach((el) => { el.checked = nilai })
                    this.hitung()
                }
            }"
            x-init="hitung()">
            @csrf
            <fieldset @change="hitung()">
                <legend>Centang kelas yang dimulai sekarang</legend>
                <p>Hanya kelas yang dicentang yang dapat mengisi borang. Kelas lain tetap tertutup sampai sesi mereka dimulai.</p>
                <div class="survei-sesi-kelas-grid">
                @foreach($rombongan as $tingkat => $daftar)
                    <div class="survei-sesi-tingkat" data-tingkat="{{ $tingkat }}">
                        <div class="survei-sesi-tingkat-kepala">
                            <h3>Kelas {{ $tingkat }}</h3>
                            <button type="button" aria-label="Centang semua kelas {{ $tingkat }}" @click="centang($event, true)">Semua</button>
                            <button type="button" aria-label="Lepas pilihan kelas {{ $tingkat }}" @click="centang($event, false)">Lepas</button>
                        </div>
                        <div class="survei-sesi-petak">
                            @foreach($daftar as $kelas)
                                <label>
                                    <input type="checkbox" name="kelas_sesi[]" value="{{ $kelas['id'] }}" @checked(in_array($kelas['id'], (array) old('kelas_sesi'), true))>
                                    <span>{{ $kelas['nama'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                </div>
            </fieldset>
            <div class="survei-sesi-mulai">
                <p class="survei-sesi-mulai-catatan">
                    <span x-show="terpilih === 0">Centang minimal satu kelas untuk memulai.</span>
                    <span x-show="terpilih > 0" x-cloak><span x-text="terpilih"></span> kelas akan dibuka sekarang. Kelas lain tetap tertutup.</span>
                </p>
                <button type="submit" class="survei-sesi-mulai-tombol" :disabled="terpilih === 0">Mulai sesi</button>
            </div>
        </form>
    @endif
    @if($pembuat && !$sesi && !$periode->wajib_kios)
        <form method="post" action="{{ route('survei.kios.wajib', $periode) }}"
            onsubmit="return confirm('Wajibkan SIMS Kios untuk periode ini?')">
            @csrf
            <button type="submit" class="survei-sesi-tombol">Wajibkan SIMS Kios</button>
        </form>
    @endif

    <details class="survei-sesi-rekap">
        <summary>Sudah mengirim di periode ini <strong>{{ $sudahMengirim->sum() }} siswa</strong></summary>
        <ul>
            @foreach($kelasSesi as $id => $namaKelas)
                <li>{{ $namaKelas }} · {{ $sudahMengirim->get($id, 0) }} siswa</li>
            @endforeach
        </ul>
        <a href="{{ route('survei.periode', $periode) }}">Perbarui jumlah</a>
    </details>
</section>
