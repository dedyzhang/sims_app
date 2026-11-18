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
        <div class="survei-detail-status flex items-center gap-2">
            <span class="survei-sesi-status {{ $periode->terbuka() ? 'survei-sesi-status-jalan' : '' }}">{{ $periode->ditutup ? 'Ditutup' : ($periode->terbuka() ? 'Terbuka' : 'Belum terbuka / berakhir') }}</span>
            @if($pembuat || auth()->user()->isAdmin())
                <form method="post" action="{{ route('survei.destroy', $periode) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus seluruh periode survei “{{ $periode->judul }}”?\n\nSemua data sasaran, sesi token, dan seluruh jawaban siswa yang telah masuk akan DIHAPUS PERMANEN.')" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition">
                        Hapus Periode
                    </button>
                </form>
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

    @if($pembuat || auth()->user()->isAdmin())
    <details class="card survei-detail-sasaran mt-4">
        <summary>
            <span>Butir Pertanyaan Periode Ini <small>{{ count($periode->butir('mapel')) }} butir mapel · {{ count($periode->butir('wali')) }} butir wali</small></span>
            <span class="survei-detail-petunjuk">Klik untuk melihat / mengubah butir soal</span>
        </summary>
        <div class="survei-detail-sasaran-isi p-4">
            <form method="post" action="{{ route('survei.pertanyaan.update', $periode) }}" class="space-y-4"
                  x-data="{
                      tab: 'mapel',
                      mapelList: @js($periode->butir('mapel')),
                      waliList: @js($periode->butir('wali')),
                      karakterList: @js($periode->pilihanKarakter()),
                      dibalikMapel: @js($periode->butirNegatif('mapel')),
                      dibalikWali: @js($periode->butirNegatif('wali')),
                      tambah(t) {
                          if (t === 'mapel') this.mapelList.push('');
                          else if (t === 'wali') this.waliList.push('');
                          else this.karakterList.push('');
                      },
                      hapus(t, i) {
                          if (t === 'mapel') {
                              if (this.mapelList.length <= 1) { alert('Minimal 1 butir pertanyaan.'); return; }
                              this.mapelList.splice(i, 1);
                              this.dibalikMapel = this.dibalikMapel.filter(idx => idx !== i).map(idx => idx > i ? idx - 1 : idx);
                          } else if (t === 'wali') {
                              if (this.waliList.length <= 1) { alert('Minimal 1 butir pertanyaan.'); return; }
                              this.waliList.splice(i, 1);
                              this.dibalikWali = this.dibalikWali.filter(idx => idx !== i).map(idx => idx > i ? idx - 1 : idx);
                          } else {
                              if (this.karakterList.length <= 1) { alert('Minimal 1 pilihan karakter.'); return; }
                              this.karakterList.splice(i, 1);
                          }
                      },
                      toggleNegatif(t, i) {
                          if (t === 'mapel') {
                              const pos = this.dibalikMapel.indexOf(i);
                              if (pos !== -1) this.dibalikMapel.splice(pos, 1);
                              else this.dibalikMapel.push(i);
                          } else {
                              const pos = this.dibalikWali.indexOf(i);
                              if (pos !== -1) this.dibalikWali.splice(pos, 1);
                              else this.dibalikWali.push(i);
                          }
                      },
                      isNegatif(t, i) {
                          return t === 'mapel' ? this.dibalikMapel.includes(i) : this.dibalikWali.includes(i);
                      }
                  }">
                @csrf
                @method('PUT')
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2 flex-wrap gap-2">
                    <p class="text-xs text-slate-500">Ubah butir pertanyaan atau pilihan dropdown karakter untuk periode survei ini.</p>
                    <div class="inline-flex rounded-lg p-1 bg-slate-200/80 dark:bg-slate-800 flex-wrap gap-1">
                        <button type="button" @click="tab = 'mapel'" :class="tab === 'mapel' ? 'bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-400'" class="px-3 py-1 text-xs rounded-md">Guru Mapel (<span x-text="mapelList.length"></span>)</button>
                        <button type="button" @click="tab = 'wali'" :class="tab === 'wali' ? 'bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-400'" class="px-3 py-1 text-xs rounded-md">Wali Kelas (<span x-text="waliList.length"></span>)</button>
                        <button type="button" @click="tab = 'karakter'" :class="tab === 'karakter' ? 'bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-400'" class="px-3 py-1 text-xs rounded-md">Dropdown Karakter (<span x-text="karakterList.length"></span>)</button>
                    </div>
                </div>

                <div x-show="tab === 'mapel'" class="space-y-2">
                    <template x-for="(text, index) in mapelList" :key="'m-' + index">
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-xs font-bold text-slate-500 text-right shrink-0" x-text="index + 1 + '.'"></span>
                            <input type="text" :name="'pertanyaan_mapel[' + index + ']'" x-model="mapelList[index]" required class="form-input text-xs flex-1">
                            <label class="text-[11px] text-slate-500 flex items-center gap-1 shrink-0 cursor-pointer">
                                <input type="checkbox" :name="'dibalik_mapel[]'" :value="index" :checked="isNegatif('mapel', index)" @change="toggleNegatif('mapel', index)">
                                <span>Skor dibalik</span>
                            </label>
                            <button type="button" @click="hapus('mapel', index)" class="text-rose-500 hover:text-rose-700 px-2 text-xs font-bold" title="Hapus butir ini">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="tambah('mapel')" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline mt-1">+ Tambah Butir Soal Mapel</button>
                </div>

                <div x-show="tab === 'wali'" class="space-y-2" x-cloak>
                    <template x-for="(text, index) in waliList" :key="'w-' + index">
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-xs font-bold text-slate-500 text-right shrink-0" x-text="index + 1 + '.'"></span>
                            <input type="text" :name="'pertanyaan_wali[' + index + ']'" x-model="waliList[index]" required class="form-input text-xs flex-1">
                            <label class="text-[11px] text-slate-500 flex items-center gap-1 shrink-0 cursor-pointer">
                                <input type="checkbox" :name="'dibalik_wali[]'" :value="index" :checked="isNegatif('wali', index)" @change="toggleNegatif('wali', index)">
                                <span>Skor dibalik</span>
                            </label>
                            <button type="button" @click="hapus('wali', index)" class="text-rose-500 hover:text-rose-700 px-2 text-xs font-bold" title="Hapus butir ini">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="tambah('wali')" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline mt-1">+ Tambah Butir Soal Wali</button>
                </div>

                <div x-show="tab === 'karakter'" class="space-y-2" x-cloak>
                    <p class="text-xs text-slate-500 mb-2">Pilihan Dropdown: "Menurut kamu, guru ini termasuk guru yang ..." (di halaman terakhir setiap guru mapel & wali)</p>
                    <template x-for="(text, index) in karakterList" :key="'k-' + index">
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-xs font-bold text-amber-600 dark:text-amber-400 text-right shrink-0" x-text="index + 1 + '.'"></span>
                            <input type="text" :name="'opsi_karakter[' + index + ']'" x-model="karakterList[index]" required class="form-input text-xs flex-1 rounded-md" placeholder="Contoh: Sangat sabar dan ramah...">
                            <button type="button" @click="hapus('karakter', index)" class="text-rose-500 hover:text-rose-700 px-2 text-xs font-bold" title="Hapus pilihan ini">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="tambah('karakter')" class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline mt-1">+ Tambah Pilihan Karakter</button>
                </div>

                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <label class="text-xs text-slate-500 flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="simpan_default" value="1">
                        <span>Simpan susunan soal ini juga sebagai template default sekolah</span>
                    </label>
                    <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded-lg text-xs font-semibold hover:bg-blue-800">
                        Simpan Perubahan Soal
                    </button>
                </div>
            </form>
        </div>
    </details>
    @endif

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
