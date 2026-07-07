<div class="relative overflow-hidden rounded-[22px] p-5 text-white h-full flex flex-col justify-between min-h-[140px] shadow-sm" style="background:linear-gradient(150deg, var(--cp), color-mix(in srgb, var(--cp) 55%, black))">
    <i data-lucide="{{ $motifIcon }}" class="absolute -right-7 -top-7 w-36 h-36 text-white opacity-20" style="stroke-width:1.2"></i>
    <i data-lucide="{{ $motifIcon }}" class="absolute right-8 bottom-2 w-16 h-16 text-white opacity-15" style="stroke-width:1.2"></i>
    <div class="relative z-10">
        <div class="w-10 h-10 rounded-xl bg-white/25 grid place-items-center mb-3"><i data-lucide="calendar-days" class="w-5 h-5"></i></div>
        <p class="text-white/70 text-xs">Semester Aktif</p>
        <p class="text-lg md:text-2xl font-extrabold">{{ $semester ? 'Semester '.$semester->semester : '—' }}</p>
        <p class="text-white/80 text-sm">{{ $semester->tahun ?? 'Belum diatur' }}</p>
    </div>
</div>
