<div x-data="filePreviewModal()">
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
                    @foreach($mySubmission->files as $f)
@php $canPreview = $f->isImage() || $f->mime === 'application/pdf'; @endphp
@if($canPreview)
<button type="button" @click="open('{{ route('classroom.submission.file.preview', $f) }}', '{{ route('classroom.submission.file', $f) }}', '{{ addslashes($f->original_name) }}', {{ $f->isImage() ? 'true' : 'false' }})" class="text-xs inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-primary"><i data-lucide="{{ $f->isImage() ? 'image' : 'file-text' }}" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::limit($f->original_name, 26) }}</button>
@else
<a href="{{ route('classroom.submission.file', $f) }}" class="text-xs inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-primary"><i data-lucide="paperclip" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::limit($f->original_name, 26) }}</a>
@endif
@endforeach
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
                <p class="text-[11px] text-slate-400 mb-2">Tulis jawaban (opsional jika melampirkan file). Bisa menggunakan editor matematika visual <b>&sum; Rumus</b> &amp; <b>&#9654; YouTube</b>.</p>
                @include('classroom.partials.editor', ['name' => 'body', 'value' => $mySubmission->body ?? ''])
            </div>
            @if($mySubmission && $mySubmission->files->isNotEmpty())
              <div>
                  <label class="form-label text-xs">Lampiran Saat Ini</label>
                  <div class="flex flex-wrap gap-2 mb-2">
                      @foreach($mySubmission->files as $f)
                      @php $canPreview = $f->isImage() || $f->mime === 'application/pdf'; @endphp
                      <div class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-600 overflow-hidden hover:border-primary">
                          @if($canPreview)
                          <button type="button" @click="open('{{ route('classroom.submission.file.preview', $f) }}', '{{ route('classroom.submission.file', $f) }}', '{{ addslashes($f->original_name) }}', {{ $f->isImage() ? 'true' : 'false' }})" class="text-xs inline-flex items-center gap-1 px-2.5 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-700">
                              <i data-lucide="{{ $f->isImage() ? 'image' : 'file-text' }}" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::limit($f->original_name, 26) }}
                          </button>
                          @else
                          <a href="{{ route('classroom.submission.file', $f) }}" class="text-xs inline-flex items-center gap-1 px-2.5 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-700">
                              <i data-lucide="paperclip" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::limit($f->original_name, 26) }}
                          </a>
                          @endif
                          <button type="button" onclick="confirmAction(document.getElementById('form-delete-{{ $f->uuid }}'), 'Hapus lampiran ini?', 'red')" class="text-rose-500 hover:bg-rose-50 px-2 py-1.5 border-l border-slate-200 dark:border-slate-600 h-full flex items-center justify-center" title="Hapus"><i data-lucide="x" class="w-3 h-3"></i></button>
                      </div>
                      @endforeach
                  </div>
              </div>
            @endif
            @include('classroom.partials.upload', ['label' => 'Tambah Lampiran (gambar/PDF)'])
            <div class="flex justify-end gap-2 pt-2">
                <button type="submit" name="submit_action" value="draft" class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Simpan Draf</button>
                <button type="submit" name="submit_action" value="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90 shadow" style="background:var(--cp)">Kumpulkan Tugas</button>
            </div>
        </form>
        @if($mySubmission && $mySubmission->files->isNotEmpty())
            @foreach($mySubmission->files as $f)
                <form id="form-delete-{{ $f->uuid }}" action="{{ route('classroom.submission.file.delete', $f) }}" method="POST" style="display:none;">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endif
    @endif
</div>

@include('classroom.partials.file_preview_modal')


</div>









@push('scripts')
<script>
document.addEventListener('submit', async function(e) {
    const form = e.target;
    if (form.getAttribute('action') !== '{{ route('classroom.submission.store', $assignment) }}') return;

    const fileInput = form.querySelector('input[type="file"][name="files[]"]');
    if (!fileInput || fileInput.files.length <= 1) return;

    e.preventDefault();
    const btn = e.submitter;
    const actionVal = btn ? btn.value : 'draft';
    const bodyInput = form.querySelector('[name="body"]');
    const csrf = form.querySelector('input[name="_token"]').value;

    const files = Array.from(fileInput.files);

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center transition-opacity';
    overlay.innerHTML = `
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-2xl flex flex-col items-center max-w-xs w-full mx-4 text-center">
            <svg class="animate-spin h-10 w-10 text-primary mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-1">Mengunggah Tugas...</h3>
            <p id="upload-progress-text" class="text-sm text-slate-500 font-medium">File 1 dari ${files.length}</p>
            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 mt-4 overflow-hidden">
                <div id="upload-progress-bar" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const updateProgress = (i) => {
        document.getElementById('upload-progress-text').innerText = 'File ' + i + ' dari ' + files.length;
        document.getElementById('upload-progress-bar').style.width = ((i - 1) / files.length * 100) + '%';
    };

    // Upload files 0 to N-2
    for (let i = 0; i < files.length - 1; i++) {
        updateProgress(i + 1);
        
        const fd = new FormData();
        fd.append('_token', csrf);
        fd.append('submit_action', 'draft'); 
        if (bodyInput && i === 0) fd.append('body', bodyInput.value);
        fd.append('files[]', files[i]);

        try {
            const resp = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) {
                alert('Gagal mengunggah file ke-' + (i+1) + '. Status: ' + resp.status);
                overlay.remove();
                return;
            }
        } catch (err) {
            alert('Terjadi kesalahan jaringan saat mengunggah file ke-' + (i+1));
            overlay.remove();
            return;
        }
    }

    updateProgress(files.length);
    document.getElementById('upload-progress-bar').style.width = '90%';
    document.getElementById('upload-progress-text').innerText = 'Menyelesaikan pengumpulan...';
    
    try {
        const dt = new DataTransfer();
        dt.items.add(files[files.length - 1]);
        fileInput.files = dt.files;
    } catch(e) {
        const fd = new FormData();
        fd.append('_token', csrf);
        fd.append('submit_action', actionVal);
        if (bodyInput && files.length === 1) fd.append('body', bodyInput.value);
        fd.append('files[]', files[files.length - 1]);
        await fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }});
        window.location.reload();
        return;
    }

    let hiddenAction = form.querySelector('input[name="submit_action"][type="hidden"]');
    if (!hiddenAction) {
        hiddenAction = document.createElement('input');
        hiddenAction.type = 'hidden';
        hiddenAction.name = 'submit_action';
        form.appendChild(hiddenAction);
    }
    hiddenAction.value = actionVal;

    form.submit();
});
</script>
@endpush