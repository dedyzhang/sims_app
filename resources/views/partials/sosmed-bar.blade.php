{{-- Bilah ikon media sosial sekolah. Param: $sosmed (array key => ['label','href']) --}}
@if(!empty($sosmed))
<div class="mt-8 flex flex-col items-center gap-3">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ikuti Kami</p>
    <div class="flex flex-wrap items-center justify-center gap-2.5">
        @foreach($sosmed as $key => $s)
            <a href="{{ $s['href'] }}" target="_blank" rel="noopener noreferrer"
               title="{{ $s['label'] }}" aria-label="{{ $s['label'] }}"
               class="grid place-items-center w-11 h-11 rounded-2xl bg-primary/10 text-primary transition hover:bg-primary hover:text-white hover:-translate-y-0.5 hover:shadow-lg">
                @include('partials.sosmed-icon', ['key' => $key, 'cls' => 'w-5 h-5'])
            </a>
        @endforeach
    </div>
</div>
@endif
