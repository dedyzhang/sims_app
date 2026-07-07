<div class="card overflow-hidden group card-guru h-full flex flex-col justify-between min-h-[140px] shadow-sm">
    <div class="p-4 pb-0">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-2xl font-extrabold text-title">{{ number_format($totalGuru) }}</p>
                <p class="text-sm font-medium text-sub">Guru</p>
            </div>
        </div>
    </div>
    {{-- Ilustrasi: Buku & Bohlam Ide --}}
    <svg viewBox="0 0 200 86" class="ill w-full mt-auto" style="height:84px" preserveAspectRatio="xMidYMid meet">
        <defs>
            <linearGradient id="pageL" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="color-mix(in srgb, var(--cps) 42%, white)"/>
                <stop offset="1" stop-color="var(--cps)"/>
            </linearGradient>
            <linearGradient id="pageR" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="var(--cps)"/>
                <stop offset="1" stop-color="color-mix(in srgb, var(--cps) 62%, black)"/>
            </linearGradient>
            <linearGradient id="bulb" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="color-mix(in srgb, var(--ca) 45%, white)"/>
                <stop offset="1" stop-color="var(--ca)"/>
            </linearGradient>
            <radialGradient id="bulbGlow" cx=".5" cy=".5" r=".5">
                <stop offset="0" stop-color="var(--ca)" stop-opacity=".6"/>
                <stop offset="1" stop-color="var(--ca)" stop-opacity="0"/>
            </radialGradient>
        </defs>
        <circle class="ill-glow" cx="100" cy="26" r="24" fill="url(#bulbGlow)"/>
        <ellipse cx="100" cy="68" rx="44" ry="5" fill="#0f172a" opacity=".08"/>
        <g transform="translate(100,50)">
            <path d="M0,2 C-12,-5 -33,-5 -43,1 L-43,19 C-33,14 -12,14 0,20 Z" fill="url(#pageL)"/>
            <path d="M0,2 C12,-5 33,-5 43,1 L43,19 C33,14 12,14 0,20 Z" fill="url(#pageR)"/>
            <g stroke="#fff" stroke-width="1" opacity=".4" stroke-linecap="round" fill="none">
                <path d="M-9,5 C-19,2 -29,2 -35,5"/><path d="M-9,10 C-19,7 -29,7 -35,10"/>
            </g>
            <line x1="0" y1="2" x2="0" y2="20" stroke="#fff" stroke-width="1.6" opacity=".6"/>
        </g>
        <g class="ill-bob"><g transform="translate(100,24)">
            <circle r="10" fill="url(#bulb)"/>
            <path d="M-5.5,-2.5 a5.5,5.5 0 0,1 11,0" fill="none" stroke="#fff" stroke-width="1.5" opacity=".55"/>
            <rect x="-4.8" y="8.5" width="9.6" height="4" rx="1.6" fill="color-mix(in srgb, var(--cps) 68%, black)"/>
            <rect x="-3.6" y="12" width="7.2" height="2.6" rx="1.3" fill="color-mix(in srgb, var(--cps) 56%, black)"/>
            <g class="ill-tw1" stroke="var(--ca)" stroke-width="1.9" stroke-linecap="round">
                <line x1="0" y1="-15" x2="0" y2="-19.5"/>
                <line x1="-13" y1="-7.5" x2="-16.5" y2="-10"/>
                <line x1="13" y1="-7.5" x2="16.5" y2="-10"/>
                <line x1="-10" y1="3" x2="-13" y2="4.5"/>
                <line x1="10" y1="3" x2="13" y2="4.5"/>
            </g>
        </g></g>
    </svg>
</div>
