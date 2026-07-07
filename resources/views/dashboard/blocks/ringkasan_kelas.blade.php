<div class="card overflow-hidden group card-kelas h-full flex flex-col justify-between min-h-[140px] shadow-sm">
    <div class="p-4 pb-0">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-2xl font-extrabold text-title">{{ number_format($totalKelas) }}</p>
                <p class="text-sm font-medium text-sub">Kelas</p>
            </div>
        </div>
    </div>
    {{-- Ilustrasi: Gedung Sekolah --}}
    <svg viewBox="0 0 200 86" class="ill w-full mt-auto" style="height:84px" preserveAspectRatio="xMidYMid meet">
        <defs>
            <linearGradient id="wall" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="color-mix(in srgb, var(--ca) 42%, white)"/>
                <stop offset="1" stop-color="var(--ca)"/>
            </linearGradient>
            <linearGradient id="roof" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="var(--ca)"/>
                <stop offset="1" stop-color="color-mix(in srgb, var(--ca) 58%, black)"/>
            </linearGradient>
            <radialGradient id="win" cx=".5" cy=".25" r=".9">
                <stop offset="0" stop-color="#ffffff"/>
                <stop offset="1" stop-color="color-mix(in srgb, var(--cp) 28%, white)"/>
            </radialGradient>
        </defs>
        <g fill="#ffffff" opacity=".55">
            <ellipse class="ill-tw2" cx="42" cy="24" rx="13" ry="5"/>
            <ellipse class="ill-tw1" cx="160" cy="18" rx="10" ry="4"/>
        </g>
        <ellipse cx="100" cy="70" rx="44" ry="5" fill="#0f172a" opacity=".08"/>
        <g class="ill-bob"><g transform="translate(100,4)">
            <rect x="-37" y="34" width="74" height="34" rx="3" fill="url(#wall)"/>
            <path d="M-45,35 L0,14 L45,35 Z" fill="url(#roof)"/>
            <path d="M-45,35 L0,14 L45,35" fill="none" stroke="#fff" stroke-width="1" opacity=".25"/>
            <line x1="0" y1="14" x2="0" y2="4" stroke="color-mix(in srgb, var(--ca) 58%, black)" stroke-width="1.8"/>
            <path class="ill-flag" d="M0,4 L12,7.5 L0,11 Z" fill="var(--cp)"/>
            <path d="M-7,68 L-7,55 A7,7 0 0,1 7,55 L7,68 Z" fill="#ffffff" opacity=".92"/>
            <circle cx="4" cy="60" r="1.1" fill="var(--ca)"/>
            <rect class="ill-w1" x="-30" y="40" width="12" height="12" rx="2" fill="url(#win)"/>
            <rect class="ill-w2" x="18"  y="40" width="12" height="12" rx="2" fill="url(#win)"/>
            <g stroke="var(--ca)" stroke-width="1" opacity=".45">
                <line x1="-24" y1="40" x2="-24" y2="52"/><line x1="-30" y1="46" x2="-18" y2="46"/>
                <line x1="24" y1="40" x2="24" y2="52"/><line x1="18" y1="46" x2="30" y2="46"/>
            </g>
        </g></g>
    </svg>
</div>
