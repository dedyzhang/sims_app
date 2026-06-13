{{-- Animasi tutorial 3 posisi wajah saat pengambilan sampel (loop, ikut tema) --}}
<div class="ft-wrap">
    <div class="ft-stage">
        <svg viewBox="0 0 200 170" class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
            {{-- bingkai panduan --}}
            <ellipse cx="100" cy="85" rx="58" ry="70" fill="none" stroke="var(--cp)" stroke-width="2.5" stroke-dasharray="7 8" opacity=".55"/>

            {{-- ===== Pose 1: Hadap depan ===== --}}
            <g class="ft-pose ft-p1">
                <path d="M62 60 Q100 24 138 60 L138 56 Q100 14 62 56 Z" fill="#6b4f3a"/>
                <ellipse cx="100" cy="88" rx="40" ry="48" fill="#ffd9b3" stroke="#e6a276" stroke-width="2"/>
                <ellipse cx="84" cy="82" rx="4.5" ry="6" fill="#3f3a34"/>
                <ellipse cx="116" cy="82" rx="4.5" ry="6" fill="#3f3a34"/>
                <path d="M78 71 q6 -4 12 0" stroke="#6b4f3a" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                <path d="M110 71 q6 -4 12 0" stroke="#6b4f3a" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                <path d="M100 90 l-4 14 h8" stroke="#e6a276" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M86 118 q14 12 28 0" stroke="#d97f5a" stroke-width="3" fill="none" stroke-linecap="round"/>
            </g>

            {{-- ===== Pose 2: Toleh ke kiri ===== --}}
            <g class="ft-pose ft-p2">
                <ellipse cx="138" cy="90" rx="7" ry="10" fill="#ffd0a6" stroke="#e6a276" stroke-width="2"/>
                <path d="M58 58 Q92 24 128 56 L130 54 Q94 16 58 54 Z" fill="#6b4f3a"/>
                <ellipse cx="92" cy="88" rx="38" ry="48" fill="#ffd9b3" stroke="#e6a276" stroke-width="2"/>
                <ellipse cx="76" cy="84" rx="4.5" ry="6" fill="#3f3a34"/>
                <ellipse cx="104" cy="84" rx="4" ry="5.5" fill="#3f3a34"/>
                <path d="M70 73 q6 -4 12 0" stroke="#6b4f3a" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                <path d="M99 73 q5 -3.5 10 0" stroke="#6b4f3a" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                <path d="M84 92 l-6 14 h7" stroke="#e6a276" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M76 118 q12 11 26 1" stroke="#d97f5a" stroke-width="3" fill="none" stroke-linecap="round"/>
                {{-- panah --}}
                <path d="M44 86 l-14 0 m0 0 l6 -6 m-6 6 l6 6" stroke="var(--cp)" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" class="ft-arrowL"/>
            </g>

            {{-- ===== Pose 3: Toleh ke kanan ===== --}}
            <g class="ft-pose ft-p3">
                <ellipse cx="62" cy="90" rx="7" ry="10" fill="#ffd0a6" stroke="#e6a276" stroke-width="2"/>
                <path d="M72 56 Q108 24 142 58 L142 54 Q106 16 70 54 Z" fill="#6b4f3a"/>
                <ellipse cx="108" cy="88" rx="38" ry="48" fill="#ffd9b3" stroke="#e6a276" stroke-width="2"/>
                <ellipse cx="124" cy="84" rx="4.5" ry="6" fill="#3f3a34"/>
                <ellipse cx="96" cy="84" rx="4" ry="5.5" fill="#3f3a34"/>
                <path d="M118 73 q6 -4 12 0" stroke="#6b4f3a" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                <path d="M91 73 q5 -3.5 10 0" stroke="#6b4f3a" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                <path d="M116 92 l6 14 h-7" stroke="#e6a276" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M98 118 q12 11 26 1" stroke="#d97f5a" stroke-width="3" fill="none" stroke-linecap="round"/>
                {{-- panah --}}
                <path d="M156 86 l14 0 m0 0 l-6 -6 m6 6 l-6 6" stroke="var(--cp)" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" class="ft-arrowR"/>
            </g>
        </svg>
    </div>

    <div class="ft-caps">
        <p class="ft-cap ft-c1"><b>1.</b> Hadap lurus ke kamera</p>
        <p class="ft-cap ft-c2"><b>2.</b> Toleh sedikit ke kiri</p>
        <p class="ft-cap ft-c3"><b>3.</b> Toleh sedikit ke kanan</p>
    </div>
    <div class="ft-dots">
        <span class="ft-d ft-dot1"></span><span class="ft-d ft-dot2"></span><span class="ft-d ft-dot3"></span>
    </div>
</div>

<style>
    .ft-wrap { text-align:center; }
    .ft-stage { width:150px; height:128px; margin:0 auto; position:relative; }
    .ft-pose { opacity:0; transform-origin:100px 90px; }
    .ft-p1 { animation: ftShow1 6s infinite ease-in-out; }
    .ft-p2 { animation: ftShow2 6s infinite ease-in-out; }
    .ft-p3 { animation: ftShow3 6s infinite ease-in-out; }
    @keyframes ftShow1 { 0%,28%{opacity:1} 34%,96%{opacity:0} 100%{opacity:1} }
    @keyframes ftShow2 { 0%,30%{opacity:0} 36%,61%{opacity:1} 67%,100%{opacity:0} }
    @keyframes ftShow3 { 0%,63%{opacity:0} 69%,95%{opacity:1} 100%{opacity:0} }

    .ft-caps { position:relative; height:22px; margin-top:6px; }
    .ft-cap { position:absolute; inset:0; margin:0; font-size:.85rem; font-weight:600; color:#6b6157; opacity:0; }
    .dark .ft-cap { color:#cbd5e1; }
    .ft-cap b { color:var(--cp); }
    .ft-c1 { animation: ftShow1 6s infinite ease-in-out; }
    .ft-c2 { animation: ftShow2 6s infinite ease-in-out; }
    .ft-c3 { animation: ftShow3 6s infinite ease-in-out; }

    .ft-dots { display:flex; gap:7px; justify-content:center; margin-top:9px; }
    .ft-d { width:8px; height:8px; border-radius:9999px; background:var(--cp); opacity:.25; }
    .ft-dot1 { animation: ftDot1 6s infinite ease-in-out; }
    .ft-dot2 { animation: ftDot2 6s infinite ease-in-out; }
    .ft-dot3 { animation: ftDot3 6s infinite ease-in-out; }
    @keyframes ftDot1 { 0%,28%{opacity:1;transform:scale(1.25)} 34%,96%{opacity:.25;transform:scale(1)} 100%{opacity:1;transform:scale(1.25)} }
    @keyframes ftDot2 { 0%,30%{opacity:.25;transform:scale(1)} 36%,61%{opacity:1;transform:scale(1.25)} 67%,100%{opacity:.25;transform:scale(1)} }
    @keyframes ftDot3 { 0%,63%{opacity:.25;transform:scale(1)} 69%,95%{opacity:1;transform:scale(1.25)} 100%{opacity:.25;transform:scale(1)} }
</style>
