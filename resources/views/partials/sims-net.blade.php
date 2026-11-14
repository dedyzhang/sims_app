@php
    $simsNetStats = \App\Support\TickerStats::forRole(auth()->user()?->access);
    $simsNetLabels = [
        'semester' => 'Semester', 'siswa' => 'Siswa', 'guru' => 'Guru',
        'kelas' => 'Kelas', 'mapel' => 'Mapel', 'aset' => 'Aset',
        'kerusakan' => 'Kerusakan', 'peminjaman' => 'Peminjaman', 'online' => 'Online',
    ];
@endphp
<section data-sims-ticker class="sims-net" aria-label="SIMS-NET — Ringkasan sekolah">
    <span class="sims-net-brand">SIMS-NET</span>
    <div class="sims-net-stats" tabindex="0" role="region" aria-label="Statistik sekolah berjalan, geser untuk melihat lainnya">
        <div class="sims-net-track"><div class="sims-net-group" data-ticker-original>
        @foreach($simsNetLabels as $key => $label)
            @if(($simsNetStats[$key] ?? '') !== '')
                <span class="sims-net-stat"><span class="sims-net-label">{{ $label }}</span><strong class="ticker-val-{{ $key }}">{{ $simsNetStats[$key] }}</strong></span>
            @endif
        @endforeach
        </div></div>
    </div>
    <button class="sims-net-pause" type="button" aria-label="Jeda gerakan SIMS-NET" aria-pressed="false" hidden>Jeda</button>
</section>
