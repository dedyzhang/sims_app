@extends('layouts.app')
@section('title','Isi Survei Pembelajaran')
@section('hide_page_footer', '1')
@include('survei.gaya')
@section('content')
<div class="survei-page mx-auto max-w-4xl space-y-5" data-daftar="{{ route('survei.index') }}" data-seb="{{ $periode->wajib_seb ? '1' : '0' }}"
    @if($tantanganKios) data-periode-kios="{{ $periode->uuid }}" data-tantangan-kios="{{ $tantanganKios }}" @endif>
    <header class="survei-hero">
        <div class="survei-identitas">
            <div class="survei-logo">
                @if($sekolahLogoUrl)
                    <img src="{{ $sekolahLogoUrl }}" alt="" width="52" height="52">
                @else
                    <i data-lucide="clipboard-list" class="h-7 w-7" aria-hidden="true"></i>
                @endif
            </div>
            <div>
                <p class="survei-sekolah">{{ $namaSekolah }} · {{ $targets->first()->kelas_nama }}</p>
                <h1>{{ $periode->judul }}</h1>
            </div>
        </div>
        <p>Jawablah sesuai pengalamanmu semester ini. Pilih <strong>Tidak tahu</strong> jika kamu belum mengalami hal tersebut.</p>
        <div class="survei-cakupan">
            @if($targets->contains('jenis', 'mapel'))
                <span>{{ $targets->where('jenis', 'mapel')->unique('pelajaran_id')->count() }} mata pelajaran</span>
            @endif
            @if($targets->contains('jenis', 'wali'))
                <span>Wali kelas</span>
            @endif
        </div>
    </header>

    @include('survei.privasi')

    <div id="survei-mode-fokus" class="card survei-akses" data-sudah-mulai="{{ $errors->any() ? '1' : '0' }}">
        <h2 id="survei-fokus-judul" class="font-semibold">{{ $errors->any() ? 'Lanjutkan survei' : 'Mulai survei' }}</h2>
        <p id="survei-fokus-pesan" class="text-sm" role="status">{{ $periode->wajib_seb || $periode->wajib_kios ? 'Perangkat akan dibuka setelah semua jawaban berhasil dikirim.' : 'Siapkan waktu untuk menyelesaikan semua pertanyaan wajib dalam layar penuh.' }}</p>
        <button id="survei-fokus-mulai" type="button" class="survei-tombol survei-aksi-utama" disabled>
            {{ $errors->any() ? 'Lanjutkan pengisian' : ($periode->wajib_seb || $periode->wajib_kios ? 'Mulai survei' : 'Mulai layar penuh') }}
        </button>
        <a id="survei-fokus-kembali" href="{{ route('survei.index') }}" class="survei-kembali" @if($periode->wajib_seb || $periode->wajib_kios) hidden @endif>Kembali ke daftar survei</a>
        <noscript><p>Aktifkan JavaScript untuk mulai mengisi survei.</p></noscript>
    </div>

    <p class="survei-bantuan">Jika ada hal serius yang membuatmu merasa tidak aman, segera temui guru BK atau Waka Kesiswaan.</p>

    <div id="survei-pengisian" class="space-y-5" hidden inert>
        <nav aria-label="Mata pelajaran" class="card survei-pemilih-mapel space-y-3 p-4">
            <h2 class="font-semibold">Mata pelajaran</h2>
            <ul class="survei-daftar-mapel">
                @foreach($targets as $target)
                    <li>
                        <button type="button" class="survei-bagian-link" data-bagian="target-{{ $target->uuid }}" aria-pressed="false">
                            <span>{{ $target->jenis === 'wali' ? 'Wali Kelas' : $target->pelajaran_nama }}</span>
                            <span data-status-bagian></span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

    @if($errors->any())
        <div class="card border border-red-200 p-4 text-red-700" role="alert">Periksa kembali jawabanmu. {{ $errors->first() }}</div>
    @endif

    <form action="{{ route('survei.kirim-batch',$periode) }}" method="post" class="space-y-4" novalidate>
        @csrf
        @if($tandaSesi)
            <input type="hidden" name="tanda_sesi" value="{{ $tandaSesi }}">
        @endif
        <div class="survei-ruang-soal">
        <div class="survei-kartu-soal min-w-0">
        <p id="survei-nomor-aktif" class="survei-nomor-aktif" tabindex="-1"></p>
        @foreach($targets as $target)
            @php
                $questions = $periode->butir($target->jenis);
                $comments = $periode->pertanyaanTerbuka($target->jenis);
                $targetKendala = $periode->pilihanKendala($target->jenis);
                $adaKendalaWali = old("kendala.{$target->uuid}.0") === 'ya';
                $pilihanRingkas = \Illuminate\Support\Arr::only($targetKendala, ['penjelasan', 'tugas', 'tidak_ada', 'lainnya']);
                $jawabanKendala = collect(array_keys($pilihanRingkas))->first(
                    fn ($value) => in_array($value, (array) old("kendala.{$target->uuid}"), true)
                );
            @endphp
            <section class="card survei-bagian space-y-5 p-4 sm:p-6" id="target-{{ $target->uuid }}" x-data="{ adaKendalaWali: @js($adaKendalaWali) }" hidden>
                <div class="survei-bagian-judul">
                    <h2 class="text-xl font-bold">{{ $target->jenis === 'wali' ? 'Wali Kelas' : $target->pelajaran_nama }}</h2>
                    <p class="mt-1 text-sm">{{ $target->guru_nama }}</p>
                </div>

                    @foreach($questions as $index => $question)
                        <fieldset class="survei-langkah" data-langkah hidden>
                            <legend class="survei-teks-soal font-semibold">{{ $question }}</legend>
                            <div class="mt-4 grid gap-2">
                                @foreach($skala as $value => $label)
                                    <label class="survei-pilihan">
                                        <input required type="radio" name="jawaban[{{ $target->uuid }}][{{ $index }}]" value="{{ $value }}" @checked(in_array(old("jawaban.{$target->uuid}.{$index}"), [$value, (string) $value], true))>
                                        <span class="survei-huruf" aria-hidden="true">{{ chr(65 + $loop->index) }}</span>
                                        <span>{{ $target->jenis === 'wali' && $value === 0 ? 'Tidak tahu / belum pernah' : $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                <div class="survei-langkah space-y-4" data-langkah data-masukan hidden>
                @if($target->jenis === 'mapel')
                    <fieldset class="rounded-lg border border-slate-200 p-4" x-data="{ kendalaUtama: @js($jawabanKendala) }">
                        <legend class="font-semibold">Apa kendala utamamu di pelajaran ini? <span class="font-normal text-slate-500">(opsional)</span></legend>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach($pilihanRingkas as $value => $label)
                                <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input type="radio" name="kendala[{{ $target->uuid }}][]" value="{{ $value }}" @checked($jawabanKendala === $value) @change="kendalaUtama = $event.target.value">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @php($teksLainnya = old("kendala_lainnya.{$target->uuid}"))
                        <label class="mt-3 block text-sm" x-show="kendalaUtama === 'lainnya'" x-cloak>Tuliskan kendalamu
                            <input class="form-input mt-2" type="text" name="kendala_lainnya[{{ $target->uuid }}]" value="{{ is_string($teksLainnya) ? $teksLainnya : '' }}" maxlength="1000" :disabled="kendalaUtama !== 'lainnya'">
                        </label>
                    </fieldset>
                @else
                    <fieldset class="rounded-lg border border-slate-200 p-4">
                        <legend class="font-semibold">Apakah ada kendala dengan wali kelasmu?</legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($targetKendala as $value => $label)
                                <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input required type="radio" name="kendala[{{ $target->uuid }}][0]" value="{{ $value }}" @checked(old("kendala.{$target->uuid}.0") === $value) @change="adaKendalaWali = $event.target.value === 'ya'">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <div class="space-y-3" @if($target->jenis === 'wali') x-show="adaKendalaWali" @endif>
                    <p class="text-sm text-slate-600">Hindari menyebut nama siswa atau menuliskan informasi pribadi.</p>
                    @foreach($comments as $index => $question)
                        @php($komentarLama = old("komentar.{$target->uuid}.{$index}"))
                        <label class="block rounded-lg border border-slate-200 p-4 font-semibold">{{ $question }} <span class="font-normal text-slate-500">(boleh dikosongkan)</span>
                            <textarea class="form-input mt-2" name="komentar[{{ $target->uuid }}][{{ $index }}]" maxlength="1000" rows="3">{{ is_string($komentarLama) ? $komentarLama : '' }}</textarea>
                        </label>
                    @endforeach
                </div>
                </div>
            </section>
        @endforeach
        </div>

        <details class="card survei-panel-nomor p-4" aria-label="Daftar nomor soal">
            <summary>
                <strong>Nomor soal</strong>
                <span id="survei-ringkas-nomor"></span>
            </summary>
            <div class="mt-3 flex flex-wrap items-center gap-2" role="group" aria-label="Ukuran teks pertanyaan">
                <span class="text-sm">Ukuran teks</span>
                <button type="button" class="survei-tombol-netral" data-ukuran="kecil" aria-label="Teks kecil" aria-pressed="false">A</button>
                <button type="button" class="survei-tombol-netral" data-ukuran="sedang" aria-label="Teks sedang" aria-pressed="true">A</button>
                <button type="button" class="survei-tombol-netral" data-ukuran="besar" aria-label="Teks besar" aria-pressed="false">A</button>
            </div>
            <div id="survei-daftar-nomor" class="survei-daftar-nomor"></div>
            <p class="mt-3 text-xs">Huruf menunjukkan jawabanmu. Garis biru menandai soal aktif.</p>
        </details>
        </div>

        <div class="survei-kirim">
            <div class="survei-progres">
                <p id="survei-progres-label" role="status">Menghitung jawaban wajib…</p>
                <progress id="survei-progres" aria-label="Progres jawaban wajib" value="0" max="1"></progress>
            </div>
            <div class="survei-navigasi-soal" aria-label="Navigasi pertanyaan">
                <button type="button" id="survei-sebelumnya" class="survei-tombol-netral" aria-label="Soal sebelumnya">‹ <span>Sebelumnya</span></button>
                <button type="button" id="survei-berikutnya" class="survei-tombol">Berikutnya</button>
            </div>
            <button type="submit" class="survei-tombol" disabled>Kirim semua jawaban</button>
            <p id="survei-kirim-pesan" class="survei-kirim-pesan" role="alert" hidden></p>
        </div>
    </form>
    <dialog id="survei-konfirmasi" class="survei-konfirmasi" aria-labelledby="survei-konfirmasi-judul">
        <h2 id="survei-konfirmasi-judul" class="font-bold">Kirim semua jawaban?</h2>
        <p>Jawaban tidak dapat dibuka kembali atau diubah setelah dikirim.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button id="survei-periksa" type="button" class="survei-tombol-netral" autofocus>Periksa lagi</button>
            <button id="survei-kirim-final" type="button" class="survei-tombol">Ya, kirim</button>
        </div>
    </dialog>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/survei-fokus.js') }}?v={{ filemtime(public_path('js/survei-fokus.js')) }}" defer></script>
@endpush
