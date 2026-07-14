<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\Admin\ChatbotAdminController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AiAnalyzeController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AiRagController;
use App\Http\Controllers\AiTeacherController;
use App\Http\Controllers\AppDownloadController;
use App\Http\Controllers\CetakController;
use App\Http\Controllers\CetakRaporController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ClassroomAssignmentController;
use App\Http\Controllers\ClassroomCommentController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassroomMaterialController;
use App\Http\Controllers\ClassroomSubmissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EkskulController;
use App\Http\Controllers\FaceController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ForumAccessController;
use App\Http\Controllers\ForumCommentController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ForumReactionController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KaihController;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\KartuPelajarController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\Keuangan\KeuanganController;
use App\Http\Controllers\Keuangan\TagihanController;
use App\Http\Controllers\LanggananController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\P3Controller;
use App\Http\Controllers\PanduanController;
use App\Http\Controllers\PelajaranController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PerangkatAjarController;
use App\Http\Controllers\PoinController;
use App\Http\Controllers\PresensiGuruController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrAbsensiController;
use App\Http\Controllers\RapatController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\WalikelasController;
use App\Http\Middleware\EnsureFaceRegistered;
use App\Http\Middleware\EnsureKioskOrPermission;
use App\Support\TickerStats;
use Illuminate\Support\Facades\Route;
use Laragear\WebAuthn\Http\Routes as WebAuthnRoutes;

// ─── Publik ───────────────────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// ─── Auth ─────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    // Throttle: cegah brute force kredensial.
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.post');
});
// Throttle: PIN cuma 6 digit, tanpa throttle gampang di-brute force.
Route::post('/login/pin', [LoginController::class, 'loginPin'])->middleware('throttle:login')->name('login.pin');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::post('/password/request', [LoginController::class, 'requestResetPassword'])->middleware('throttle:6,1')->name('password.request');

// WebAuthn (Fingerprint / Face ID)
WebAuthnRoutes::register('webauthn');

// ─── Kiosk Absensi: link rahasia PUBLIK (tanpa login) — dipasang sbg shortcut di komputer
//     meja piket supaya guru bisa langsung scan tanpa minta admin buka/login-kan dulu.
//     Token di URL didapat dari Pengaturan → Absensi (admin-only, lihat setting.kioskToken.regenerate).
//     PENTING: tidak ada Auth::login()/session di sini sama sekali (lihat EnsureKioskOrPermission) —
//     supaya membuka link ini di browser yang sama dgn tab lain yg sudah login tidak pernah
//     menimpa/mengeluarkan sesi login orang itu. Token divalidasi ulang tiap request lewat URL. ───
Route::get('/kiosk-absensi/{token}', [AbsensiController::class, 'kioskEnter'])->name('absensi.kioskEnter');

Route::middleware(EnsureKioskOrPermission::class)->group(function () {
    Route::get('/absensi/scan', [AbsensiController::class, 'scan'])->name('absensi.scan');
    Route::post('/absensi/mark', [AbsensiController::class, 'mark'])->name('absensi.mark');
    Route::get('/presensi-guru/scan', [AbsensiController::class, 'scan'])->name('presensi-guru.scan');
    Route::post('/presensi-guru/mark', [PresensiGuruController::class, 'mark'])->name('presensi-guru.mark');
    Route::get('/qr-absensi', [QrAbsensiController::class, 'show'])->name('qr.absensi');
});

// Halaman "Langganan berakhir" — PUBLIK (tanpa auth) supaya siapa pun yang terkunci
// oleh middleware EnforceLangganan tetap bisa melihat penjelasannya.
Route::get('/langganan-berakhir', fn () => response()->view('langganan.berakhir'))->name('langganan.berakhir');

// Panduan SIMS: sengaja hanya auth, tidak melewati gate wajah, agar user baru tetap bisa membaca tutorial awal.
Route::middleware('auth')->get('/panduan-sims', [PanduanController::class, 'index'])->name('panduan.index');

// ─── Authenticated ────────────────────────────────────────────────────────────
// Gate EnsureFaceRegistered: siswa & guru wajib daftar wajah dulu sebelum lanjut
Route::middleware(['auth', EnsureFaceRegistered::class])->group(function () {

    Route::get('/home', [LoginController::class, 'home'])->name('auth.home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/tata-letak', [DashboardController::class, 'saveLayout'])->name('dashboard.layout');

    Route::controller(FeedbackController::class)->prefix('masukan')->name('feedback.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/buat', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
        Route::get('/badge', 'badge')->middleware('permission:manage_feedback')->name('badge');
        Route::get('/{feedback}', 'show')->name('show');
        Route::post('/{feedback}/respon', 'respond')->middleware('permission:manage_feedback')->name('respond');
    });

    // ─── Langganan (lisensi) — khusus superadmin ────────────────────────────────
    Route::middleware('role:superadmin')->prefix('langganan')->name('langganan.')
        ->controller(LanggananController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/perpanjang', 'perpanjang')->name('perpanjang');
        });

    // ─── Asisten Guru (Gateway Gemini — Fase 1) ────────────────────────────────────
    // Gateway generik; dibatasi superadmin. Fitur per-role menyusul di fase berikut.
    Route::middleware('role:superadmin')->prefix('ai')->name('ai.')->group(function () {
        Route::post('/generate', [AiController::class, 'generate'])->name('generate');
    });

    // ─── Asisten Guru Chatbot (Fase 2) ─────────────────────────────────────────────
    // Widget AI generatif hanya untuk staf/admin — siswa & orang tua memakai chatbot handoff.
    Route::middleware('role:admin,superadmin,guru,walikelas,kepala,kurikulum,kesiswaan,sapras,bendahara,sekretaris')
        ->prefix('ai/chat')->name('ai.chat.')->controller(AiChatController::class)->group(function () {
        Route::post('/', 'send')->name('send');
        Route::get('/history', 'history')->name('history');
        Route::get('/{conversation}', 'show')->name('show');
        Route::delete('/{conversation}', 'destroy')->name('destroy');
    });

    // ─── Asisten Guru (Fase 3) ────────────────────────────────────────
    // Panel tool guru (soal/rangkum/feedback). Hanya guru & wali kelas.
    Route::middleware('role:guru,walikelas')->prefix('ai/teacher')->name('ai.teacher.')->controller(AiTeacherController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/quiz', 'quiz')->name('quiz');
        Route::post('/quiz/preview', 'previewQuiz')->name('quiz.preview');
        Route::post('/quiz/export-word', 'exportQuizWord')->name('quiz.export-word');
        Route::post('/quiz/export-pdf', 'exportQuizPdf')->name('quiz.export-pdf');
        Route::post('/learning', 'learning')->name('learning');
        Route::post('/learning/preview', 'previewLearning')->name('learning.preview');
        Route::post('/learning/export-word', 'exportLearningWord')->name('learning.export-word');
        Route::post('/learning/export-pdf', 'exportLearningPdf')->name('learning.export-pdf');
        Route::post('/summary', 'summary')->name('summary');
        Route::post('/feedback', 'feedback')->name('feedback');
        Route::delete('/history/{history}', 'destroyHistory')->name('history.destroy');
    });

    // ─── Asisten Guru Narasi Data (Fase 4) ─────────────────────────────────────────
    // Controller agregasi angka server-side → AI narasikan. Pimpinan/staf sekolah.
    Route::middleware('role:admin,kepala,kurikulum,kesiswaan')->prefix('ai/analyze')->name('ai.analyze.')->controller(AiAnalyzeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/nilai', 'nilai')->name('nilai');
        Route::post('/absensi', 'absensi')->name('absensi');
        Route::post('/keuangan', 'keuangan')->name('keuangan');
    });

    // ─── Asisten Guru RAG Dokumen (Fase 5) ─────────────────────────────────────────
    // Unggah dokumen → embed; tanya-jawab berbasis isi dokumen + sitasi.
    Route::middleware('role:admin,kepala,kurikulum,kesiswaan')->prefix('ai/rag')->name('ai.rag.')->controller(AiRagController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/ask', 'ask')->name('ask');
        Route::delete('/{document}', 'destroy')->name('destroy');
    });

    // Wajib daftar wajah sendiri (dipakai gate di atas)
    Route::get('/wajah-saya', [FaceController::class, 'self'])->name('face.self');
    Route::post('/wajah-saya', [FaceController::class, 'selfStore'])->name('face.self.store');

    // Absen QR mandiri (siswa/guru) — scan QR harian + cek lokasi
    Route::get('/absen-qr', [QrAbsensiController::class, 'absen'])->name('absen.qr');
    Route::post('/absen-qr', [QrAbsensiController::class, 'mark'])->name('absen.qr.mark');

    // Ganti password & PIN
    Route::get('/ganti-password', [LoginController::class, 'changePasswordPage'])->name('ganti.password');
    Route::post('/ganti-password', [LoginController::class, 'changePassword'])->name('ganti.password.post');
    Route::post('/ganti-username', [LoginController::class, 'changeUsername'])->name('ganti.username');
    Route::get('/ganti-pin', [LoginController::class, 'changePinPage'])->name('ganti.pin');
    Route::post('/ganti-pin', [LoginController::class, 'changePin'])->name('ganti.pin.post');

    // Profil & Preferensi Tampilan
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'index')->name('profile.index');
        Route::get('/profile/edit', 'edit')->name('profile.edit');
        Route::put('/profile/update', 'update')->name('profile.update');
        Route::get('/profile/tampilan', 'preferenceEdit')->name('profile.preference');
        Route::match(['put', 'post'], '/profile/tampilan', 'preferenceUpdate')->name('profile.preference.update');
        Route::get('/profile/tampilan/reset', 'preferenceReset')->name('profile.preference.reset');
        Route::post('/profile/gaya', 'setStyle')->name('profile.style');
    });

    // Notifikasi
    Route::get('/notifications-json', [NotificationController::class, 'getNotifications'])->name('notifications.json');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('/notifications/fcm-token', [NotificationController::class, 'storeFcmToken'])->name('notifications.fcmToken.store');
    Route::delete('/notifications/fcm-token', [NotificationController::class, 'destroyFcmToken'])->name('notifications.fcmToken.destroy');
    Route::post('/fcm/token', [NotificationController::class, 'storeFcmToken'])->name('fcm.token.store.legacy');
    Route::delete('/fcm/token', [NotificationController::class, 'destroyFcmToken'])->name('fcm.token.destroy.legacy');

    // Pengumuman: riwayat untuk semua user; buat/ubah/hapus butuh izin manage_pengumuman.
    Route::controller(PengumumanController::class)->prefix('pengumuman')->name('pengumuman.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::middleware('permission:manage_pengumuman')->group(function () {
            Route::get('/buat', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            Route::get('/{pengumuman}/edit', 'edit')->name('edit');
            Route::put('/{pengumuman}', 'update')->name('update');
            Route::delete('/{pengumuman}', 'destroy')->name('destroy');
        });
        Route::get('/{pengumuman}', 'show')->name('show');
    });

    // Statistik real-time untuk ticker SIMS-NET (angka dari cache TickerStats).
    Route::get('/dashboard/ticker-stats', function () {
        return response()->json(
            TickerStats::forRole(auth()->user()->access ?? '')
        );
    })->name('dashboard.ticker-stats');

    // ─── Penilaian (guru menilai penugasan mengajarnya; admin akses semua) ───
    Route::controller(NilaiController::class)->group(function () {
        Route::get('/nilai', 'index')->name('nilai.index');
        Route::get('/nilai/saya', 'selfShow')->name('nilai.self');
        // KKTP (dulu KKM) per penugasan
        Route::get('/nilai/kktp', 'kktp')->name('nilai.kktp');
        Route::post('/nilai/kktp', 'kktpSave')->name('nilai.kktp.save');
        // Materi & Tujuan Pembelajaran
        Route::put('/nilai/materi/{materi}', 'materiUpdate')->name('nilai.materi.update');
        Route::post('/nilai/materi/{materi}/toggle', 'materiToggle')->name('nilai.materi.toggle');
        Route::delete('/nilai/materi/{materi}', 'materiDestroy')->name('nilai.materi.destroy');
        Route::post('/nilai/materi/{materi}/tupe', 'tupeStore')->name('nilai.tupe.store');
        Route::put('/nilai/tupe/{tupe}', 'tupeUpdate')->name('nilai.tupe.update');
        Route::delete('/nilai/tupe/{tupe}', 'tupeDestroy')->name('nilai.tupe.destroy');
        // Per-penugasan (ngajar)
        Route::get('/nilai/{ngajar}/materi', 'materi')->name('nilai.materi');
        Route::post('/nilai/{ngajar}/materi', 'materiStore')->name('nilai.materi.store');
        Route::post('/nilai/{ngajar}/materi/duplicate', 'duplicateMateri')->name('nilai.materi.duplicate');
        Route::get('/nilai/{ngajar}/formatif', 'formatif')->name('nilai.formatif');
        Route::post('/nilai/{ngajar}/formatif/sel', 'formatifCell')->name('nilai.formatif.cell');
        Route::get('/nilai/{ngajar}/sumatif', 'sumatif')->name('nilai.sumatif');
        Route::post('/nilai/{ngajar}/sumatif/sel', 'sumatifCell')->name('nilai.sumatif.cell');
        Route::get('/nilai/{ngajar}/penjabaran', 'penjabaran')->name('nilai.penjabaran');
        Route::post('/nilai/{ngajar}/penjabaran/sel', 'penjabaranCell')->name('nilai.penjabaran.cell');
        Route::get('/nilai/{ngajar}/pts', 'pts')->name('nilai.pts');
        Route::post('/nilai/{ngajar}/pts/sel', 'ptsCell')->name('nilai.pts.cell');
        Route::get('/nilai/{ngajar}/pas', 'pas')->name('nilai.pas');
        Route::post('/nilai/{ngajar}/pas/sel', 'pasCell')->name('nilai.pas.cell');
        Route::get('/nilai/{ngajar}/rapor', 'rapor')->name('nilai.rapor');
        Route::post('/nilai/{ngajar}/rapor/nilai', 'raporNilai')->name('nilai.rapor.nilai');
        Route::post('/nilai/{ngajar}/rapor/desk', 'raporDesk')->name('nilai.rapor.desk');
        Route::post('/nilai/{ngajar}/rapor/konfirmasi', 'raporKonfirmasi')->name('nilai.rapor.konfirmasi');
        Route::post('/nilai/{ngajar}/rapor/batal', 'raporBatalKonfirmasi')->name('nilai.rapor.batal');
    });

    // ─── Rekap nilai (admin/kurikulum/kepala = semua; walikelas = kelasnya) ───
    Route::get('/rekap-nilai', [RekapController::class, 'nilai'])->name('rekap.nilai');

    // ─── Cetak rapor (akses sama dgn rekap; walikelas = kelasnya) ───
    Route::get('/cetak-rapor', [CetakRaporController::class, 'index'])->name('cetak.rapor.index');
    Route::get('/cetak-rapor/cetak', [CetakRaporController::class, 'cetak'])->name('cetak.rapor');

    // ─── Forum Diskusi Kelas (modul berdiri sendiri; izin via matriks forum) ───
    Route::prefix('forum')->name('forum.')->group(function () {
        Route::get('/', [ForumController::class, 'index'])->name('index');
        Route::get('/akses', [ForumAccessController::class, 'edit'])->name('access.edit');
        Route::post('/akses', [ForumAccessController::class, 'update'])->name('access.update');
        Route::get('/buat', [ForumController::class, 'create'])->name('create');
        Route::post('/', [ForumController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::post('/reaksi', [ForumReactionController::class, 'toggle'])->name('reaction.toggle');
        Route::put('/komentar/{comment}', [ForumCommentController::class, 'update'])->name('comment.update');
        Route::delete('/komentar/{comment}', [ForumCommentController::class, 'destroy'])->name('comment.destroy');
        Route::post('/komentar/{comment}/terbaik', [ForumCommentController::class, 'best'])->name('comment.best');
        Route::get('/{topic}/comments-html', [ForumCommentController::class, 'commentsHtml'])->name('comment.html');
        Route::get('/{topic}', [ForumController::class, 'show'])->name('show');
        Route::get('/{topic}/edit', [ForumController::class, 'edit'])->name('edit');
        Route::put('/{topic}', [ForumController::class, 'update'])->name('update');
        Route::delete('/{topic}', [ForumController::class, 'destroy'])->name('destroy');
        Route::post('/{topic}/pin', [ForumController::class, 'togglePin'])->name('pin');
        Route::post('/{topic}/lock', [ForumController::class, 'toggleLock'])->name('lock');
        Route::get('/{topic}/presence', [ForumController::class, 'presence'])->name('presence');
        Route::post('/{topic}/komentar', [ForumCommentController::class, 'store'])->middleware('throttle:30,1')->name('comment.store');
    });

    // ─── Ruang Kelas (Classroom) — modul kelas digital B'tive ───
    Route::prefix('ruang-kelas')->name('classroom.')->group(function () {
        Route::get('/', [ClassroomController::class, 'index'])->name('index');
        // Navigasi: kelas → mapel (auto-provision ruang)
        Route::get('/kelas/{kelas}', [ClassroomController::class, 'kelas'])->name('kelas');
        Route::get('/kelas/{kelas}/mapel/{pelajaran}', [ClassroomController::class, 'subject'])->name('subject');

        // Materi (segmen literal — sebelum {classroom})
        Route::get('/materi/file/{file}', [ClassroomMaterialController::class, 'download'])->name('material.file');
        Route::get('/materi/file/{file}/lihat', [ClassroomMaterialController::class, 'preview'])->name('material.file.preview');
        Route::get('/materi/{material}/edit', [ClassroomMaterialController::class, 'edit'])->name('material.edit');
        Route::post('/materi/{material}/kunci', [ClassroomMaterialController::class, 'toggleLock'])->name('material.togglelock');
        Route::post('/materi/{material}/buka', [ClassroomMaterialController::class, 'unlock'])->middleware('throttle:20,1')->name('material.unlock');
        Route::post('/materi/{material}/keluar', [ClassroomMaterialController::class, 'lockExit'])->name('material.lockexit');
        Route::get('/materi/{material}/pemantauan', [ClassroomMaterialController::class, 'lockEvents'])->name('material.lockevents');
        Route::get('/materi/{material}', [ClassroomMaterialController::class, 'show'])->name('material.show');
        Route::post('/materi/{material}/update', [ClassroomMaterialController::class, 'update'])->name('material.update');
        Route::post('/materi/{material}/komentar', [ClassroomCommentController::class, 'storeMaterial'])->middleware('throttle:40,1')->name('material.comment');
        Route::post('/materi/{material}/tutup-meet', [ClassroomMaterialController::class, 'closeMeet'])->name('material.closemeet');
        Route::delete('/materi/{material}', [ClassroomMaterialController::class, 'destroy'])->name('material.destroy');

        // Tugas
        Route::get('/tugas/file/{file}', [ClassroomAssignmentController::class, 'download'])->name('assignment.file');
        Route::get('/tugas/{assignment}/penilaian', [ClassroomAssignmentController::class, 'submissions'])->name('assignment.grading');
        Route::get('/tugas/{assignment}/edit', [ClassroomAssignmentController::class, 'edit'])->name('assignment.edit');
        Route::post('/tugas/{assignment}/kunci', [ClassroomAssignmentController::class, 'toggleLock'])->name('assignment.togglelock');
        Route::post('/tugas/{assignment}/buka', [ClassroomAssignmentController::class, 'unlock'])->middleware('throttle:20,1')->name('assignment.unlock');
        Route::post('/tugas/{assignment}/keluar', [ClassroomAssignmentController::class, 'lockExit'])->name('assignment.lockexit');
        Route::get('/tugas/{assignment}/pemantauan', [ClassroomAssignmentController::class, 'lockEvents'])->name('assignment.lockevents');
        Route::get('/tugas/{assignment}', [ClassroomAssignmentController::class, 'show'])->name('assignment.show');
        Route::post('/tugas/{assignment}/update', [ClassroomAssignmentController::class, 'update'])->name('assignment.update');
        Route::post('/tugas/{assignment}/transfer-nilai', [ClassroomAssignmentController::class, 'transferGrades'])->name('assignment.transfer');
        Route::post('/tugas/{assignment}/komentar', [ClassroomCommentController::class, 'storeAssignment'])->middleware('throttle:40,1')->name('assignment.comment');
        Route::delete('/tugas/{assignment}', [ClassroomAssignmentController::class, 'destroy'])->name('assignment.destroy');
        Route::post('/tugas/{assignment}/kumpul', [ClassroomSubmissionController::class, 'store'])->middleware('throttle:30,1')->name('submission.store');
        Route::delete('/komentar/{comment}', [ClassroomCommentController::class, 'destroy'])->name('comment.destroy');
        Route::get('/comments-json/{type}/{uuid}', [ClassroomCommentController::class, 'fetch'])->name('comments.json');

        // Submission
        Route::post('/submission/{submission}/nilai', [ClassroomSubmissionController::class, 'grade'])->name('submission.grade');
        Route::post('/submission/{submission}/kembalikan', [ClassroomSubmissionController::class, 'returnSubmission'])->name('submission.return');
        Route::get('/submission/file/{file}', [ClassroomSubmissionController::class, 'download'])->name('submission.file');

        // Ruang mapel (auto-provisioned) + halaman tambah konten terpisah
        Route::get('/{classroom}/materi/buat', [ClassroomMaterialController::class, 'create'])->name('material.create');
        Route::get('/{classroom}/tugas/buat', [ClassroomAssignmentController::class, 'create'])->name('assignment.create');
        Route::get('/{classroom}', [ClassroomController::class, 'show'])->name('show');
        Route::post('/{classroom}/materi', [ClassroomMaterialController::class, 'store'])->middleware('throttle:30,1')->name('material.store');
        Route::post('/{classroom}/tugas', [ClassroomAssignmentController::class, 'store'])->middleware('throttle:30,1')->name('assignment.store');
    });

    // ─── Ekskul (pembina/guru & admin; CRUD master admin-only di controller) ───
    Route::controller(EkskulController::class)->group(function () {
        Route::get('/ekskul', 'index')->name('ekskul.index');
        Route::post('/ekskul', 'store')->name('ekskul.store');
        Route::put('/ekskul/{uuid}', 'update')->name('ekskul.update');
        Route::delete('/ekskul/{uuid}', 'destroy')->name('ekskul.destroy');
        Route::get('/ekskul/{uuid}/nilai', 'nilai')->name('ekskul.nilai');
        Route::post('/ekskul/{uuid}/nilai/sel', 'nilaiCell')->name('ekskul.nilai.cell');
    });

    // ─── Perangkat Ajar (guru upload sendiri; monitoring via permission manage_perangkat, guard di controller) ───
    Route::prefix('perangkat-ajar')->name('perangkat.')->controller(PerangkatAjarController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/saya', 'self')->name('self');
        Route::post('/jenis', 'store')->name('jenis.store');
        Route::put('/jenis/{list}', 'update')->name('jenis.update');
        Route::delete('/jenis/{list}', 'destroy')->name('jenis.destroy');
        Route::get('/file/{file}/unduh', 'download')->name('download');
        Route::get('/file/{file}/lihat', 'preview')->name('preview');
        Route::delete('/file/{file}', 'destroyFile')->name('file.destroy');
        Route::get('/{guru}/zip', 'zip')->name('zip');
        Route::post('/{guru}/{list}/upload', 'upload')->middleware('throttle:30,1')->name('upload');
        Route::get('/{guru}', 'show')->name('show');
    });

    // ─── Kalender Absensi & Agenda (admin & kurikulum; guard di controller) ───
    Route::prefix('kalender-absensi')->name('kalender.')->controller(KalenderController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/toggle', 'toggle')->name('toggle');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::post('/mode', 'mode')->name('mode');
    });

    // ─── 7 KAIH (siswa isi harian sebelum absen; rekap walikelas/admin; soal admin/kurikulum) ───
    Route::prefix('kaih')->name('kaih.')->controller(KaihController::class)->group(function () {
        Route::get('/isi', 'isi')->name('isi');
        Route::post('/isi', 'simpan')->name('simpan');
        Route::get('/rekap', 'rekap')->name('rekap');
        Route::get('/rekap/{siswa}/override', 'overrideForm')->name('override.form');
        Route::post('/rekap/{siswa}/override', 'overrideStore')->name('override.store');
        Route::get('/soal', 'soal')->name('soal');
        Route::post('/toggle-aktif', 'toggleAktif')->name('toggle-aktif');
        Route::post('/soal', 'soalStore')->name('soal.store');
        Route::put('/soal/{pertanyaan}', 'soalUpdate')->name('soal.update');
        Route::delete('/soal/{pertanyaan}', 'soalDestroy')->name('soal.destroy');
        Route::post('/soal/{pertanyaan}/opsi', 'opsiStore')->name('opsi.store');
        Route::put('/opsi/{opsi}', 'opsiUpdate')->name('opsi.update');
        Route::delete('/opsi/{opsi}', 'opsiDestroy')->name('opsi.destroy');
    });

    // ─── Agenda Guru (guru mengisi; rekap utk admin/kepala/kurikulum) ───
    Route::prefix('agenda')->name('agenda.')->controller(AgendaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/slots', 'slots')->name('slots');           // AJAX: jadwal per tanggal
        Route::get('/siswa', 'siswa')->name('siswa');           // AJAX: siswa per jadwal
        Route::get('/buat', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:60,1')->name('store');
        Route::get('/rekap', 'rekap')->name('rekap');
        Route::get('/batas', 'batas')->name('batas');
        Route::get('/batas/unduh', 'cetakBatas')->name('batas.excel');
        Route::get('/{agenda}/edit', 'edit')->name('edit');
        Route::put('/{agenda}', 'update')->name('update');
        Route::delete('/{agenda}', 'destroy')->name('destroy');
        Route::post('/{agenda}/validasi', 'validasi')->name('validasi');
    });

    // ─── Agenda Rapat / Notulen Rapat — admin/kurikulum/kepala atau guru sekretaris ───
    Route::prefix('rapat')->name('rapat.')->controller(RapatController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/buat', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/sekretaris', 'sekretaris')->name('sekretaris');
        Route::post('/sekretaris/{guru}/toggle', 'sekretarisToggle')->name('sekretaris.toggle');
        Route::get('/{rapat}', 'show')->name('show');
        Route::get('/{rapat}/edit', 'edit')->name('edit');
        Route::put('/{rapat}', 'update')->name('update');
        Route::delete('/{rapat}', 'destroy')->name('destroy');
        Route::get('/{rapat}/hadir', 'hadir')->name('hadir');
        Route::post('/{rapat}/hadir', 'hadirStore')->name('hadir.store');
        Route::get('/{rapat}/dokumentasi', 'dokumentasi')->name('dokumentasi');
        Route::post('/{rapat}/dokumentasi', 'dokumentasiStore')->name('dokumentasi.store');
        Route::delete('/{rapat}/dokumentasi/{dokumentasi}', 'dokumentasiDestroy')->name('dokumentasi.destroy');
        Route::get('/{rapat}/cetak', 'cetak')->name('cetak');
    });

    // ─── Poin/Aturan (lama, ledger basis 100) — dua sistem, dipilih di Pengaturan ───
    Route::prefix('poin')->name('poin.')->group(function () {
        // Guard peran kini ditangani langsung di PoinController (RBAC)
        Route::group([], function () {
            Route::get('/', [PoinController::class, 'index'])->name('index');
            Route::get('/buat', [PoinController::class, 'create'])->name('create');
            Route::post('/', [PoinController::class, 'store'])->name('store');
            Route::get('/{aturan}/edit', [PoinController::class, 'edit'])->name('edit');
            Route::put('/{aturan}', [PoinController::class, 'update'])->name('update');
            Route::delete('/{aturan}', [PoinController::class, 'destroy'])->name('destroy');
            Route::get('/export', [PoinController::class, 'exportAturan'])->name('export');
            Route::get('/import', [PoinController::class, 'importForm'])->name('importForm');
            Route::post('/import', [PoinController::class, 'importAturan'])->name('import');

            Route::get('/siswa/{siswa}/buat', [PoinController::class, 'poinCreate'])->name('siswa.create');
            Route::post('/siswa/{siswa}', [PoinController::class, 'poinStore'])->name('siswa.store');
            Route::delete('/entri/{poin}', [PoinController::class, 'poinDelete'])->name('entri.delete');
            Route::get('/aturan-json', [PoinController::class, 'poinGetAturan'])->name('aturan.json');

            Route::get('/temp', [PoinController::class, 'tempIndex'])->name('temp.index');
            Route::get('/temp/riwayat', [PoinController::class, 'tempHistory'])->name('temp.history');
            Route::post('/temp/bulk', [PoinController::class, 'tempBulkUpdate'])->name('temp.bulkUpdate');
            Route::post('/temp/{temp}', [PoinController::class, 'tempUpdate'])->name('temp.update');

            Route::get('/dashboard', [PoinController::class, 'dashboard'])->name('dashboard');
        });

        // Lihat ringkasan poin siswa: admin/kesiswaan (semua kelas) + walikelas (kelasnya saja, disaring di controller)
        // Guard peran ditangani langsung di PoinController
        Route::group([], function () {
            Route::get('/siswa', [PoinController::class, 'poinIndex'])->name('siswa.index');
            Route::get('/siswa/{siswa}', [PoinController::class, 'poinShow'])->name('siswa.show');
        });

        // Pengajuan guru/walikelas/sekretaris — guard peran dilakukan di controller
        Route::get('/guru', [PoinController::class, 'guruIndex'])->name('guru.index');
        Route::get('/guru/{siswa}/buat', [PoinController::class, 'guruCreate'])->name('guru.create');
        Route::post('/guru/{siswa}', [PoinController::class, 'guruStore'])->name('guru.store');
        Route::get('/guru/riwayat', [PoinController::class, 'guruRiwayat'])->name('guru.riwayat');

        // Lihat sendiri (siswa/orangtua)
        Route::get('/saya', [PoinController::class, 'selfShow'])->name('self');
    });

    // ─── P3: Pelanggaran, Prestasi, Partisipasi (baru, akumulatif per semester) ───
    Route::prefix('p3')->name('p3.')->group(function () {
        // Guard peran kini ditangani langsung di P3Controller (RBAC)
        Route::group([], function () {
            Route::get('/', [P3Controller::class, 'index'])->name('index');
            Route::get('/buat', [P3Controller::class, 'create'])->name('create');
            Route::post('/', [P3Controller::class, 'store'])->name('store');
            Route::get('/{kategori}/edit', [P3Controller::class, 'edit'])->name('edit');
            Route::put('/{kategori}', [P3Controller::class, 'update'])->name('update');
            Route::delete('/{kategori}', [P3Controller::class, 'destroy'])->name('destroy');
            Route::get('/kategori-json', [P3Controller::class, 'kategoriGet'])->name('kategori.json');

            Route::get('/siswa/{siswa}/buat', [P3Controller::class, 'createPoin'])->name('siswa.create');
            Route::post('/siswa/{siswa}', [P3Controller::class, 'storePoin'])->name('siswa.store');
            Route::get('/siswa/{siswa}/print', [P3Controller::class, 'printPoin'])->name('siswa.print');
            Route::get('/entri/{poin}/edit', [P3Controller::class, 'editPoin'])->name('entri.edit');
            Route::put('/entri/{poin}', [P3Controller::class, 'updatePoin'])->name('entri.update');
            Route::delete('/entri/{poin}', [P3Controller::class, 'deletePoin'])->name('entri.delete');

            Route::get('/temp', [P3Controller::class, 'tempIndex'])->name('temp.index');
            Route::get('/temp/riwayat', [P3Controller::class, 'tempHistory'])->name('temp.history');
            Route::put('/temp/{temp}/approve', [P3Controller::class, 'tempApprove'])->name('temp.approve');
            Route::put('/temp/{temp}/disapprove', [P3Controller::class, 'tempDisapprove'])->name('temp.disapprove');
        });

        // Lihat ringkasan P3 siswa: admin/kesiswaan (semua kelas) + walikelas (kelasnya saja, disaring di controller)
        // Guard peran ditangani langsung di P3Controller
        Route::group([], function () {
            Route::get('/siswa', [P3Controller::class, 'siswaIndex'])->name('siswa.index');
            Route::get('/siswa/{siswa}', [P3Controller::class, 'siswaShow'])->name('siswa.show');
        });

        Route::get('/guru', [P3Controller::class, 'guruIndex'])->name('guru.index');
        Route::get('/guru/{siswa}/buat', [P3Controller::class, 'guruCreate'])->name('guru.create');
        Route::post('/guru/{siswa}', [P3Controller::class, 'guruStore'])->name('guru.store');
        Route::get('/guru/riwayat', [P3Controller::class, 'guruRiwayat'])->name('guru.riwayat');

        Route::get('/saya', [P3Controller::class, 'selfShow'])->name('self');
    });

    // ─── Absensi Siswa: admin (semua kelas) + wali kelas (kelasnya saja) — guard peran
    //     ditangani langsung di AbsensiController (canAccess('manage_absensi') || walikelas),
    //     JANGAN pasang middleware permission: di sini, nanti wali kelas dgn access role lain
    //     (mis. kesiswaan/guru) yg belum diberi izin manage_absensi malah keblokir duluan. ───
    Route::group([], function () {
        Route::get('/absensi', [AbsensiController::class, 'index'])->name('absensi.index');
        Route::post('/absensi', [AbsensiController::class, 'store'])->name('absensi.store');
        Route::get('/absensi/rekap', [AbsensiController::class, 'rekap'])->name('absensi.rekap');
    });

    // ─── Wali Kelas: data siswa kelasnya, reset password, set sekretaris — guard peran
    //     ditangani langsung di WalikelasController/NilaiController (cek relasi guru->walikelas,
    //     bukan access role), sama seperti Absensi di atas. JANGAN pasang role: di sini. ───
    Route::prefix('walikelas')->name('walikelas.')->group(function () {
        Route::get('/siswa', [WalikelasController::class, 'siswaIndex'])->name('siswa.index');
        Route::get('/siswa/{siswa}', [WalikelasController::class, 'siswaShow'])->name('siswa.show');
        Route::post('/siswa/{siswa}/reset', [WalikelasController::class, 'resetSiswa'])->name('siswa.reset');
        Route::post('/siswa/{siswa}/reset-ortu', [WalikelasController::class, 'resetOrangtua'])->name('siswa.resetOrtu');
        Route::get('/sekretaris', [WalikelasController::class, 'sekretarisForm'])->name('sekretaris.form');
        Route::post('/sekretaris', [WalikelasController::class, 'sekretarisStore'])->name('sekretaris.store');
        Route::get('/nilai', [NilaiController::class, 'walikelasNilaiIndex'])->name('nilai.index');
    });
    // ─── Admin ─────────────────────────────────────────────────────────────
    Route::middleware('permission:manage_users')->group(function () {
        // Guru
        Route::get('/guru/import/kredensial', [GuruController::class, 'importKredensial'])->name('guru.import.kredensial');
        Route::get('/guru/import/template', [GuruController::class, 'downloadTemplate'])->name('guru.import.template');
        Route::get('/guru/import', [GuruController::class, 'importForm'])->name('guru.import.form');
        Route::post('/guru/import', [GuruController::class, 'import'])->name('guru.import');
        Route::resource('/guru', GuruController::class);
        Route::post('/guru/{uuid}/reset', [GuruController::class, 'reset'])->name('guru.reset');
        Route::get('/guru/{uuid}/pelajaran', [GuruController::class, 'pelajaran'])->name('guru.pelajaran');
        Route::post('/guru/{uuid}/pelajaran', [GuruController::class, 'ngajar'])->name('guru.ngajar');
        Route::delete('/guru/pelajaran/{uuid}/hapus', [GuruController::class, 'hapusNgajar'])->name('guru.hapusNgajar');

        // Kelas
        Route::resource('/kelas', KelasController::class)->except('show');
        Route::get('/kelas/{uuid}/walikelas', [KelasController::class, 'showWalikelas'])->name('kelas.showWalikelas');
        Route::post('/kelas/{uuid}/walikelas', [KelasController::class, 'walikelas'])->name('kelas.walikelas');
        Route::get('/kelas/setKelas', [KelasController::class, 'setKelasSiswa'])->name('kelas.setKelas');
        Route::post('/kelas/{uuid}/saveRombel', [KelasController::class, 'saveRombel'])->name('kelas.saveRombel');
        Route::get('/kelas/setKelas/histori', [KelasController::class, 'historiRombel'])->name('kelas.historiRombel');
        Route::post('/kelas/setKelas/histori/{uuid}/hapus', [KelasController::class, 'historiHapus'])->name('kelas.historiHapus');

        // Siswa
        Route::resource('/siswa', SiswaController::class);
        Route::post('/siswa/{uuid}/reset/siswa', [SiswaController::class, 'resetSiswa'])->name('siswa.reset');
        Route::post('/siswa/{uuid}/reset/ortu', [SiswaController::class, 'resetOrangtua'])->name('siswa.resetOrtu');

        // Pelajaran (semua AJAX, tanpa halaman create/edit terpisah)
        Route::get('/pelajaran', [PelajaranController::class, 'index'])->name('pelajaran.index');
        Route::post('/pelajaran', [PelajaranController::class, 'store'])->name('pelajaran.store');
        Route::put('/pelajaran/{uuid}', [PelajaranController::class, 'update'])->name('pelajaran.update');
        Route::delete('/pelajaran/{uuid}', [PelajaranController::class, 'destroy'])->name('pelajaran.destroy');
        Route::post('/pelajaran/sorting', [PelajaranController::class, 'sorting'])->name('pelajaran.sorting');

        // Import Siswa
        Route::get('/siswa/import', [SiswaController::class, 'importForm'])->name('siswa.importForm');
        Route::post('/siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
        Route::get('/siswa/import/template', [SiswaController::class, 'downloadTemplate'])->name('siswa.template');
        Route::get('/siswa/import/kredensial', [SiswaController::class, 'importKredensial'])->name('siswa.import.kredensial');

        // Kartu Pelajar Digital — kelola per siswa (admin)
        Route::get('/kartu-pelajar/kelola', [KartuPelajarController::class, 'kelola'])->name('kartu-pelajar.kelola');
        Route::get('/kartu-pelajar/kelola/cetak', [KartuPelajarController::class, 'cetakTingkat'])->name('kartu-pelajar.cetak');
        Route::post('/kartu-pelajar/kelola/{siswa}', [KartuPelajarController::class, 'store'])->name('kartu-pelajar.store');
        Route::get('/kartu-pelajar/kelola/{siswa}/lihat', [KartuPelajarController::class, 'lihatAdmin'])->name('kartu-pelajar.kelola.lihat');
        Route::delete('/kartu-pelajar/kelola/{siswa}', [KartuPelajarController::class, 'destroy'])->name('kartu-pelajar.destroy');
    });

    // ─── Cetak Data (admin only) — export Excel siswa/guru/kelas/absensi guru/agenda/nilai ───
    Route::middleware('role:admin')->prefix('cetak')->name('cetak.')->controller(CetakController::class)->group(function () {
        Route::get('/siswa', 'siswa')->name('siswa.index');
        Route::get('/siswa/{params}', 'cetakSiswa')->name('siswa.excel');
        Route::get('/guru', 'guru')->name('guru.index');
        Route::get('/guru/unduh', 'cetakGuru')->name('guru.excel');
        Route::get('/kelas', 'kelas')->name('kelas.index');
        Route::get('/kelas/unduh', 'cetakKelas')->name('kelas.excel');
        Route::get('/absensi-guru', 'absensiGuru')->name('absensiGuru.index');
        Route::get('/absensi-guru/unduh', 'cetakAbsensiGuru')->name('absensiGuru.excel');
        Route::get('/agenda', 'agenda')->name('agenda.index');
        Route::get('/agenda/unduh', 'cetakAgenda')->name('agenda.excel');
        Route::get('/buku-batas', 'bukuBatas')->name('bukuBatas.index');
        Route::get('/buku-batas/unduh', 'cetakBukuBatas')->name('bukuBatas.excel');
        Route::get('/formatif', 'formatif')->name('formatif.index');
        Route::get('/formatif/{params}', 'cetakFormatif')->name('formatif.excel');
        Route::get('/sumatif', 'sumatif')->name('sumatif.index');
        Route::get('/sumatif/{params}', 'cetakSumatif')->name('sumatif.excel');
        // nama route "nilaiRapor" (bukan "rapor") supaya tidak bentrok dgn cetak.rapor.index (Cetak Rapor/CetakRaporController)
        Route::get('/rapor', 'rapor')->name('nilaiRapor.index');
        Route::get('/rapor/{params}', 'cetakRapor')->name('nilaiRapor.excel');
        Route::get('/pas', 'pas')->name('pas.index');
        Route::get('/pas/{params}', 'cetakPas')->name('pas.excel');
        Route::get('/penjabaran', 'penjabaran')->name('penjabaran.index');
        Route::get('/penjabaran/{params}', 'cetakPenjabaran')->name('penjabaran.excel');
    });

    Route::middleware('permission:manage_jadwal')->group(function () {
        // Jadwal Pelajaran — editor grid per hari + generate + master jam
        Route::get('/jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
        Route::get('/jadwal/kelas', [JadwalController::class, 'kelasView'])->name('jadwal.kelas');
        Route::get('/jadwal/jp', [JadwalController::class, 'jpForm'])->name('jadwal.jp');
        Route::post('/jadwal/jp', [JadwalController::class, 'jpSave'])->name('jadwal.jp.save');
        Route::post('/jadwal/cell', [JadwalController::class, 'saveCell'])->name('jadwal.cell.save');
        Route::delete('/jadwal/cell', [JadwalController::class, 'clearCell'])->name('jadwal.cell.clear');
        Route::post('/jadwal/generate', [JadwalController::class, 'generate'])->name('jadwal.generate');
        Route::post('/jadwal/jam', [JadwalController::class, 'jamStore'])->name('jadwal.jam.store');
        Route::post('/jadwal/jam/copy', [JadwalController::class, 'jamCopy'])->name('jadwal.jam.copy');
        Route::delete('/jadwal/jam/{uuid}', [JadwalController::class, 'jamDestroy'])->name('jadwal.jam.destroy');

    });

    Route::middleware('permission:manage_absensi')->group(function () {
        // Absensi wajah (face recognition)
        Route::get('/absensi/wajah', [AbsensiController::class, 'wajah'])->name('absensi.wajah');
        Route::get('/absensi/wajah-guru', [AbsensiController::class, 'wajahGuru'])->name('absensi.wajah-guru');
        Route::post('/siswa/{uuid}/wajah', [SiswaController::class, 'storeFace'])->name('siswa.face.store');
        Route::delete('/siswa/{uuid}/wajah', [SiswaController::class, 'destroyFace'])->name('siswa.face.destroy');

        // Presensi Guru (koreksi manual + rekap — TIDAK termasuk scan/mark, lihat grup kiosk publik di atas)
        Route::get('/presensi-guru', [PresensiGuruController::class, 'index'])->name('presensi-guru.index');
        Route::post('/presensi-guru', [PresensiGuruController::class, 'store'])->name('presensi-guru.store');
        Route::get('/presensi-guru/rekap', [PresensiGuruController::class, 'rekap'])->name('presensi-guru.rekap');
        Route::post('/guru/{uuid}/wajah', [GuruController::class, 'storeFace'])->name('guru.face.store');
        Route::delete('/guru/{uuid}/wajah', [GuruController::class, 'destroyFace'])->name('guru.face.destroy');
        Route::get('/wajah-galeri', [FaceController::class, 'gallery'])->name('wajah.galeri');
        Route::get('/wajah-ganda', [FaceController::class, 'duplicates'])->name('wajah.ganda');

    });

    Route::middleware('permission:manage_settings')->group(function () {
        // Setting
        Route::controller(SettingController::class)->prefix('settings')->group(function () {
            Route::get('/', 'index')->name('setting.index');
            Route::post('/semester', 'updateSemester')->name('setting.semester');
            Route::post('/semester/store', 'storeSemester')->name('setting.semester.store');
            Route::post('/identitas', 'setIdentitasSekolah')->name('setting.identitas');
            Route::post('/media-sosial', 'setMediaSosial')->name('setting.mediaSosial');
            Route::post('/poin-terlambat', 'setPoinTerlambat')->name('setting.poinTerlambat');
            Route::post('/waktu-terlambat', 'setWaktuTerlambat')->name('setting.waktuTerlambat');
            Route::post('/mapel-rapor', 'setMapelRapor')->name('setting.mapelRapor');
            Route::post('/tanggal-rapor', 'setTanggalRapor')->name('setting.tanggalRapor');
            Route::post('/cara-absensi', 'setCaraAbsensi')->name('setting.caraAbsensi');
            Route::post('/kiosk-token/regenerate', 'regenerateKioskToken')->name('setting.kioskToken.regenerate');
            Route::post('/agenda-wajib-pulang', 'setAgendaWajibPulang')->name('setting.agendaWajibPulang');
            Route::post('/jenis-aturan', 'setJenisAturan')->name('setting.jenisAturan');
            Route::post('/poin-terlambat-aturan', 'setPoinTerlambatAturan')->name('setting.poinTerlambatAturan');
            Route::post('/lokasi-qr', 'setLokasiQr')->name('setting.lokasiQr');
            Route::post('/rumus-rapor', 'setRumusRapor')->name('setting.rumusRapor');
            Route::post('/walikelas-lihat-nilai', 'setWalikelasLihatNilai')->name('setting.walikelasLihatNilai');
            Route::get('/penjabaran', 'penjabaran')->name('setting.penjabaran');
            Route::post('/penjabaran', 'penjabaranSave')->name('setting.penjabaran.save');
            Route::post('/tp-range', 'setTpRange')->name('setting.tpRange');
            Route::get('/kop-rapor', 'kopRapor')->name('setting.kopRapor');
            Route::post('/kop-rapor', 'kopRaporSave')->name('setting.kopRapor.save');

            // Unduh Aplikasi (upload APK + Installer Windows)
            Route::post('/app-download', 'setAppDownload')->name('setting.appDownload');

            // Role Permissions
            Route::get('/roles', 'roles')->name('setting.roles');
            Route::post('/roles', 'rolesSave')->name('setting.roles.save');
        });
    });

    // ─── Unduh Aplikasi: halaman & unduhan untuk SEMUA pengguna login ──────
    Route::controller(AppDownloadController::class)->group(function () {
        Route::get('/unduh-aplikasi', 'page')->name('app.download');
        Route::get('/unduh-aplikasi/{platform}', 'download')->name('app.download.file');
    });

    // ─── Kartu Pelajar Digital: milik siswa yang login ─────────────────────
    Route::controller(KartuPelajarController::class)->group(function () {
        Route::get('/kartu-pelajar', 'self')->name('kartu-pelajar.self');
        Route::get('/kartu-pelajar/lihat', 'lihatSelf')->name('kartu-pelajar.lihat');
        Route::get('/kartu-pelajar/unduh', 'unduhSelf')->name('kartu-pelajar.unduh');
    });

    // ─── Akses Jadwal per Guru (Admin + Ekstra Role) ───────────────────────
    Route::middleware('role:admin,kurikulum,kepala,kesiswaan,sapras,guru,walikelas')->group(function () {
        Route::get('/jadwal/guru', [JadwalController::class, 'guruView'])->name('jadwal.guru');
    });

    // ─── Keuangan: Bendahara (juga admin/superadmin) ───────────────────────
    Route::middleware('permission:manage_keuangan')->prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/', [KeuanganController::class, 'index'])->name('index');
        Route::get('/verifikasi', [KeuanganController::class, 'verifikasi'])->name('verifikasi');
        Route::post('/verifikasi/verify', [KeuanganController::class, 'verifyBatch'])->name('verify-batch');
        Route::post('/verifikasi/validate', [KeuanganController::class, 'validateBatch'])->name('validate-batch');
        Route::post('/verifikasi/revise', [KeuanganController::class, 'reviseBatch'])->name('revise-batch');
        Route::post('/verifikasi/reject', [KeuanganController::class, 'rejectBatch'])->name('reject-batch');
        Route::get('/bank', [KeuanganController::class, 'bank'])->name('bank');
        Route::post('/bank', [KeuanganController::class, 'bankUpdate'])->name('bank.update');
        Route::get('/kelas/{kelas}', [KeuanganController::class, 'kelas'])->name('kelas');
        Route::get('/kelas/{kelas}/pengaturan', [KeuanganController::class, 'pengaturanKelas'])->name('kelas.pengaturan');
        Route::post('/kelas/{kelas}/pengaturan', [KeuanganController::class, 'simpanPengaturanKelas'])->name('kelas.pengaturan.simpan');
        Route::post('/pembayaran/{pembayaran}/cell', [KeuanganController::class, 'cell'])->name('cell');
    });

    // ─── Keuangan: Tagihan SPP siswa & orang tua ───────────────────────────
    Route::prefix('tagihan-spp')->name('keuangan.tagihan.')->group(function () {
        Route::get('/', [TagihanController::class, 'index'])->name('index');
        // Streaming bukti dari disk privat (auth + cek role/kepemilikan). Sebelum {pembayaran}.
        Route::get('/{pembayaran}/bukti-file', [TagihanController::class, 'buktiFile'])->name('bukti');
        Route::get('/{pembayaran}', [TagihanController::class, 'show'])->name('show');
        Route::post('/{pembayaran}/bukti', [TagihanController::class, 'upload'])->name('upload');
    });
});

// ─── Chatbot Asisten Sekolah ────────────────────────────────────────────────
// Sengaja DI LUAR gate EnsureFaceRegistered agar widget chat selalu bisa diakses.

// Widget penanya (siswa & orang tua).
Route::middleware(['auth', 'chatbot.user'])->group(function () {
    Route::get('/chatbot', [ChatbotController::class, 'show'])->name('chatbot.show');
    Route::post('/chatbot/send', [ChatbotController::class, 'send'])->middleware('throttle:30,1')->name('chatbot.send');
    Route::post('/chatbot/upload', [ChatbotController::class, 'upload'])->middleware('throttle:30,1')->name('chatbot.upload');
    Route::post('/chatbot/upload-file', [ChatbotController::class, 'uploadFile'])->middleware('throttle:30,1')->name('chatbot.upload-file');
    Route::get('/chatbot/poll', [ChatbotController::class, 'poll'])->middleware('throttle:60,1')->name('chatbot.poll');
    Route::get('/chatbot/attachment/{message}', [ChatbotController::class, 'attachment'])->name('chatbot.attachment');
    Route::get('/chatbot/unread', [ChatbotController::class, 'unread'])->name('chatbot.unread');

    // Handoff sisi user.
    Route::post('/chatbot/{conversation}/request-human', [ChatbotController::class, 'requestHuman'])->name('chatbot.request-human');
    Route::post('/chatbot/{conversation}/back-to-bot', [ChatbotController::class, 'backToBot'])->name('chatbot.back-to-bot');
});

// Inbox admin (hanya admin/superadmin).
Route::middleware(['auth', 'role:admin'])->prefix('chatbot/admin')->name('chatbot.admin.')->group(function () {
    Route::get('/inbox', [ChatbotAdminController::class, 'inbox'])->name('inbox');
    Route::get('/queue', [ChatbotAdminController::class, 'queue'])->name('queue');
    Route::get('/history', [ChatbotAdminController::class, 'history'])->name('history');
    Route::get('/{conversation}/messages', [ChatbotAdminController::class, 'messages'])->name('messages');
    Route::post('/{conversation}/assign', [ChatbotAdminController::class, 'assign'])->name('assign');
    Route::post('/{conversation}/reply', [ChatbotAdminController::class, 'reply'])->name('reply');
    Route::post('/{conversation}/reply-image', [ChatbotAdminController::class, 'replyImage'])->middleware('throttle:60,1')->name('reply-image');
    Route::post('/{conversation}/reply-file', [ChatbotAdminController::class, 'replyFile'])->middleware('throttle:60,1')->name('reply-file');
    Route::post('/{conversation}/back-to-bot', [ChatbotAdminController::class, 'backToBot'])->name('back-to-bot');
    Route::post('/{conversation}/close', [ChatbotAdminController::class, 'close'])->name('close');
    Route::delete('/{conversation}', [ChatbotAdminController::class, 'destroy'])->name('destroy');
    Route::post('/settings', [ChatbotAdminController::class, 'settings'])->name('settings');
    Route::post('/settings/avatar', [ChatbotAdminController::class, 'updateAvatar'])->name('settings.avatar');
    Route::post('/settings/quick-questions', [ChatbotAdminController::class, 'updateQuickQuestions'])->name('settings.quick-questions');
});
