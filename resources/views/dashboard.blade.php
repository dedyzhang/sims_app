@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    /* Ilustrasi kartu — animatif & interaktif */
    .ill { transition: transform .4s cubic-bezier(.2,.8,.2,1); transform-origin: center bottom; will-change: transform; }
    .group:hover .ill { transform: scale(1.07); }
    .ill-bob { animation: illBob 3.2s ease-in-out infinite; }
    .group:hover .ill-bob { animation-duration: 1.6s; }
    @keyframes illBob { 0%,100%{ transform: translateY(0); } 50%{ transform: translateY(-4px); } }
    .ill-glow { transform-box: fill-box; transform-origin: center; animation: illGlow 2s ease-in-out infinite; }
    @keyframes illGlow { 0%,100%{ opacity:.85; transform: scale(1); } 50%{ opacity:1; transform: scale(1.15); } }
    .ill-tw1 { animation: illTw 2.2s ease-in-out infinite; }
    .ill-tw2 { animation: illTw 2.6s ease-in-out infinite .4s; }
    @keyframes illTw { 0%,100%{ opacity:.35; } 50%{ opacity:1; } }
    .ill-flag { transform-box: fill-box; transform-origin: left center; animation: illFlag 1.6s ease-in-out infinite; }
    @keyframes illFlag { 0%,100%{ transform: scaleX(1); } 50%{ transform: scaleX(.74); } }
    .ill-w1,.ill-w2,.ill-w3,.ill-w4 { animation: illWin 2.4s ease-in-out infinite; }
    .ill-w2 { animation-delay:.3s; } .ill-w3 { animation-delay:.6s; } .ill-w4 { animation-delay:.9s; }
    @keyframes illWin { 0%,100%{ opacity:.45; } 50%{ opacity:1; } }
    @media (prefers-reduced-motion: reduce) {
        .ill-bob,.ill-glow,.ill-tw1,.ill-tw2,.ill-flag,.ill-w1,.ill-w2,.ill-w3,.ill-w4 { animation: none; }
    }

    /* ===== Kutipan harian: minimalis & modern ===== */
    .motiv-card { animation: motivIn .55s cubic-bezier(.2,.8,.2,1) both; }
    .motiv-label { font-size: 10px; letter-spacing: .22em; }
    @keyframes motivIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) { .motiv-card { animation: none; } }
    /* ===== Dashboard Google Education Theme ===== */
    .edu-dashboard { position: relative; }
    .card-batik.edu-hero {
        border: 1px solid #e8eaed !important;
        background: #fff !important;
        box-shadow: 0 14px 32px rgba(60, 64, 67, .10) !important;
    }
    .dark .card-batik.edu-hero {
        border-color: rgba(148, 163, 184, .18) !important;
        background: #0f172a !important;
        box-shadow: 0 18px 45px rgba(2, 6, 23, .34) !important;
    }
    .edu-hero-grid {
        position: absolute; inset: 0; pointer-events: none; opacity: .08;
        background-image:
            linear-gradient(#4285F4 1px, transparent 1px),
            linear-gradient(90deg, #34A853 1px, transparent 1px);
        background-size: 32px 32px;
        mask-image: linear-gradient(120deg, transparent 0%, #000 18%, #000 64%, transparent 100%);
    }
    .dark .edu-hero-grid { opacity: .12; }
    .google-accent-bar {
        position: absolute; inset: 0 auto auto 0; height: 5px; width: 100%;
        background: linear-gradient(90deg, #4285F4 0 25%, #EA4335 25% 50%, #FBBC05 50% 75%, #34A853 75% 100%);
    }
    .google-chip {
        display: inline-flex; align-items: center; gap: .4rem;
        border-radius: 9999px; padding: .35rem .75rem;
        border: 1px solid #e8eaed; background: #fff;
        color: #3c4043; font-size: 11px; font-weight: 800;
        box-shadow: 0 1px 2px rgba(60,64,67,.08);
    }
    .dark .google-chip { background: rgba(15,23,42,.72); border-color: rgba(148,163,184,.2); color: #e2e8f0; box-shadow: none; }
    .google-dot { width: .55rem; height: .55rem; border-radius: 9999px; display: inline-block; flex-shrink: 0; }
    .google-dot.blue { background: #4285F4; }
    .google-dot.red { background: #EA4335; }
    .google-dot.yellow { background: #FBBC05; }
    .google-dot.green { background: #34A853; }
    .google-title span:nth-child(1) { color: #4285F4; }
    .google-title span:nth-child(2) { color: #EA4335; }
    .google-title span:nth-child(3) { color: #FBBC05; }
    .google-title span:nth-child(4) { color: #4285F4; }
    .google-title span:nth-child(5) { color: #34A853; }
    .google-title span:nth-child(6) { color: #EA4335; }
    .edu-salam-icon {
        width: 2.75rem; height: 2.75rem; display: grid; place-items: center;
        border-radius: 1rem; color: #fff;
        background: #4285F4;
        box-shadow: 0 10px 24px rgba(66, 133, 244, .22);
    }
    .google-quote-card {
        position: relative; margin-top: 1rem; overflow: hidden;
        border-radius: 1.25rem; border: 1px solid #e8eaed;
        background: #f8fbff; padding: .95rem 1rem .95rem 1.1rem;
    }
    .google-quote-card::before {
        content: ''; position: absolute; inset: 0 auto 0 0; width: 5px;
        background: linear-gradient(180deg, #4285F4, #34A853);
    }
    .dark .google-quote-card { background: rgba(30,41,59,.52); border-color: rgba(148,163,184,.18); }
    .google-side-panel {
        position: relative; overflow: hidden; border-radius: 1.25rem;
        padding: 1rem; background: #f8fafd;
        border: 1px solid #e8eaed;
    }
    .dark .google-side-panel { background: rgba(15,23,42,.62); border-color: rgba(148,163,184,.18); }
    .google-info-card {
        display: flex; align-items: center; gap: .75rem;
        min-height: 3.9rem; padding: .75rem .85rem;
        border-radius: 1rem; background: #fff;
        border: 1px solid #e8eaed;
        box-shadow: 0 1px 2px rgba(60,64,67,.08);
    }
    .dark .google-info-card { background: rgba(15,23,42,.72); border-color: rgba(148,163,184,.18); box-shadow: none; }
    .google-icon-box {
        width: 2.35rem; height: 2.35rem; display: grid; place-items: center;
        border-radius: .85rem; color: #fff; flex-shrink: 0;
    }
    .google-school-art {
        margin-top: .9rem; width: 100%; height: 82px; color: #4285F4;
    }
    .dark .google-school-art { color: #93c5fd; opacity: .9; }
    .edu-dashboard #dashGrid { gap: 1rem; }
    @media (min-width: 1024px) { .edu-dashboard #dashGrid { gap: 1.25rem; } }
    .edu-dashboard .dash-content > .card,
    .edu-dashboard .dash-content > div:not([class*="card-batik"]):not(.edu-hero) {
        border: 1px solid #e8eaed;
        box-shadow: 0 6px 18px rgba(60, 64, 67, .08);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .edu-dashboard .dash-content > .card:hover,
    .edu-dashboard .dash-content > div:not([class*="card-batik"]):not(.edu-hero):hover {
        transform: translateY(-1px);
        border-color: #d2e3fc;
        box-shadow: 0 10px 24px rgba(60, 64, 67, .12);
    }
    .dark .edu-dashboard .dash-content > .card,
    .dark .edu-dashboard .dash-content > div:not([class*="card-batik"]):not(.edu-hero) {
        border-color: rgba(148, 163, 184, .18);
        box-shadow: 0 12px 26px rgba(2, 6, 23, .22);
    }
    .edu-dashboard .dash-content h2,
    .edu-dashboard .dash-content h3 { letter-spacing: 0; }
    /* ===== Tampilan Khusus Ringkasan Baru ===== */
    .card-siswa {
        background: linear-gradient(160deg, color-mix(in srgb, var(--cp) 18%, white), color-mix(in srgb, var(--cp) 6%, white)) !important;
        border-color: color-mix(in srgb, var(--cp) 12%, #e2e8f0) !important;
    }
    .dark .card-siswa {
        background: linear-gradient(160deg, #1e293b, #0f172a) !important;
        border-color: #334155 !important;
    }
    .card-siswa .text-title { color: color-mix(in srgb, var(--cp) 78%, black); }
    .card-siswa .text-sub { color: color-mix(in srgb, var(--cp) 62%, black); }

    /* ===== Kartu sambutan bermotif batik "Kawung" ===== */
    .card-batik {
        background: linear-gradient(120deg,
            color-mix(in srgb, var(--cp) 16%, white),
            color-mix(in srgb, var(--ca) 12%, white) 55%,
            color-mix(in srgb, var(--cps) 14%, white)) !important;
        border-color: color-mix(in srgb, var(--cp) 18%, #e2e8f0) !important;
    }
    .dark .card-batik {
        background: linear-gradient(120deg, #1e293b, #17233a 55%, #0f172a) !important;
        border-color: #334155 !important;
    }
    .dark .card-batik svg rect:first-of-type { opacity: .28 !important; }
    .dark .card-batik svg rect:last-of-type { opacity: .22 !important; }

    .card-guru {
        background: linear-gradient(160deg, color-mix(in srgb, var(--cps) 20%, white), color-mix(in srgb, var(--cps) 8%, white)) !important;
        border-color: color-mix(in srgb, var(--cps) 12%, #e2e8f0) !important;
    }
    .dark .card-guru {
        background: linear-gradient(160deg, #1e293b, #0f172a) !important;
        border-color: #334155 !important;
    }
    .card-guru .text-title { color: color-mix(in srgb, var(--cps) 80%, black); }
    .card-guru .text-sub { color: color-mix(in srgb, var(--cps) 64%, black); }

    .card-kelas {
        background: linear-gradient(160deg, color-mix(in srgb, var(--ca) 20%, white), color-mix(in srgb, var(--ca) 8%, white)) !important;
        border-color: color-mix(in srgb, var(--ca) 12%, #e2e8f0) !important;
    }
    .dark .card-kelas {
        background: linear-gradient(160deg, #1e293b, #0f172a) !important;
        border-color: #334155 !important;
    }
    .card-kelas .text-title { color: color-mix(in srgb, var(--ca) 80%, black); }
    .card-kelas .text-sub { color: color-mix(in srgb, var(--ca) 64%, black); }

    .dark .card-siswa .text-title, .dark .card-guru .text-title, .dark .card-kelas .text-title {
        color: #f1f5f9 !important;
    }
    .dark .card-siswa .text-sub, .dark .card-guru .text-sub, .dark .card-kelas .text-sub {
        color: #94a3b8 !important;
    }

    /* ===== Mode atur tata letak (drag & drop) ===== */
    .dash-block { position: relative; }
    .dash-handle { display: none; }
    .dash-editing .dash-block {
        border: 2px dashed color-mix(in srgb, var(--cp) 55%, transparent);
        border-radius: 22px; padding: .55rem; cursor: grab;
        background: color-mix(in srgb, var(--cp) 5%, transparent);
        transition: border-color .2s, background .2s;
    }
    .dash-editing .dash-block:hover { border-color: var(--cp); }
    .dash-editing .dash-handle {
        display: inline-flex; align-items: center; gap: .35rem;
        position: absolute; top: -.7rem; left: 1rem; z-index: 20;
        padding: .15rem .55rem; border-radius: 9999px;
        background: var(--cp); color: #fff; font-size: 11px; font-weight: 700;
        box-shadow: 0 4px 12px rgba(15,12,10,.18); user-select: none;
    }
    .dash-block.sortable-ghost { opacity: .4; }
    .dash-block.sortable-chosen { cursor: grabbing; }
    .dash-block.sortable-drag { box-shadow: 0 18px 40px rgba(15,12,10,.22); }
    /* matikan link saat sedang menyusun supaya tidak salah klik */
    .dash-editing .dash-block a { pointer-events: none; }

    /* tombol hapus/sembunyikan blok */
    .dash-remove { display: none; }
    .dash-editing .dash-remove {
        display: inline-flex; align-items: center; justify-content: center;
        position: absolute; top: -.7rem; right: 1rem; z-index: 21;
        width: 1.7rem; height: 1.7rem; border-radius: 9999px;
        background: #ef4444; color: #fff; cursor: pointer;
        box-shadow: 0 4px 12px rgba(15,12,10,.2); transition: transform .15s, background .15s;
    }
    .dash-editing .dash-remove:hover { transform: scale(1.12); }

    /* tombol ciutkan blok dashboard */
    .dash-collapse { display: none; }
    .dash-editing .dash-collapse {
        display: inline-flex; align-items: center; justify-content: center;
        position: absolute; top: -.7rem; right: 3.05rem; z-index: 21;
        width: 1.7rem; height: 1.7rem; border-radius: 9999px;
        background: #0f172a; color: #fff; cursor: pointer;
        box-shadow: 0 4px 12px rgba(15,12,10,.2); transition: transform .15s, background .15s;
    }
    .dash-editing .dash-collapse:hover { transform: scale(1.12); }
    .dash-collapsed .dash-content { display: none; }
    .dash-editing .dash-collapsed { min-height: 4.25rem; }
    .dash-collapsed-badge { display: none; }
    .dash-editing .dash-collapsed .dash-collapsed-badge {
        display: inline-block; position: absolute; bottom: .85rem; left: 1rem;
        z-index: 20; padding: .2rem .65rem; border-radius: 9999px;
        background: color-mix(in srgb, var(--cp) 12%, #fff); color: var(--cp);
        font-size: 11px; font-weight: 800; user-select: none;
    }
    .dark .dash-editing .dash-collapsed .dash-collapsed-badge { background: #1e293b; color: #cbd5e1; }

    /* blok yang disembunyikan */
    .dash-hidden { display: none; }
    .dash-editing .dash-hidden { display: block; opacity: .55; filter: grayscale(.6); }
    .dash-editing .dash-hidden .dash-remove { background: var(--cp); }
    .dash-hidden-badge { display: none; }
    .dash-editing .dash-hidden .dash-hidden-badge {
        display: inline-block; position: absolute; top: -.7rem; left: 50%; transform: translateX(-50%);
        z-index: 21; padding: .15rem .6rem; border-radius: 9999px;
        background: #64748b; color: #fff; font-size: 11px; font-weight: 700; user-select: none;
    }
</style>
@endpush

@section('content')
@php
    $access = auth()->user()?->access;
    $nama = auth()->user() ? auth()->user()->displayName() : 'Tamu';
    $totalSiswa = $stats['total_siswa'] ?? \App\Models\Siswa::count();
    $totalGuru  = $stats['total_guru'] ?? \App\Models\Guru::count();
    $totalKelas = $stats['total_kelas'] ?? \App\Models\Kelas::count();
    $totalMapel = \App\Models\Pelajaran::count();
    $siswaL = \App\Models\Siswa::where('jk','L')->count();
    $siswaP = \App\Models\Siswa::where('jk','P')->count();
    $pref = auth()->user()?->preference()->firstOrCreate(
        ['user_uuid' => auth()->id()],
        \App\Models\UserPreference::defaults()
    );
    $motif = $pref->motif ?? 'botanical';
    $motifIcon = ['botanical'=>'flower-2','ocean'=>'waves','forest'=>'trees','sunset'=>'sunset','robot'=>'bot','space'=>'rocket','minimal'=>'circle','nightocean'=>'anchor','rainbow'=>'sparkles'][$motif] ?? 'flower-2';

    // Salam berdasarkan waktu + tanggal hari ini (Bahasa Indonesia)
    $now  = \Carbon\Carbon::now();
    $jam  = (int) $now->format('H');
    $salam     = $jam < 11 ? 'Selamat Pagi' : ($jam < 15 ? 'Selamat Siang' : ($jam < 18 ? 'Selamat Sore' : 'Selamat Malam'));
    $salamIcon = $jam < 11 ? 'sunrise' : ($jam < 15 ? 'sun' : ($jam < 18 ? 'sunset' : 'moon'));
    $tanggalHari = $now->locale('id')->isoFormat('dddd, D MMMM Y');

    // Kata motivasi harian — tetap sepanjang hari, berganti otomatis tiap hari.
    $motivasiList = [
        // ── Bahasa Indonesia ──
        'Pendidikan adalah senjata paling ampuh untuk mengubah dunia.',
        'Sedikit demi sedikit, lama-lama menjadi bukit. Konsistensi mengalahkan kesempurnaan.',
        'Hari yang baik dimulai dari niat yang baik. Semangat untuk hari ini!',
        'Guru terbaik adalah mereka yang menyalakan rasa ingin tahu, bukan sekadar mengisi.',
        'Tidak ada usaha yang sia-sia, setiap langkah kecil mendekatkanmu pada tujuan.',
        'Belajar hari ini, memimpin esok hari.',
        'Kesabaran dan ketekunan adalah dua sahabat terbaik dalam mendidik.',
        'Setiap anak adalah bintang dengan caranya sendiri untuk bersinar.',
        'Mulailah dari yang kecil, mulailah dari sekarang, mulailah dari tempatmu berada.',
        'Kualitas pendidikan menentukan kualitas masa depan bangsa.',
        'Senyum dan semangatmu hari ini bisa menjadi inspirasi bagi orang lain.',
        'Kerja keras tidak pernah mengkhianati hasil.',
        'Disiplin adalah jembatan antara cita-cita dan pencapaian.',
        'Ilmu yang bermanfaat adalah warisan terbaik yang tak pernah habis.',
        'Jangan menunggu termotivasi untuk memulai; mulailah, maka motivasi akan datang.',
        'Hari ini adalah kesempatan baru untuk menjadi lebih baik dari kemarin.',
        'Mendidik dengan hati akan dikenang sepanjang masa.',
        'Kegagalan hanyalah cara lain untuk belajar melakukannya dengan benar.',
        'Bersyukur atas hal kecil membuat hal besar terasa lebih ringan.',
        'Pikiran yang tenang menghasilkan keputusan yang bijak.',
        'Ing ngarsa sung tuladha, ing madya mangun karsa, tut wuri handayani. — Ki Hajar Dewantara',
        'Bermimpilah setinggi langit. Jika engkau jatuh, engkau akan jatuh di antara bintang-bintang. — Ir. Soekarno',
        'Orang boleh pandai setinggi langit, tapi selama ia tidak menulis, ia akan hilang dari masyarakat dan sejarah. — Pramoedya Ananta Toer',
        'Membaca adalah jendela dunia, dan ilmu adalah cahayanya.',
        'Belajar tanpa berpikir itu sia-sia, berpikir tanpa belajar itu berbahaya.',
        'Guru menggenggam masa depan bangsa di ujung penanya.',
        'Tuntutlah ilmu dari buaian hingga ke liang lahat.',
        'Tidak ada kata terlambat untuk belajar hal baru.',
        'Sebaik-baik manusia adalah yang paling bermanfaat bagi sesamanya.',
        'Kecerdasan bukan tujuan akhir, melainkan kebaikan hati yang menyertainya.',
        'Rajin pangkal pandai, hemat pangkal kaya, malu bertanya sesat di jalan.',
        'Pendidikan bukan mempersiapkan hidup; pendidikan adalah hidup itu sendiri.',
        'Anak-anak kita bukan untuk kehidupan kita, melainkan untuk zamannya sendiri.',
        'Berani bermimpi, berani belajar, berani mencoba, berani gagal, berani bangkit.',
        'Jadikan kesalahan sebagai guru, bukan sebagai penjara.',
        'Sebuah buku adalah teman yang tidak pernah mengkhianati.',
        'Akar pendidikan memang pahit, tetapi buahnya manis.',
        'Membimbing satu anak berarti menanam pohon yang teduh bagi banyak orang.',
        'Setiap pertanyaan adalah benih dari sebuah penemuan.',
        'Karakter yang baik lebih berharga daripada nilai yang sempurna.',
        'Hari ini membaca, esok memimpin.',
        'Jangan takut melangkah pelan, takutlah jika hanya berdiam diri.',
        'Pengetahuan adalah harta yang tidak bisa dicuri.',
        'Mengajar adalah menyentuh kehidupan selamanya.',
        'Semangat belajar adalah api yang harus terus dijaga agar tetap menyala.',
        'Kerendahan hati adalah awal dari semua pembelajaran.',
        'Yang penting bukan seberapa cepat kau berlari, tapi seberapa jauh kau bertahan.',
        'Doa, usaha, dan ilmu adalah tiga sahabat menuju kesuksesan.',
        'Pendidikan terbaik adalah teladan yang nyata.',
        'Sukses adalah hasil dari kebiasaan kecil yang diulang setiap hari.',

        // ── English ──
        'Education is the most powerful weapon which you can use to change the world. — Nelson Mandela',
        'The beautiful thing about learning is that no one can take it away from you. — B.B. King',
        'Live as if you were to die tomorrow. Learn as if you were to live forever. — Mahatma Gandhi',
        'The roots of education are bitter, but the fruit is sweet. — Aristotle',
        'Tell me and I forget. Teach me and I remember. Involve me and I learn. — Benjamin Franklin',
        'The mind is not a vessel to be filled, but a fire to be kindled. — Plutarch',
        'Education is not the filling of a pail, but the lighting of a fire. — W.B. Yeats',
        'An investment in knowledge pays the best interest. — Benjamin Franklin',
        'The expert in anything was once a beginner.',
        'The only person who is educated is the one who has learned how to learn. — Carl Rogers',
        'Develop a passion for learning. If you do, you will never cease to grow. — Anthony J. D’Angelo',
        'Education is the passport to the future, for tomorrow belongs to those who prepare for it today. — Malcolm X',
        'A teacher affects eternity; he can never tell where his influence stops. — Henry Adams',
        'Learning never exhausts the mind. — Leonardo da Vinci',
        'The capacity to learn is a gift; the ability to learn is a skill; the willingness to learn is a choice.',
        'Change is the end result of all true learning. — Leo Buscaglia',
        'It is the supreme art of the teacher to awaken joy in creative expression and knowledge. — Albert Einstein',
        'The more that you read, the more things you will know. The more that you learn, the more places you’ll go. — Dr. Seuss',
        'Anyone who stops learning is old, whether at twenty or eighty. — Henry Ford',
        'They cannot stop me. I will get my education, if it is in the home, school, or anyplace. — Rosa Parks',
        'The function of education is to teach one to think intensively and to think critically. — Martin Luther King Jr.',
        'I am still learning. — Michelangelo',
        'Success is the sum of small efforts repeated day in and day out. — Robert Collier',
        'Strive for progress, not perfection.',
        'Believe you can and you’re halfway there. — Theodore Roosevelt',
        'The future belongs to those who believe in the beauty of their dreams. — Eleanor Roosevelt',
        'Don’t watch the clock; do what it does. Keep going. — Sam Levenson',
        'A little progress each day adds up to big results.',
        'The harder you work for something, the greater you’ll feel when you achieve it.',
        'Wisdom is not a product of schooling but of the lifelong attempt to acquire it. — Albert Einstein',
        'Knowledge is power. — Francis Bacon',
        'Curiosity is the wick in the candle of learning. — William Arthur Ward',
        'The best way to predict your future is to create it. — Abraham Lincoln',
        'Education breeds confidence. Confidence breeds hope. Hope breeds peace. — Confucius',
        'The whole purpose of education is to turn mirrors into windows. — Sydney J. Harris',
        'Learn from yesterday, live for today, hope for tomorrow. — Albert Einstein',
        'You don’t have to be great to start, but you have to start to be great. — Zig Ziglar',
        'Every accomplishment starts with the decision to try.',
        'Education is not preparation for life; education is life itself. — John Dewey',
        'There are no shortcuts to any place worth going.',
        'Teaching is the one profession that creates all other professions.',
        'A good teacher can inspire hope, ignite the imagination, and instill a love of learning. — Brad Henry',
        'Genius is one percent inspiration and ninety-nine percent perspiration. — Thomas Edison',
        'The journey of a thousand miles begins with a single step. — Lao Tzu',
        'Do not go where the path may lead, go instead where there is no path and leave a trail. — Ralph Waldo Emerson',
        'Small daily improvements over time lead to stunning results. — Robin Sharma',
        'The more I learn, the more I realize how much I don’t know. — Albert Einstein',
        'Push yourself, because no one else is going to do it for you.',
        'Great things are done by a series of small things brought together. — Vincent van Gogh',
        'Be a lifelong student; the day you stop learning is the day you stop growing.',
    ];
    // Pilih via hash tanggal: terasa acak antar-hari, tetap stabil sepanjang hari.
    $kataMotivasi = $motivasiList[abs(crc32($now->toDateString())) % count($motivasiList)];

    // Pisahkan teks dengan penulisnya (jika ada "— Penulis" di akhir).
    if (preg_match('/^(.*?)\s+—\s+([^—]+)$/u', $kataMotivasi, $mm)) {
        $kataTeks = trim($mm[1]);
        $kataPenulis = trim($mm[2]);
    } else {
        $kataTeks = $kataMotivasi;
        $kataPenulis = null;
    }
@endphp

@if(auth()->user()->must_change_password)
<div class="mb-6 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-amber-800 dark:text-amber-200 text-sm">
    <div class="flex gap-3 items-start">
        <i data-lucide="shield-alert" class="w-5 h-5 flex-shrink-0 text-amber-600 dark:text-amber-400 mt-0.5"></i>
        <div>
            <p class="font-bold">Keamanan Akun: Harap Ganti Password Default Anda</p>
            <p class="text-xs text-amber-700/90 dark:text-amber-300/90 mt-0.5 leading-relaxed">
                Akun Anda saat ini menggunakan password default atau baru saja direset. Silakan ganti password lama demi keamanan data Anda.
            </p>
        </div>
    </div>
    <a href="{{ route('ganti.password') }}" class="flex-shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm w-fit">
        <i data-lucide="key-round" class="w-3.5 h-3.5"></i> Ganti Password Sekarang
    </a>
</div>
@endif

@if(in_array($access, ['superadmin','admin','kepala']))
@php
    // Urutan blok: pakai preferensi tersimpan dulu, lalu blok baru yang belum tercatat.
    // (DASHBOARD_BLOCKS juga memuat blok khusus per-peran lain — disaring di sini karena bukan blok admin.
    // sarpras_* dikecualikan dari filter ini karena admin memang memakainya juga.)
    $rolePrefixes = ['siswa_', 'guru_', 'kesiswaan_', 'kurikulum_'];
    $accessRole = auth()->user()?->access;
    $allBlocks = array_values(array_filter(\App\Models\UserPreference::DASHBOARD_BLOCKS, function ($b) use ($rolePrefixes, $accessRole) {
        if ($accessRole === 'kepala' && $b === 'quicklinks') return false;
        foreach ($rolePrefixes as $prefix) {
            if (str_starts_with($b, $prefix)) return false;
        }
        return true;
    }));
    $savedLayout = is_array($pref->dashboard_layout) ? $pref->dashboard_layout : [];
    $blockOrder  = array_values(array_unique(array_merge(
        array_values(array_intersect($savedLayout, $allBlocks)),
        $allBlocks
    )));
    $hiddenBlocks = is_array($pref->dashboard_hidden)
        ? array_values(array_intersect($pref->dashboard_hidden, $allBlocks))
        : [];
    $blockLabel = [
        'ringkasan_siswa' => 'Ringkasan Siswa',
        'ringkasan_guru'  => 'Ringkasan Guru',
        'ringkasan_kelas' => 'Ringkasan Kelas',
        'ringkasan_tahun' => 'Tahun Ajaran',
        'presensi_hadir'   => 'Presensi Guru: Hadir',
        'presensi_terlambat' => 'Presensi Guru: Terlambat',
        'presensi_tidak_hadir' => 'Presensi Guru: Izin/Sakit/Alpa',
        'presensi_belum'   => 'Presensi Guru: Belum Presensi',
        'sarpras_aset'    => 'Sarpras Total Aset',
        'sarpras_kerusakan' => 'Sarpras Laporan Kerusakan',
        'sarpras_peminjaman' => 'Sarpras Peminjaman Aktif',
        'sarpras_pengadaan' => 'Sarpras Pengadaan Pending',
        'recent_tingkat'  => 'Sebaran Siswa per Tingkat',
        'recent_komposisi' => 'Komposisi Jenis Kelamin',
        'sebaran'         => 'Grafik Sebaran Kelas',
        'quicklinks'      => 'Tautan Cepat',
    ];
    $spans = [
        'ringkasan_siswa' => 'col-span-6 lg:col-span-3',
        'ringkasan_guru'  => 'col-span-6 lg:col-span-3',
        'ringkasan_kelas' => 'col-span-6 lg:col-span-3',
        'ringkasan_tahun' => 'col-span-6 lg:col-span-3',
        'presensi_hadir'   => 'col-span-6 lg:col-span-3',
        'presensi_terlambat' => 'col-span-6 lg:col-span-3',
        'presensi_tidak_hadir' => 'col-span-6 lg:col-span-3',
        'presensi_belum'   => 'col-span-6 lg:col-span-3',
        'sarpras_aset'    => 'col-span-6 lg:col-span-3',
        'sarpras_kerusakan' => 'col-span-6 lg:col-span-3',
        'sarpras_peminjaman' => 'col-span-6 lg:col-span-3',
        'sarpras_pengadaan' => 'col-span-6 lg:col-span-3',
        'recent_tingkat'  => 'col-span-12 lg:col-span-5',
        'recent_komposisi' => 'col-span-12 lg:col-span-7',
        'sebaran'         => 'col-span-12',
        'quicklinks'      => 'col-span-12',
    ];
@endphp

<div x-data="dashLayout()" class="edu-dashboard" :class="{ 'dash-editing': editing }">
    @include('partials.dash-greeting')

    {{-- Grid blok yang bisa di-drag --}}
    <div id="dashGrid" class="grid grid-cols-12 gap-5" x-ref="grid">
        @foreach($blockOrder as $block)
            @if(str_starts_with($block, 'sarpras_') && ! auth()->user()->can('sarpras.dashboard.lihat'))
                @continue
            @endif
            @include('partials.dash-block-item', ['block' => $block, 'spans' => $spans, 'hiddenBlocks' => $hiddenBlocks, 'blockLabel' => $blockLabel])
        @endforeach
    </div>
</div>

@include('partials.sosmed-bar')

@elseif(in_array($access, ['siswa', 'orangtua']) && $siswaWidget)
{{-- ===== Siswa & Orangtua: kartu batik + blok yang bisa disusun ulang, sama seperti admin ===== --}}
@php
    $siswa = $siswaWidget['siswa']; $jadwals = $siswaWidget['jadwals']; $hariIni = $siswaWidget['hariIni'];
    $absensiHariIni = $siswaWidget['absensiHariIni']; $rekapAbsensi = $siswaWidget['rekapAbsensi'];
    $persenHadir = $siswaWidget['persenHadir']; $kalenderBulan = $siswaWidget['kalenderBulan'];
    $offsetAwal = $siswaWidget['offsetAwal']; $streakHadir = $siswaWidget['streakHadir'];
    $jenisAturan = $siswaWidget['jenisAturan']; $poin = $siswaWidget['poin']; $podium = $siswaWidget['podium'];

    $allBlocks = ['siswa_jadwal', 'siswa_absensi', 'siswa_poin'];
    if ($jenisAturan === 'poin') {
        $allBlocks[] = 'siswa_podium';
    }
    $savedLayout = is_array($pref->dashboard_layout) ? $pref->dashboard_layout : [];
    $blockOrder  = array_values(array_unique(array_merge(
        array_values(array_intersect($savedLayout, $allBlocks)),
        $allBlocks
    )));
    $hiddenBlocks = is_array($pref->dashboard_hidden)
        ? array_values(array_intersect($pref->dashboard_hidden, $allBlocks))
        : [];
    $blockLabel = [
        'siswa_jadwal'  => 'Jadwal Hari Ini',
        'siswa_absensi' => 'Absensi Saya',
        'siswa_poin'    => $jenisAturan === 'poin' ? 'Poin Kedisiplinan' : 'P3 Kedisiplinan',
        'siswa_podium'  => 'Papan Peringkat Sekolah',
    ];
    $spans = [
        'siswa_jadwal'  => 'col-span-12 lg:col-span-6',
        'siswa_absensi' => 'col-span-12 lg:col-span-6',
        'siswa_poin'    => 'col-span-12 lg:col-span-5',
        'siswa_podium'  => 'col-span-12 lg:col-span-7',
    ];
@endphp

<div x-data="dashLayout()" class="edu-dashboard" :class="{ 'dash-editing': editing }">
    @include('partials.dash-greeting')

    <div id="dashGrid" class="grid grid-cols-12 gap-5" x-ref="grid">
        @foreach($blockOrder as $block)
            @include('partials.dash-block-item', ['block' => $block, 'spans' => $spans, 'hiddenBlocks' => $hiddenBlocks, 'blockLabel' => $blockLabel])
        @endforeach
    </div>
</div>

@include('partials.sosmed-bar')

@elseif(in_array($access, ['guru', 'walikelas', 'kurikulum', 'kesiswaan', 'sapras']))
{{-- ===== Guru/Walikelas/Kurikulum/Kesiswaan/Sapras: kartu batik + blok sesuai kebutuhan peran, sama seperti admin ===== --}}
@php
    $allBlocks = match ($access) {
        'guru', 'walikelas' => auth()->user()->guru ? ['guru_jadwal', 'guru_presensi', 'guru_agenda'] : [],
        'kurikulum' => array_merge(auth()->user()->guru ? ['guru_jadwal', 'guru_presensi', 'guru_agenda'] : [], ['ringkasan_siswa', 'ringkasan_guru', 'ringkasan_kelas', 'ringkasan_tahun', 'kurikulum_agenda']),
        'kesiswaan' => array_merge(auth()->user()->guru ? ['guru_jadwal', 'guru_presensi', 'guru_agenda'] : [], ['ringkasan_siswa', 'kesiswaan_pending', 'kesiswaan_absensi']),
        'sapras' => array_merge(auth()->user()->guru ? ['guru_jadwal', 'guru_presensi', 'guru_agenda'] : [], auth()->user()->can('sarpras.dashboard.lihat') ? ['sarpras_aset', 'sarpras_kerusakan', 'sarpras_peminjaman', 'sarpras_pengadaan'] : []),
        default => [],
    };
    $savedLayout = is_array($pref->dashboard_layout) ? $pref->dashboard_layout : [];
    $blockOrder  = array_values(array_unique(array_merge(
        array_values(array_intersect($savedLayout, $allBlocks)),
        $allBlocks
    )));
    $hiddenBlocks = is_array($pref->dashboard_hidden)
        ? array_values(array_intersect($pref->dashboard_hidden, $allBlocks))
        : [];
    $blockLabel = [
        'guru_jadwal'        => 'Jadwal Mengajar',
        'guru_presensi'      => 'Presensi Saya',
        'guru_agenda'        => 'Agenda Hari Ini',
        'ringkasan_siswa'    => 'Ringkasan Siswa',
        'ringkasan_guru'     => 'Ringkasan Guru',
        'ringkasan_kelas'    => 'Ringkasan Kelas',
        'ringkasan_tahun'    => 'Tahun Ajaran',
        'kurikulum_agenda'   => 'Agenda Menunggu Validasi',
        'kesiswaan_pending'  => 'Pengajuan Menunggu',
        'kesiswaan_absensi'  => 'Absensi Siswa Hari Ini',
        'sarpras_aset'       => 'Total Aset',
        'sarpras_kerusakan'  => 'Laporan Kerusakan',
        'sarpras_peminjaman' => 'Peminjaman Aktif',
        'sarpras_pengadaan'  => 'Pengadaan Pending',
    ];
    $spans = [
        'guru_jadwal'        => 'col-span-12 lg:col-span-6',
        'guru_presensi'      => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'guru_agenda'        => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'ringkasan_siswa'    => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'ringkasan_guru'     => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'ringkasan_kelas'    => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'ringkasan_tahun'    => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'kurikulum_agenda'   => 'col-span-12',
        'kesiswaan_pending'  => 'col-span-12 lg:col-span-4',
        'kesiswaan_absensi'  => 'col-span-12 lg:col-span-8',
        'sarpras_aset'       => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'sarpras_kerusakan'  => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'sarpras_peminjaman' => 'col-span-12 sm:col-span-6 lg:col-span-3',
        'sarpras_pengadaan'  => 'col-span-12 sm:col-span-6 lg:col-span-3',
    ];
@endphp

<div x-data="dashLayout()" class="edu-dashboard" :class="{ 'dash-editing': editing }">
    @include('partials.dash-greeting')

    @if(empty($allBlocks))
    <div class="card p-8 text-center text-slate-400">
        <i data-lucide="layout-dashboard" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
        <p class="text-sm font-medium">Belum ada widget untuk peran Anda.</p>
    </div>
    @else
    <div id="dashGrid" class="grid grid-cols-12 gap-5" x-ref="grid">
        @foreach($blockOrder as $block)
            @include('partials.dash-block-item', ['block' => $block, 'spans' => $spans, 'hiddenBlocks' => $hiddenBlocks, 'blockLabel' => $blockLabel])
        @endforeach
    </div>
    @endif
</div>

@include('partials.sosmed-bar')

@else
{{-- ===== Non-admin ===== --}}
<div class="max-w-lg mx-auto mt-10">
    <div class="card p-8 text-center relative overflow-hidden">
        <div class="absolute -right-6 -top-6 opacity-30 pointer-events-none">
            @php
                $motif = $pref->motif ?? 'botanical';
                $cp = strtolower($pref->primary_color ?? '');
            @endphp
            @if($motif === 'botanical')
                @include('partials.flower', ['s'=>110,'c1'=>'var(--cp)','c2'=>'var(--ca)','o'=>'.5'])
            @elseif($motif === 'forest')
                @include('partials.leaf', ['s'=>100,'c'=>'var(--cp)','o'=>'.5'])
            @elseif($motif === 'ocean')
                <svg width="100" height="100" viewBox="0 0 120 70" style="transform: rotate(-15deg);"><g fill="var(--cp)"><path d="M15,35 C45,8 85,8 100,35 C85,62 45,62 15,35 Z"/><path d="M100,35 L120,18 L115,35 L120,52 Z"/></g></svg>
            @elseif($motif === 'nightocean')
                <svg width="90" height="90" viewBox="0 0 120 140" fill="none" stroke="var(--cp)" stroke-width="6" stroke-linecap="round"><circle cx="60" cy="22" r="12"/><line x1="60" y1="34" x2="60" y2="110"/><path d="M24,110 C24,84 60,84 60,110 C60,84 96,84 96,110"/><line x1="42" y1="50" x2="78" y2="50"/></svg>
            @elseif($motif === 'rainbow')
                <svg width="104" height="104" viewBox="0 0 104 104" fill="none" aria-hidden="true">
                    <path d="M8 76 C28 46 50 40 96 28" stroke="#4285f4" stroke-width="13" stroke-linecap="round" opacity=".62"/>
                    <path d="M12 86 C34 58 58 53 96 46" stroke="#34a853" stroke-width="13" stroke-linecap="round" opacity=".55"/>
                    <path d="M20 20 L80 80" stroke="#fbbc05" stroke-width="12" stroke-linecap="round" opacity=".52"/>
                    <path d="M74 14 L94 34 L74 54 L54 34 Z" fill="#ea4335" opacity=".5"/>
                    <circle cx="35" cy="34" r="8" fill="var(--ca)" opacity=".75"/>
                </svg>
            @elseif($motif === 'robot')
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="var(--cp)" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            @elseif($motif === 'space')
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="var(--cp)" stroke-width="2"><path d="M4.5 16.5c-1.5 1.25-2.5 3.5-2.5 3.5s2.25-1 3.5-2.5L18.5 4.5 19.5 5.5l-13 13M12 5l2 2M9 8l2 2M6 11l2 2M19 3l2 2"/></svg>
            @elseif($motif === 'sunset')
                <svg width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="30" fill="var(--cp)" opacity=".8"/><path d="M10,70 L90,70 M20,80 L80,80 M35,90 L65,90" stroke="var(--cp)" stroke-width="4"/></svg>
            @elseif($motif === 'minimal' && $cp === '#0f5132')
                {{-- Zamrud Pro (Emerald Diamond) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="var(--cp)" stroke-width="2" stroke-linejoin="round"><polygon points="50,10 90,50 50,90 10,50" opacity=".6" /><polygon points="50,25 75,50 50,75 25,50" opacity=".4" /><polygon points="50,38 62,50 50,62 38,50" fill="var(--ca)" opacity=".8" stroke="none" /><line x1="50" y1="10" x2="50" y2="25" opacity=".5"/><line x1="90" y1="50" x2="75" y2="50" opacity=".5"/><line x1="50" y1="90" x2="50" y2="75" opacity=".5"/><line x1="10" y1="50" x2="25" y2="50" opacity=".5"/></svg>
            @elseif($motif === 'minimal' && $cp === '#3d2314')
                {{-- Kopi Karamel (Coffee Ripples) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="var(--cp)" stroke-dasharray="2 4" stroke-width="2.5"><circle cx="50" cy="50" r="40" opacity=".5" /><circle cx="50" cy="50" r="30" stroke-dasharray="none" opacity=".4" /><circle cx="50" cy="50" r="20" opacity=".3" /><circle cx="50" cy="50" r="10" fill="var(--ca)" opacity=".8" stroke="none" /><line x1="15" y1="15" x2="85" y2="85" stroke-width="1.5" opacity=".3"/></svg>
            @elseif($motif === 'minimal' && $cp === '#212529')
                {{-- Arang Pro (Charcoal Isometric Cube) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" stroke="var(--cp)" stroke-width="2" fill="none" stroke-linejoin="round"><g transform="translate(45, 45)"><polygon points="0,-25 22,-12 22,12 0,25 -22,12 -22,-12" opacity=".6"/><line x1="0" y1="25" x2="0" y2="0" opacity=".6"/><line x1="-22" y1="-12" x2="0" y2="0" opacity=".6"/><line x1="22" y1="-12" x2="0" y2="0" opacity=".6"/></g><g transform="translate(68, 62) scale(0.6)"><polygon points="0,-25 22,-12 22,12 0,25 -22,12 -22,-12" fill="var(--ca)" opacity=".8" stroke="none"/></g><g fill="var(--cps)" opacity=".4"><circle cx="20" cy="20" r="1.5"/><circle cx="40" cy="20" r="1.5"/><circle cx="60" cy="20" r="1.5"/><circle cx="80" cy="20" r="1.5"/><circle cx="20" cy="80" r="1.5"/><circle cx="40" cy="80" r="1.5"/><circle cx="60" cy="80" r="1.5"/></g></svg>
            @else
                {{-- Minimalis Default (#5b7a99) --}}
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="var(--cp)" stroke-width="2"><rect x="15" y="15" width="70" height="70" rx="16" opacity=".5" /><circle cx="50" cy="50" r="24" fill="var(--ca)" stroke="none" opacity=".8" /><line x1="15" y1="50" x2="85" y2="50" opacity=".3" /></svg>
            @endif
        </div>
        <div class="js-salam-icon w-16 h-16 rounded-2xl mx-auto mb-4 grid place-items-center text-white shadow-lg" data-icon-class="w-8 h-8" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
            <i data-lucide="{{ $salamIcon }}" class="w-8 h-8"></i>
        </div>
        <h2 class="text-xl font-extrabold text-slate-700 dark:text-slate-100"><span class="js-salam-text">{{ $salam }}</span>, {{ $nama }} 👋</h2>
        <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 dark:text-slate-200">
            <i data-lucide="calendar-days" class="w-4 h-4 text-slate-400"></i>
            <span class="js-dash-date capitalize">{{ $tanggalHari }}</span>
        </p>
        <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 dark:text-slate-200">
            <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
            <span class="js-dash-clock tabular-nums">--:--:--</span> WIB
        </p>
        <p class="text-sm text-slate-500 mt-1 capitalize">{{ $access }} @if($semester) • Semester {{ $semester->semester }} / {{ $semester->tahun }} @endif</p>
        <div class="motiv-card mt-6 pt-5 border-t border-slate-100 dark:border-slate-700/60">
            <p class="motiv-label font-bold uppercase text-primary/70">Quote of the Day</p>
            <p class="mt-2 text-sm font-normal leading-relaxed text-slate-600 dark:text-slate-300">{{ $kataTeks }}</p>
            @if($kataPenulis)
                <p class="mt-1.5 text-xs font-medium text-slate-400 dark:text-slate-500">{{ $kataPenulis }}</p>
            @endif
        </div>
        <p class="text-sm text-slate-400 mt-4">Gunakan menu di sidebar untuk mengakses fitur yang tersedia.</p>
    </div>

    @include('partials.sosmed-bar')
</div>
@endif
@endsection

@push('scripts')
<script>
// Jam + salam realtime dashboard — selalu mengikuti waktu WIB (Asia/Jakarta)
(function () {
    var TZ = 'Asia/Jakarta';
    var fmtJam, fmtJam24, fmtTanggal;
    try {
        fmtJam     = new Intl.DateTimeFormat('id-ID', { timeZone: TZ, hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        fmtJam24   = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour12: false, hour: '2-digit' });
        fmtTanggal = new Intl.DateTimeFormat('id-ID', { timeZone: TZ, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch (e) { fmtJam = fmtJam24 = fmtTanggal = null; }

    // Nilai awal dari server agar tidak ada "kedip" saat halaman dibuka.
    var lastSalam = @json($salam);
    var lastDate  = @json($tanggalHari);

    function salamFor(h) {
        if (h < 11) return ['Selamat Pagi',  'sunrise'];
        if (h < 15) return ['Selamat Siang', 'sun'];
        if (h < 18) return ['Selamat Sore',  'sunset'];
        return ['Selamat Malam', 'moon'];
    }

    function tick() {
        var now = new Date();

        // Jam berdetak
        document.querySelectorAll('.js-dash-clock').forEach(function (n) {
            n.textContent = fmtJam ? fmtJam.format(now) : now.toTimeString().slice(0, 8);
        });

        if (!fmtJam24) return;

        // Salam — perbarui hanya saat melewati batas waktu (mis. malam → pagi)
        var jam = parseInt(fmtJam24.format(now), 10);
        var s = salamFor(jam);
        if (s[0] !== lastSalam) {
            lastSalam = s[0];
            document.querySelectorAll('.js-salam-text').forEach(function (n) { n.textContent = s[0]; });
            document.querySelectorAll('.js-salam-icon').forEach(function (n) {
                n.innerHTML = '<i data-lucide="' + s[1] + '" class="' + (n.getAttribute('data-icon-class') || '') + '"></i>';
            });
            if (window.lucide) window.lucide.createIcons();
        }

        // Tanggal — perbarui saat berganti hari (lewat tengah malam)
        var d = fmtTanggal.format(now);
        if (d !== lastDate) {
            lastDate = d;
            document.querySelectorAll('.js-dash-date').forEach(function (n) { n.textContent = d; });
        }
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush

@if(in_array($access, ['superadmin','admin','kepala']) || (in_array($access, ['siswa', 'orangtua']) && $siswaWidget) || in_array($access, ['guru', 'walikelas', 'kurikulum', 'kesiswaan', 'sapras']))
@push('scripts')
<script>
function dashLayout() {
    return {
        editing: false,
        sortable: null,
        hidden: @json($hiddenBlocks),
        collapsed: (() => { try { return JSON.parse(localStorage.getItem('dash_collapsed_blocks') || '[]'); } catch (_) { return []; } })(),
        saveUrl: '{{ route('dashboard.layout') }}',
        csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),

        toggle() {
            this.editing = !this.editing;
            if (this.editing) {
                this.enableSort();
            } else {
                this.save();
            }
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },

        toggleHide(block) {
            const i = this.hidden.indexOf(block);
            if (i === -1) this.hidden.push(block);
            else this.hidden.splice(i, 1);
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },

        toggleCollapse(block) {
            const i = this.collapsed.indexOf(block);
            if (i === -1) this.collapsed.push(block);
            else this.collapsed.splice(i, 1);
            localStorage.setItem('dash_collapsed_blocks', JSON.stringify(this.collapsed));
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },

        enableSort() {
            if (this.sortable || typeof Sortable === 'undefined') return;
            this.sortable = Sortable.create(this.$refs.grid, {
                animation: 180,
                handle: '.dash-block',
                draggable: '.dash-block',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
            });
        },

        currentOrder() {
            return Array.from(this.$refs.grid.querySelectorAll('.dash-block'))
                        .map(el => el.dataset.block);
        },

        save() {
            // Urutan blok yang tampil saja; blok tersembunyi dikirim terpisah.
            const order  = this.currentOrder();
            const layout = order.filter(b => !this.hidden.includes(b));
            const hidden = this.hidden;
            fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ layout, hidden }),
            })
            .then(r => r.json())
            .then(() => window.showToast && window.showToast('Tata letak dashboard tersimpan', 'success'))
            .catch(() => window.showToast && window.showToast('Gagal menyimpan tata letak', 'error'));
        },

        reset() {
            const def = @json($allBlocks);
            const grid = this.$refs.grid;
            def.forEach(key => {
                const el = grid.querySelector('.dash-block[data-block="' + key + '"]');
                if (el) grid.appendChild(el); // urutkan ulang sesuai default
            });
            this.hidden = []; // tampilkan kembali semua blok
            this.collapsed = [];
            localStorage.removeItem('dash_collapsed_blocks');
            this.save();
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },
    };
}
</script>
@endpush
@endif
