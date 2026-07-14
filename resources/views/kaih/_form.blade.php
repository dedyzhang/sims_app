{{-- Form isi 7 KAIH. Var: $pertanyaans (Collection<KaihPertanyaan> dgn opsi), $actionUrl (opsional, default kaih.simpan), $extraFields (opsional, HTML tambahan mis. hidden input) --}}
<form method="POST" action="{{ $actionUrl ?? route('kaih.simpan') }}" class="space-y-4">
    @csrf
    {!! $extraFields ?? '' !!}
    @foreach($pertanyaans as $i => $p)
    <div class="card p-4">
        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 mb-1">{{ $i + 1 }}. {{ $p->kebiasaan }}</p>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">{{ $p->pertanyaan }}</p>
        <div class="space-y-2">
            @foreach($p->opsi as $o)
            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer hover:border-primary transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input type="radio" name="jawaban[{{ $p->uuid }}]" value="{{ $o->uuid }}" required class="w-4 h-4 text-primary focus:ring-primary flex-shrink-0">
                <span class="text-sm text-slate-700 dark:text-slate-200 flex-1">{{ $o->label }}</span>
            </label>
            @endforeach
        </div>
        @error("jawaban.{$p->uuid}")<p class="text-xs text-rose-500 mt-2">{{ $message }}</p>@enderror
    </div>
    @endforeach

    {{-- Refleksi Hari Ini — selalu ada di tiap pengisian, terpisah dari pertanyaan yang bisa dikustomisasi admin. --}}
    <div class="card p-4">
        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 mb-1 flex items-center gap-1.5">
            <i data-lucide="pencil-line" class="w-4 h-4 text-primary"></i> Refleksi Hari Ini
        </p>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">Ceritakan singkat perasaan atau hal yang ingin kamu sampaikan hari ini.</p>
        <textarea name="refleksi" rows="3" required maxlength="1000" placeholder="Tulis refleksimu di sini..." class="form-input">{{ old('refleksi') }}</textarea>
        @error('refleksi')<p class="text-xs text-rose-500 mt-2">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="btn-primary w-full py-3.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
        <i data-lucide="check-circle-2" class="w-5 h-5"></i> Simpan Jawaban 7 KAIH
    </button>
</form>
