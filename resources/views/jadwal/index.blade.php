@extends('layouts.app')
@section('title', 'Editor Jadwal')

@push('styles')
<style>
    .jtable { border-collapse: separate; border-spacing: 0; }
    .jtable th, .jtable td { border-bottom: 1px solid #eef2f7; border-right: 1px solid #eef2f7; }
    .dark .jtable th, .dark .jtable td { border-color:#293548; }
    .jtable thead th { position: sticky; top: 0; z-index: 5; background: #f8fafc; }
    .dark .jtable thead th { background:#0f172a; }
    .jtable .jam-col { position: sticky; left: 0; z-index: 4; background:#fff; }
    .dark .jtable .jam-col { background:#1e293b; }
    .jtable thead th.jam-col { z-index: 6; }
    .jcell-btn { width:100%; min-height:44px; display:flex; align-items:center; justify-content:center; text-align:center; padding:3px 4px; border-radius:9px; font-weight:700; transition:background .12s, box-shadow .12s; }
    .jcell-btn:hover { background: color-mix(in srgb, var(--cp) 10%, #fff); }
    .dark .jcell-btn:hover { background: color-mix(in srgb, var(--cp) 16%, #1e293b); }
    .jcell-btn.filled { background: color-mix(in srgb, var(--cp) 12%, #fff); color: color-mix(in srgb, var(--cp) 75%, black); }
    .dark .jcell-btn.filled { background: color-mix(in srgb, var(--cp) 22%, #1e293b); color:#e2e8f0; }
    .jcell-btn.conflict { background:#fef2f2 !important; box-shadow: inset 0 0 0 2px #ef4444; color:#dc2626; }
    .dark .jcell-btn.conflict { background: rgba(239,68,68,.18) !important; color:#fca5a5; }
    .istirahat-row td { background: repeating-linear-gradient(45deg,#fafafa,#fafafa 8px,#f4f4f5 8px,#f4f4f5 16px); }
    .dark .istirahat-row td { background:#0f172a; }
    /* Tooltip guru (popup hover) */
    #jtip { position:fixed; z-index:9999; pointer-events:none; background:#0f172a; color:#fff; border-radius:10px; padding:7px 11px; font-size:12px; line-height:1.35; box-shadow:0 12px 30px -8px rgba(0,0,0,.45); opacity:0; transition:opacity .12s; transform:translate(-50%,-100%); white-space:nowrap; }
    #jtip.show { opacity:1; }
    #jtip .jt-pel { font-weight:700; }
    #jtip .jt-guru { color:#cbd5e1; font-size:11px; margin-top:1px; display:flex; align-items:center; gap:4px; }
    #jtip::after { content:''; position:absolute; bottom:-5px; left:50%; transform:translateX(-50%); border:5px solid transparent; border-top-color:#0f172a; border-bottom:0; }
</style>
@endpush

@section('content')
<div class="space-y-4" x-data="jadwalGrid(@js($bentrok), @js($ngajarMap))">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Editor Jadwal</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Input semua kelas dalam satu hari — bentrok guru otomatis ditandai</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('jadwal.kelas') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="layout-grid" class="w-4 h-4"></i> <span class="hidden sm:inline">Per Kelas</span>
            </a>
            <a href="{{ route('jadwal.jp') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="hash" class="w-4 h-4"></i> <span class="hidden sm:inline">JP/Minggu</span>
            </a>
            <button @click="jamModal=true" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="clock" class="w-4 h-4"></i> <span class="hidden sm:inline">Atur Jam</span>
            </button>
            <button @click="genModal=true" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
                <i data-lucide="sparkles" class="w-4 h-4"></i> Auto-Generate
            </button>
        </div>
    </div>

    {{-- Day tabs --}}
    <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1 overflow-x-auto">
        @foreach(\App\Models\Jadwal::HARI as $no => $nama)
        <a href="{{ route('jadwal.index', ['hari'=>$no]) }}"
           class="flex-1 min-w-fit text-center py-2 px-4 rounded-lg text-sm font-semibold transition whitespace-nowrap {{ $hari===$no ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
            {{ $nama }}
        </a>
        @endforeach
    </div>

    @if($jamList->isEmpty())
    <div class="card p-10 text-center text-slate-400">
        <i data-lucide="clock" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada jam pelajaran.</p>
        <button @click="jamModal=true" class="text-primary hover:underline text-sm mt-1">+ Atur jam sekarang</button>
    </div>
    @elseif($kelasList->isEmpty())
    <div class="card p-10 text-center text-slate-400">
        <i data-lucide="door-open" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada kelas.</p>
        <a href="{{ route('kelas.create') }}" class="text-primary hover:underline text-sm mt-1 inline-block">+ Tambah kelas</a>
    </div>
    @else
    {{-- Grid --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto" style="max-height:70vh">
            <table class="jtable w-full text-sm">
                <thead>
                    <tr>
                        <th class="jam-col text-left px-3 py-2.5 text-xs font-bold uppercase text-slate-500 w-24">Jam</th>
                        @foreach($kelasList as $k)
                        <th class="px-1 py-2.5 text-center text-xs font-bold text-slate-600 dark:text-slate-300 w-16 min-w-[60px]">{{ $k->tingkat }}{{ $k->kelas }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($jamList as $jam)
                        @if($jam->jenis !== 'pelajaran')
                        <tr class="istirahat-row">
                            <td class="jam-col px-3 py-1.5 text-xs">
                                <span class="font-bold text-amber-600">{{ $jam->nama_khusus }}</span>
                            </td>
                            <td colspan="{{ $kelasList->count() }}" class="px-3 py-1.5 text-center text-xs text-amber-600 font-semibold">
                                <i data-lucide="{{ $jam->ikon }}" class="w-3.5 h-3.5 inline"></i> {{ $jam->nama_khusus }} &bull; {{ $jam->rentang }}
                            </td>
                        </tr>
                        @else
                        <tr>
                            <td class="jam-col px-3 py-2 align-top">
                                <p class="font-bold text-slate-700 dark:text-slate-200">Jam {{ $jam->jam_ke ?? '-' }}</p>
                                <p class="text-[11px] text-slate-400 font-mono">{{ $jam->rentang }}</p>
                            </td>
                            @foreach($kelasList as $k)
                            @php
                                $j = $cells[$jam->uuid.'|'.$k->uuid] ?? null;
                                $pnama = $j?->pelajaran?->nama ?? $j?->keterangan ?? '';
                                $short = $j?->pelajaran?->kode ?: \Illuminate\Support\Str::limit($pnama, 6, '');
                            @endphp
                            <td class="p-1 align-middle">
                                <button type="button"
                                        class="jcell-btn jcell {{ $j ? 'filled' : '' }}"
                                        data-kelas="{{ $k->uuid }}" data-kelasnama="{{ $k->tingkat }}{{ $k->kelas }}"
                                        data-jam="{{ $jam->uuid }}" data-jamlabel="Jam {{ $jam->jam_ke }} ({{ $jam->rentang }})"
                                        data-guru="{{ $j->id_guru ?? '' }}"
                                        data-pelajaran="{{ $j->id_pelajaran ?? '' }}"
                                        data-ket="{{ $j->keterangan ?? '' }}"
                                        data-pnama="{{ $pnama }}"
                                        data-gnama="{{ $j?->guru?->nama ?? '' }}"
                                        @click="openCell($event.currentTarget)">
                                    @if($j && ($j->pelajaran || $j->keterangan))
                                    <span class="text-[12.5px] leading-none truncate">{{ $short }}</span>
                                    @else
                                    <span class="text-slate-300 dark:text-slate-600 text-lg">+</span>
                                    @endif
                                </button>
                            </td>
                            @endforeach
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex items-center gap-4 text-xs text-slate-400 flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded" style="background:color-mix(in srgb,var(--cp) 20%,#fff)"></span> Terisi</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-100 ring-2 ring-rose-400"></span> Guru bentrok (mengajar 2 kelas di jam sama)</span>
    </div>
    @endif

    {{-- ===== Modal Edit Sel ===== --}}
    <div x-show="cellModal" class="modal-backdrop" x-transition @click.self="cellModal=false">
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Atur Jadwal</h3>
                    <p class="text-xs text-slate-400" x-text="form.kelasnama + ' • ' + form.jamlabel"></p>
                </div>
                <button @click="cellModal=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="form-label">Mata Pelajaran</label>
                    <select id="cellPelajaran" class="form-select">
                        <option value="">— Kosong / Istirahat —</option>
                        @foreach($pelajarans as $p)<option value="{{ $p->uuid }}">{{ $p->nama }}{{ $p->kode ? " ({$p->kode})" : '' }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label flex items-center gap-1.5">Guru Pengajar <span class="text-[10px] font-normal px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500">otomatis dari penugasan</span></label>
                    <select id="cellGuru" class="form-select">
                        <option value="">— Otomatis dari penugasan —</option>
                        @foreach($gurus as $g)<option value="{{ $g->uuid }}">{{ $g->nama }}</option>@endforeach
                    </select>
                    <p class="text-xs text-rose-500 mt-1.5" x-show="guruHint" x-text="guruHint"></p>
                    <p class="text-xs text-amber-600 mt-1.5 hidden" id="cellWarn"><i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> Guru ini sudah mengajar di kelas lain pada jam ini.</p>
                </div>
                <div>
                    <label class="form-label">Keterangan (opsional)</label>
                    <input type="text" id="cellKet" maxlength="50" class="form-input" placeholder="Mis. Upacara, Pramuka">
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-700 flex gap-2 justify-between">
                <button @click="clearCell()" class="px-4 py-2 rounded-xl text-sm font-semibold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition flex items-center gap-1.5">
                    <i data-lucide="eraser" class="w-4 h-4"></i> Kosongkan
                </button>
                <div class="flex gap-2">
                    <button @click="cellModal=false" class="px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Batal</button>
                    <button @click="saveCell()" :disabled="saving" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-2">
                        <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin" x-show="saving"></i><span x-text="saving?'Menyimpan...':'Simpan'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Modal Atur Jam ===== --}}
    <div x-show="jamModal" class="modal-backdrop" x-transition @click.self="jamModal=false">
        <div class="modal-box max-w-lg w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Atur Jam — {{ \App\Models\Jadwal::HARI[$hari] }}</h3>
                    <p class="text-xs text-slate-400">Tiap hari bisa punya susunan jam berbeda</p>
                </div>
                <button @click="jamModal=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5 space-y-4 max-h-[64vh] overflow-y-auto">
                <div class="space-y-1.5">
                    @forelse($jamList as $jam)
                    <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/50">
                        @if($jam->jenis!=='pelajaran')
                        <span class="badge bg-amber-100 text-amber-700">{{ $jam->nama_khusus }}</span>
                        @else
                        <span class="badge bg-primary-50 text-primary">Jam {{ $jam->jam_ke }}</span>
                        @endif
                        <span class="text-sm font-mono text-slate-600 dark:text-slate-300 flex-1">{{ $jam->rentang }}</span>
                        <form method="POST" action="{{ route('jadwal.jam.destroy', $jam->uuid) }}" onsubmit="return confirmDelete(this)">
                            @csrf @method('DELETE')
                            <button class="p-1.5 rounded-lg hover:bg-rose-100 text-rose-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </form>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400 text-center py-2">Belum ada jam untuk hari ini.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('jadwal.jam.store') }}" class="border-t border-slate-100 dark:border-slate-700 pt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="hari" value="{{ $hari }}">
                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200">Tambah Jam</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="form-label">Jenis</label>
                            <select name="jenis" class="form-select" id="jamJenis" onchange="jamJenisChange(this.value)">
                                @foreach(\App\Models\JamPelajaran::JENIS as $key => $lbl)
                                <option value="{{ $key }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="jamKeWrap">
                            <label class="form-label">Jam ke-</label>
                            <input type="number" name="jam_ke" min="0" class="form-input" placeholder="1">
                        </div>
                        <div id="jamLabelWrap" style="display:none">
                            <label class="form-label">Nama</label>
                            <input type="text" name="label" id="jamLabel" maxlength="30" class="form-input" placeholder="mis. Sholat Dzuhur">
                        </div>
                        <div>
                            <label class="form-label">Mulai</label>
                            <input type="time" name="jam_mulai" required class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Selesai</label>
                            <input type="time" name="jam_selesai" required class="form-input">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-semibold">Tambah Jam ke {{ \App\Models\Jadwal::HARI[$hari] }}</button>
                </form>

                {{-- Salin susunan jam ke hari lain --}}
                <form method="POST" action="{{ route('jadwal.jam.copy') }}" class="border-t border-slate-100 dark:border-slate-700 pt-4 space-y-2.5" onsubmit="return confirmCopyJam(this)">
                    @csrf
                    <input type="hidden" name="from_hari" value="{{ $hari }}">
                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200">Salin susunan jam {{ \App\Models\Jadwal::HARI[$hari] }} ke:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Models\Jadwal::HARI as $no => $nama)
                            @if($no !== $hari)
                            <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700">
                                <input type="checkbox" name="to[]" value="{{ $no }}" class="accent-[color:var(--cp)]"> {{ $nama }}
                            </label>
                            @endif
                        @endforeach
                    </div>
                    <p class="text-[11px] text-amber-600">⚠ Jadwal pada hari tujuan akan direset.</p>
                    <button type="submit" class="w-full py-2.5 rounded-xl text-sm font-semibold border border-primary text-primary hover:bg-primary-50 transition flex items-center justify-center gap-2"><i data-lucide="copy" class="w-4 h-4"></i> Salin Jam</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== Modal Auto-Generate ===== --}}
    <div x-show="genModal" class="modal-backdrop" x-transition @click.self="genModal=false">
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2"><i data-lucide="sparkles" class="w-5 h-5 text-primary"></i> Auto-Generate Jadwal</h3>
                <button @click="genModal=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <form method="POST" action="{{ route('jadwal.generate') }}" class="p-5 space-y-4">
                @csrf
                <div class="bg-primary-50 text-primary rounded-xl p-3 text-sm flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                    <p>Komputer mengisi jadwal berdasarkan <strong>penugasan mengajar guru</strong> (Guru → Pelajaran Diajar) dan <strong>JP/minggu</strong> tiap mapel. Tiap mapel ditempatkan sebagai <strong>blok jam berurutan</strong> (mis. 6 JP → 2-2-2 di 3 hari) tanpa bentrok guru. <a href="{{ route('jadwal.jp') }}" class="underline font-semibold">Atur JP dulu →</a></p>
                </div>
                <div class="space-y-2">
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                        <input type="radio" name="mode" value="isi_kosong" checked class="mt-0.5">
                        <div><p class="font-semibold text-sm text-slate-700 dark:text-slate-200">Isi slot kosong</p><p class="text-xs text-slate-400">Pertahankan jadwal yang sudah ada, isi yang masih kosong saja.</p></div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                        <input type="radio" name="mode" value="timpa" class="mt-0.5">
                        <div><p class="font-semibold text-sm text-slate-700 dark:text-slate-200">Buat ulang dari nol</p><p class="text-xs text-rose-500">Hapus semua jadwal lama, generate baru sepenuhnya.</p></div>
                    </label>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="genModal=false" class="px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Batal</button>
                    <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="wand-sparkles" class="w-4 h-4"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Popup nama guru (hover) --}}
<div id="jtip"></div>
@endsection

@push('scripts')
<script>
// Atur Jam: tampilkan "Jam ke-" hanya utk pelajaran, "Nama" utk jam khusus
function jamJenisChange(v){
    const isPel = v === 'pelajaran';
    const ke = document.getElementById('jamKeWrap'), lb = document.getElementById('jamLabelWrap');
    if(ke) ke.style.display = isPel ? 'block' : 'none';
    if(lb) lb.style.display = isPel ? 'none' : 'block';
}
function confirmCopyJam(form){
    const n = form.querySelectorAll('input[name="to[]"]:checked').length;
    if(n === 0){ showToast('Pilih minimal satu hari tujuan','error'); return false; }
    $.confirm({
        title:'Salin susunan jam?',
        content:'<div class="text-slate-600 dark:text-slate-300">Susunan jam akan disalin ke <b>'+n+' hari</b>.<br><span class="text-amber-600 font-semibold">Jadwal pada hari tujuan akan direset.</span> Lanjutkan?</div>',
        type:'orange', icon:'',
        buttons:{
            ya:{ text:'Ya, salin', btnClass:'btn-warning', keys:['enter'], action:function(){ form.submit(); } },
            batal:{ text:'Batal', btnClass:'btn-default' }
        }
    });
    return false; // submit dilakukan lewat tombol "Ya"
}
function jadwalGrid(bentrok, ngajarMap) {
    return {
        cellModal:false, jamModal:false, genModal:false, saving:false,
        bentrok: bentrok || [],
        ngajarMap: ngajarMap || {},
        guruHint:'',
        currentBtn:null,
        tsPel:null, tsGuru:null,
        form:{ kelasnama:'', jamlabel:'' },

        initTom(){
            if(!this.tsPel) this.tsPel = new TomSelect('#cellPelajaran', { create:false, onChange:(v)=>this.onPelChange(v) });
            if(!this.tsGuru){
                this.tsGuru = new TomSelect('#cellGuru', { create:false });
                this.tsGuru.on('change', ()=> this.checkWarn());
                this.tsGuru.lock();   // guru otomatis dari penugasan — tak bisa dipilih manual
            }
        },
        // Setelah pilih mapel → isi guru otomatis dari penugasan kelas ini
        onPelChange(pel){
            const kelas = this.currentBtn?.dataset.kelas;
            const m = (this.ngajarMap[kelas] || {})[pel];
            this.tsGuru.unlock();
            if(pel && m){
                if(!this.tsGuru.options[m.g]) this.tsGuru.addOption({ value:m.g, text:m.gn });
                this.tsGuru.setValue(m.g, true);
                this.guruHint = '';
            } else {
                this.tsGuru.setValue('', true);
                this.guruHint = pel ? 'Belum ada guru yang ditugaskan untuk pelajaran ini di kelas ini. Atur di Profil Guru → Pelajaran Diajar.' : '';
            }
            this.tsGuru.lock();
            this.checkWarn();
        },
        // Bangun ulang pilihan mapel: HANYA yang ditugaskan ke kelas sel ini
        rebuildPel(kelas, currentPel, currentPnama){
            const map = this.ngajarMap[kelas] || {};
            this.tsPel.clearOptions();
            this.tsPel.addOption({ value:'', text:'— Kosong / Istirahat —' });
            Object.keys(map).forEach(p => {
                const m = map[p];
                this.tsPel.addOption({ value:p, text: m.pn + (m.pk ? ' ('+m.pk+')' : '') });
            });
            if(currentPel && !map[currentPel]){
                this.tsPel.addOption({ value:currentPel, text: (currentPnama || 'Pelajaran') });
            }
            this.tsPel.refreshOptions(false);
            this.tsPel.setValue(currentPel || '', true);   // silent → tak picu onChange
            this.onPelChange(currentPel || '');            // isi guru sesuai
        },
        openCell(btn){
            this.currentBtn = btn;
            this.form.kelasnama = 'Kelas ' + btn.dataset.kelasnama;
            this.form.jamlabel = btn.dataset.jamlabel;
            this.cellModal = true;
            this.guruHint = '';
            this.$nextTick(()=>{
                this.initTom();
                this.rebuildPel(btn.dataset.kelas, btn.dataset.pelajaran || '', btn.dataset.pnama || '');
                document.getElementById('cellKet').value = btn.dataset.ket || '';
                lucide.createIcons();
            });
        },
        checkWarn(){
            const guru = this.tsGuru?.getValue();
            const jam = this.currentBtn?.dataset.jam;
            const warn = document.getElementById('cellWarn');
            if(!warn) return;
            // bentrok jika guru ini sudah ada di bentrok list ATAU sudah dipakai di kelas lain pada jam ini
            let used = false;
            if(guru){
                document.querySelectorAll('.jcell').forEach(c=>{
                    if(c!==this.currentBtn && c.dataset.jam===jam && c.dataset.guru===guru) used=true;
                });
            }
            warn.classList.toggle('hidden', !used);
        },
        async saveCell(){
            this.saving = true;
            const b = this.currentBtn;
            const payload = {
                id_kelas: b.dataset.kelas, hari: {{ $hari }}, id_jam: b.dataset.jam,
                id_pelajaran: this.tsPel.getValue() || null,
                id_guru: this.tsGuru.getValue() || null,
                keterangan: document.getElementById('cellKet').value || null
            };
            try {
                const res = await fetch('{{ route('jadwal.cell.save') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'}, body: JSON.stringify(payload) });
                const data = await res.json();
                if(res.ok){
                    this.updateCellDom(b, payload);
                    this.bentrok = data.bentrok || [];
                    this.refreshConflicts();
                    this.cellModal = false;
                } else { showToast('Gagal menyimpan','error'); }
            } catch { showToast('Gagal menghubungi server','error'); }
            this.saving = false;
        },
        async clearCell(){
            const b = this.currentBtn;
            try {
                const res = await fetch('{{ route('jadwal.cell.clear') }}', { method:'DELETE', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'}, body: JSON.stringify({ id_kelas:b.dataset.kelas, hari:{{ $hari }}, id_jam:b.dataset.jam }) });
                const data = await res.json();
                this.updateCellDom(b, {id_pelajaran:null,id_guru:null,keterangan:null});
                this.bentrok = data.bentrok || [];
                this.refreshConflicts();
                this.cellModal = false;
            } catch { showToast('Gagal','error'); }
        },
        updateCellDom(b, p){
            b.dataset.guru = p.id_guru || '';
            b.dataset.pelajaran = p.id_pelajaran || '';
            b.dataset.ket = p.keterangan || '';
            const pelText = p.id_pelajaran ? (this.tsPel.options[p.id_pelajaran]?.text || '') : (p.keterangan || '');
            const guruName = p.id_guru ? (this.tsGuru.options[p.id_guru]?.text || '') : '';
            // ekstrak kode dari "Nama (KODE)" → KODE; nama penuh tanpa kurung
            const m = pelText.match(/\(([^)]+)\)\s*$/);
            const short = m ? m[1] : pelText.slice(0, 6);
            const pnama = m ? pelText.replace(/\s*\([^)]+\)\s*$/, '') : pelText;
            b.dataset.pnama = pnama;
            b.dataset.gnama = guruName;
            if(pelText){
                b.classList.add('filled');
                b.innerHTML = `<span class="text-[12.5px] leading-none truncate">${short}</span>`;
            } else {
                b.classList.remove('filled');
                b.innerHTML = `<span class="text-slate-300 text-lg">+</span>`;
            }
        },
        bindTooltips(){
            const tip = document.getElementById('jtip');
            if(!tip) return;
            const show = (c)=>{
                const pn = c.dataset.pnama, gn = c.dataset.gnama;
                if(!pn) return;
                tip.innerHTML = `<div class="jt-pel">${pn}</div><div class="jt-guru"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>${gn || 'Guru belum diset'}</div>`;
                const r = c.getBoundingClientRect();
                tip.style.left = (r.left + r.width/2) + 'px';
                tip.style.top = (r.top - 8) + 'px';
                tip.classList.add('show');
            };
            document.querySelectorAll('.jcell').forEach(c=>{
                c.addEventListener('mouseenter', ()=> show(c));
                c.addEventListener('mouseleave', ()=> tip.classList.remove('show'));
            });
        },
        refreshConflicts(){
            const set = new Set(this.bentrok);
            document.querySelectorAll('.jcell').forEach(c=>{
                const key = c.dataset.jam + '|' + c.dataset.guru;
                c.classList.toggle('conflict', !!c.dataset.guru && set.has(key));
            });
        },
        init(){
            this.$nextTick(()=>{ this.refreshConflicts(); this.bindTooltips(); lucide.createIcons(); });
        }
    }
}
</script>
@endpush
