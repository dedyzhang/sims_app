@extends('layouts.app')
@section('title', 'Penempatan Siswa ke Kelas')

@section('content')
<div class="max-w-5xl mx-auto space-y-5" x-data="setKelasApp()">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Penempatan Siswa ke Kelas</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                Semester aktif: <strong>{{ $semester ? "Semester {$semester->semester} / {$semester->tahun}" : 'Belum ada semester aktif' }}</strong>
            </p>
        </div>
        <a href="{{ route('kelas.historiRombel') }}"
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
            <i data-lucide="history" class="w-4 h-4"></i> Histori Rombel
        </a>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">

        {{-- Panel Kiri: Siswa belum kelas --}}
        <div class="card flex flex-col" style="max-height:70vh">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200">
                        Belum Masuk Kelas
                        <span class="text-sm text-slate-400 font-normal ml-1">({{ $siswaBelumKelas->count() }})</span>
                    </h2>
                    <label class="flex items-center gap-1.5 text-sm text-slate-500 cursor-pointer">
                        <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="w-4 h-4 rounded border-slate-300 text-indigo-600">
                        Semua
                    </label>
                </div>
                <div class="relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" x-model="searchTerm" placeholder="Cari nama / NIS..."
                           class="form-input pl-9 py-2 text-sm">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-1">
                @forelse($siswaBelumKelas as $siswa)
                <label class="flex items-center gap-3 p-2.5 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700 transition group"
                       x-show="matchSearch('{{ addslashes(strtolower($siswa->nama)) }}', '{{ $siswa->nis ?? '' }}')">
                    <input type="checkbox" value="{{ $siswa->uuid }}" x-model="selected"
                           class="w-4 h-4 rounded border-slate-300 text-indigo-600 flex-shrink-0">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                         style="background:{{ $siswa->jk==='L' ? '#4f46e5' : '#ec4899' }}">
                        {{ strtoupper(substr($siswa->nama, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-700 dark:text-slate-200 truncate text-sm">{{ $siswa->nama }}</p>
                        <p class="text-xs text-slate-400 font-mono">{{ $siswa->nis ?? 'No NIS' }}</p>
                    </div>
                </label>
                @empty
                <div class="text-center py-10 text-slate-400">
                    <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                    <p class="text-sm font-medium">Semua siswa sudah masuk kelas</p>
                </div>
                @endforelse
            </div>

            <div class="p-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 rounded-b-2xl text-center">
                <span class="text-sm text-slate-500">
                    <span x-text="selected.length" class="font-bold text-indigo-600 text-base"></span> siswa dipilih
                </span>
            </div>
        </div>

        {{-- Panel Kanan --}}
        <div class="space-y-4">
            <div class="card p-5 space-y-4">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">📌 Tempatkan ke Kelas</h2>

                <div>
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1.5">Kelas Tujuan</label>
                    <select x-model="targetKelas" class="form-input">
                        <option value="">— Pilih Kelas —</option>
                        @foreach($kelas as $k)
                        <option value="{{ $k->uuid }}">
                            Kelas {{ $k->tingkat }}{{ $k->kelas }}
                            @if($k->walikelas?->guru) (Wali: {{ $k->walikelas->guru->nama }})@endif
                        </option>
                        @endforeach
                    </select>
                </div>

                <div x-show="selected.length > 0 && targetKelas"
                     class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl p-3 text-sm text-indigo-700 dark:text-indigo-300">
                    Akan memasukkan <strong x-text="selected.length"></strong> siswa
                </div>

                <button @click="submitForm()"
                        :disabled="selected.length===0 || !targetKelas"
                        :class="selected.length>0 && targetKelas
                            ? 'btn-primary shadow-sm cursor-pointer'
                            : 'bg-slate-200 dark:bg-slate-700 text-slate-400 cursor-not-allowed pointer-events-none'"
                        class="w-full py-3 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 transition">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Masukkan <span x-text="selected.length"></span> Siswa ke Kelas
                </button>
            </div>

            {{-- Ringkasan --}}
            <div class="card p-5">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-3">Ringkasan Kelas</h2>
                <div class="space-y-2">
                    @foreach($kelas as $k)
                    @php $jml = \App\Models\Siswa::where('id_kelas', $k->uuid)->count(); @endphp
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Kelas {{ $k->tingkat }}{{ $k->kelas }}</p>
                            <p class="text-xs text-slate-400">{{ $k->walikelas?->guru?->nama ?? 'Belum ada wali' }}</p>
                        </div>
                        <span class="badge {{ $jml>0 ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }}">
                            {{ $jml }} siswa
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden form for submit --}}
    <form id="rombelForm" method="POST" style="display:none">
        @csrf
        <div id="rombelInputs"></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function setKelasApp() {
    return {
        selected: [],
        selectAll: false,
        searchTerm: '',
        targetKelas: '',

        toggleAll() {
            const ids = @json($siswaBelumKelas->pluck('uuid'));
            this.selected = this.selectAll ? [...ids] : [];
        },

        matchSearch(nama, nis) {
            if (!this.searchTerm) return true;
            const q = this.searchTerm.toLowerCase();
            return nama.includes(q) || nis.includes(q);
        },

        submitForm() {
            if (!this.selected.length || !this.targetKelas) return;
            const form = document.getElementById('rombelForm');
            form.action = `/kelas/${this.targetKelas}/saveRombel`;
            const container = document.getElementById('rombelInputs');
            container.innerHTML = '';
            this.selected.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'siswa_ids[]'; inp.value = id;
                container.appendChild(inp);
            });
            form.submit();
        },

        init() { this.$nextTick(() => lucide.createIcons()); }
    }
}
</script>
@endpush
