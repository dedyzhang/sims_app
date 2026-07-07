<div class="card overflow-hidden group card-siswa h-full flex flex-col justify-between min-h-[140px] shadow-sm">
    <div class="p-4 pb-0">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-2xl font-extrabold text-title">{{ number_format($totalSiswa) }}</p>
                <p class="text-sm font-medium text-sub">Siswa</p>
            </div>
        </div>
    </div>
    {{-- Ilustrasi: Topi Wisuda --}}
    <svg viewBox="0 0 200 86" class="ill w-full mt-auto" style="height:84px" preserveAspectRatio="xMidYMid meet">
        <defs>
            <linearGradient id="capBoard" x1="0" y1="0" x2=".25" y2="1">
                <stop offset="0" stop-color="color-mix(in srgb, var(--cp) 50%, white)"/>
                <stop offset="1" stop-color="var(--cp)"/>
            </linearGradient>
            <linearGradient id="capBase" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="var(--cp)"/>
                <stop offset="1" stop-color="color-mix(in srgb, var(--cp) 58%, black)"/>
            </linearGradient>
            <radialGradient id="capGlow" cx=".5" cy=".5" r=".5">
                <stop offset="0" stop-color="var(--ca)" stop-opacity=".45"/>
                <stop offset="1" stop-color="var(--ca)" stop-opacity="0"/>
            </radialGradient>
        </defs>
        <ellipse cx="100" cy="42" rx="50" ry="36" fill="url(#capGlow)"/>
        <path class="ill-tw1" d="M46,22 l1.8,4 l4,1.8 l-4,1.8 l-1.8,4 l-1.8,-4 l-4,-1.8 l4,-1.8 z" fill="var(--ca)"/>
        <circle class="ill-tw2" cx="156" cy="22" r="3" fill="var(--cps)" opacity=".7"/>
        <circle class="ill-tw1" cx="150" cy="46" r="2.2" fill="var(--ca)" opacity=".7"/>
        <ellipse cx="100" cy="70" rx="34" ry="5" fill="#0f172a" opacity=".08"/>
        <g class="ill-bob"><g transform="translate(100,42)">
            <path d="M-18,3 L18,3 L18,15 C18,23 -18,23 -18,15 Z" fill="url(#capBase)"/>
            <polygon points="0,-16 42,0 0,16 -42,0" fill="url(#capBoard)"/>
            <polygon points="0,-16 42,0 0,1 -42,0" fill="#fff" opacity=".22"/>
            <circle r="3.3" fill="#fff"/>
            <circle r="1.4" fill="var(--cp)"/>
            <g>
                <path d="M0,0 L30,3" stroke="var(--ca)" stroke-width="2.4" stroke-linecap="round" fill="none"/>
                <line x1="30" y1="3" x2="30" y2="16" stroke="var(--ca)" stroke-width="2.4" stroke-linecap="round"/>
                <g fill="var(--ca)"><circle cx="30" cy="17" r="2.6"/><path d="M26.5,18 L33.5,18 L31.5,28 L28.5,28 Z"/></g>
                <animateTransform attributeName="transform" type="rotate" values="-8 0 0;9 0 0;-8 0 0" keyTimes="0;0.5;1" dur="2.8s" repeatCount="indefinite" calcMode="spline" keySplines="0.4 0 0.6 1;0.4 0 0.6 1"/>
            </g>
        </g></g>
    </svg>
</div>
