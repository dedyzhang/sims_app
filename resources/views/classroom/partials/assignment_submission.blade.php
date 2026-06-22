{{-- Blok pengumpulan siswa (status + form). Var: $assignment, $mySubmission --}}
@php
    $warningTime = false; $timeLeftStr = '';
    if ($assignment->due_at && !$assignment->due_at->isPast()) {
        $hoursLeft = now()->diffInHours($assignment->due_at, false);
        if ($hoursLeft >= 0 && $hoursLeft <= 24) { $warningTime = true; $timeLeftStr = $assignment->due_at->locale('id')->diffForHumans(now()); }
    }
@endphp
<div class="card p-5">
    <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-3">Pengumpulan Tugas</h3>

    @if($warningTime && (!$mySubmission || in_array($mySubmission->status, ['draft', 'returned'])))
    <div class="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 px-4 py-3 text-sm flex items-start gap-2.5 mb-4 shadow-sm">
        <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 text-amber-600 dark:text-amber-500"></i>
        <div><p class="font-bold">Batas waktu hampir habis!</p><p class="text-xs mt-0.5">Waktu pengumpulan tersisa <strong>{{ $timeLeftStr }}</strong>. Segera simpan dan kumpulkan jawaban Anda.</p></div>
    </div>
    @endif

    @if($mySubmission)
        @if($mySubmission->status==='graded')
        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 mb-4 text-sm shadow-sm">
            @if($assignment->hide_scores)
                <span class="font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-1.5"><i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i> Tugas sudah dikoreksi</span>
            @else
                <span class="font-bold text-emerald-700 dark:text-emerald-300">Nilai: {{ $mySubmission->score }} / {{ $assignment->max_score }}</span>
            @endif
            @if($mySubmission->feedback)<p class="text-slate-600 dark:text-slate-300 mt-1"><b>Feedback:</b> {{ $mySubmission->feedback }}</p>@endif
        </div>
        @elseif($mySubmission->status==='submitted')
        <div class="rounded-xl bg-emerald-50/50 dark:bg-emerald-950/10 border border-emerald-100 dark:border-emerald-900 px-4 py-2.5 mb-4 text-xs text-emerald-800 dark:text-emerald-400 flex items-center gap-2 shadow-sm">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
            <span>Sudah dikumpulkan {{ $mySubmission->submitted_at?->locale('id')->diffForHumans() }} @if($mySubmission->is_late)<span class="text-rose-500 font-semibold">(terlambat)</span>@endif.</span>
        </div>
        @elseif($mySubmission->status==='returned')
        <div class="rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800 px-4 py-3 mb-4 text-sm text-rose-800 dark:text-rose-300 flex items-start gap-2.5 shadow-sm">
            <i data-lucide="info" class="w-5 h-5 flex-shrink-0 text-rose-600"></i>
            <div><p class="font-bold">Jawaban Dikembalikan</p><p class="text-xs mt-0.5">Tugas Anda dikembalikan oleh Guru agar dapat direvisi. Silakan perbarui jawaban Anda di bawah lalu kumpulkan kembali.</p></div>
        </div>
        @elseif($mySubmission->status==='draft')
        <div class="rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-4 py-3 mb-4 text-sm text-slate-700 dark:text-slate-300 flex items-start gap-2.5 shadow-sm">
            <i data-lucide="file-text" class="w-5 h-5 flex-shrink-0 text-slate-500"></i>
            <div><p class="font-bold">Draf Jawaban</p><p class="text-xs mt-0.5">Jawaban disimpan sebagai draf dan <strong>belum dikirimkan ke Guru</strong>. Jangan lupa klik tombol <strong>Kumpulkan Tugas</strong> jika sudah selesai.</p></div>
        </div>
        @endif
    @endif

    @if($mySubmission && in_array($mySubmission->status, ['submitted', 'graded']))
        <div class="space-y-4">
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Jawaban Anda:</p>
                <div class="mt-3 text-sm">
                    @if($mySubmission->body)@include('classroom.partials.richbody', ['html' => $mySubmission->body])@else<p class="text-slate-400 italic text-xs">Tidak ada jawaban teks.</p>@endif
                </div>
            </div>
            @if($mySubmission->files->isNotEmpty())
            <div>
                <p class="text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Lampiran:</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($mySubmission->files as $f)<a href="{{ route('classroom.submission.file', $f) }}" class="text-xs inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 hover:border-primary hover:text-primary transition shadow-sm"><i data-lucide="paperclip" class="w-3.5 h-3.5 text-slate-400"></i><span>{{ \Illuminate\Support\Str::limit($f->original_name, 28) }}</span></a>@endforeach
                </div>
            </div>
            @endif
            <div class="rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-4 py-3 text-xs flex items-start gap-2 shadow-inner">
                <i data-lucide="lock" class="w-4 h-4 flex-shrink-0 text-slate-400 mt-0.5"></i>
                <p>Jawaban Anda telah dikunci dan tidak dapat diubah lagi. Hubungi Guru jika Anda perlu melakukan revisi.</p>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('classroom.submission.store', $assignment) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="form-label">Jawaban Anda</label>
                <p class="text-[11px] text-slate-400 mb-2">Tulis jawaban (opsional jika melampirkan file). Bisa menggunakan editor matematika visual <b>∑ Rumus</b> &amp; <b>▶ YouTube</b>.</p>
                @include('classroom.partials.editor', ['name' => 'body', 'value' => $mySubmission->body ?? ''])
            </div>
            @if($mySubmission && $mySubmission->files->isNotEmpty())
            <div>
                <label class="form-label text-xs">Lampiran Saat Ini</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    @foreach($mySubmission->files as $f)<a href="{{ route('classroom.submission.file', $f) }}" class="text-xs inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/40"><i data-lucide="paperclip" class="w-3 h-3 text-slate-400"></i><span>{{ \Illuminate\Support\Str::limit($f->original_name, 20) }}</span></a>@endforeach
                </div>
            </div>
            @endif
            @include('classroom.partials.upload', ['label' => 'Tambah Lampiran (gambar/PDF)'])
            <div class="flex justify-end gap-2 pt-2">
                <button type="submit" name="submit_action" value="draft" class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Simpan Draf</button>
                <button type="submit" name="submit_action" value="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90 shadow" style="background:var(--cp)">Kumpulkan Tugas</button>
            </div>
        </form>
    @endif
</div>
