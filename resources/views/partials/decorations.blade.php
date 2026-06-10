{{-- ============================================================
     Semua set dekorasi motif. Hanya yang aktif yang ditampilkan
     (lihat JS setMotif di layout). Warna mengikuti CSS var tema.
     ============================================================ --}}

{{-- ===================== BOTANICAL ===================== --}}
<div class="motif-set" data-motif="botanical">
    <div style="position:absolute;top:-60px;right:-50px;">@include('partials.flower', ['s'=>240,'c1'=>'#8fae8f','c2'=>'#e8a06a','o'=>'.16'])</div>
    <div style="position:absolute;bottom:-70px;right:90px;">@include('partials.flower', ['s'=>180,'c1'=>'#7ba088','c2'=>'#e5996c','o'=>'.13'])</div>
    <div class="hidden md:block" style="position:absolute;top:32%;right:-60px;">@include('partials.leaf', ['s'=>140,'c'=>'#8fae8f','o'=>'.12'])</div>
    <div class="hidden lg:block" style="position:absolute;bottom:80px;right:38%;">@include('partials.leaf', ['s'=>100,'c'=>'#e8a06a','o'=>'.1'])</div>
    <div class="hidden lg:block" style="position:absolute;top:55%;right:30%;">@include('partials.flower', ['s'=>120,'c1'=>'#e8a06a','c2'=>'#7ba088','o'=>'.09'])</div>
</div>

{{-- ===================== OCEAN ===================== --}}
<div class="motif-set" data-motif="ocean">
    {{-- waves bottom --}}
    <svg viewBox="0 0 1440 220" preserveAspectRatio="none" style="position:absolute;bottom:0;left:0;width:100%;height:200px;opacity:.13">
        <path d="M0,90 C240,150 480,40 720,90 C960,140 1200,40 1440,100 L1440,220 L0,220 Z" fill="var(--cp)"/>
        <path d="M0,130 C260,180 500,100 720,130 C980,165 1200,100 1440,140 L1440,220 L0,220 Z" fill="var(--cps)" opacity=".7"/>
        <path d="M0,170 C260,200 520,150 760,175 C1000,200 1220,150 1440,180 L1440,220 L0,220 Z" fill="var(--cp)" opacity=".5"/>
    </svg>
    {{-- bubbles --}}
    <svg width="160" height="160" style="position:absolute;top:40px;right:60px;opacity:.12"><g fill="var(--cp)"><circle cx="120" cy="30" r="18"/><circle cx="60" cy="70" r="10"/><circle cx="110" cy="100" r="7"/><circle cx="30" cy="40" r="6"/></g></svg>
    {{-- fish --}}
    <svg width="120" height="70" style="position:absolute;top:42%;right:-10px;opacity:.12">
        <g fill="var(--ca)"><path d="M15,35 C45,8 85,8 100,35 C85,62 45,62 15,35 Z"/><path d="M100,35 L120,18 L115,35 L120,52 Z"/><circle cx="40" cy="30" r="3.5" fill="#fff"/></g>
    </svg>
    <svg width="80" height="50" style="position:absolute;top:68%;right:32%;opacity:.1">
        <g fill="var(--cps)"><path d="M10,25 C30,5 58,5 70,25 C58,45 30,45 10,25 Z"/><path d="M70,25 L82,13 L79,25 L82,37 Z"/></g>
    </svg>
</div>

{{-- ===================== FOREST ===================== --}}
<div class="motif-set" data-motif="forest">
    {{-- sun --}}
    <svg width="140" height="140" style="position:absolute;top:30px;right:80px;opacity:.13"><circle cx="70" cy="70" r="42" fill="var(--ca)"/></svg>
    {{-- pine trees bottom-right --}}
    <svg viewBox="0 0 600 260" preserveAspectRatio="xMaxYMax meet" style="position:absolute;bottom:0;right:0;width:560px;max-width:80%;height:240px;opacity:.14">
        @php $pines=[[80,150,0.9],[200,180,1.1],[330,150,0.85],[440,200,1.25],[540,160,0.95]]; @endphp
        @foreach($pines as [$x,$h,$sc])
        <g transform="translate({{ $x }},{{ 260-$h }}) scale({{ $sc }})" fill="var(--cp)">
            <polygon points="0,0 26,46 -26,46"/>
            <polygon points="0,28 30,80 -30,80"/>
            <polygon points="0,58 34,118 -34,118"/>
            <rect x="-6" y="116" width="12" height="22" fill="var(--ca)" opacity=".8"/>
        </g>
        @endforeach
    </svg>
    {{-- falling leaves --}}
    <div class="hidden md:block" style="position:absolute;top:38%;right:50px;">@include('partials.leaf', ['s'=>70,'c'=>'var(--cps)','o'=>'.13'])</div>
    <div class="hidden lg:block" style="position:absolute;top:24%;right:34%;">@include('partials.leaf', ['s'=>54,'c'=>'var(--ca)','o'=>'.1'])</div>
</div>

{{-- ===================== SUNSET ===================== --}}
<div class="motif-set" data-motif="sunset">
    {{-- big sun with rays --}}
    <svg width="220" height="220" style="position:absolute;top:-30px;right:-20px;opacity:.16">
        <g transform="translate(150,80)">
            <circle r="50" fill="var(--ca)"/>
            @foreach(range(0,330,30) as $a)<line x1="0" y1="0" x2="0" y2="-78" stroke="var(--ca)" stroke-width="5" stroke-linecap="round" transform="rotate({{ $a }})"/>@endforeach
        </g>
    </svg>
    {{-- mountains --}}
    <svg viewBox="0 0 1440 240" preserveAspectRatio="none" style="position:absolute;bottom:0;left:0;width:100%;height:200px;opacity:.15">
        <polygon points="0,240 360,80 620,240" fill="var(--cps)"/>
        <polygon points="380,240 760,40 1140,240" fill="var(--cp)"/>
        <polygon points="900,240 1200,110 1440,240 1440,240" fill="var(--cps)" opacity=".8"/>
    </svg>
    {{-- birds --}}
    <svg width="160" height="60" style="position:absolute;top:28%;right:18%;opacity:.18">
        <g stroke="var(--cp)" stroke-width="3" fill="none" stroke-linecap="round">
            <path d="M10,30 Q22,18 34,30 Q46,18 58,30"/><path d="M80,18 Q90,8 100,18 Q110,8 120,18"/>
        </g>
    </svg>
</div>

{{-- ===================== ROBOT / TECH ===================== --}}
<div class="motif-set" data-motif="robot">
    {{-- circuit top-right --}}
    <svg width="320" height="240" style="position:absolute;top:20px;right:20px;opacity:.13">
        <g stroke="var(--cp)" stroke-width="2.5" fill="none">
            <path d="M20,40 H120 V120 H220"/><path d="M220,40 V90 H300"/><path d="M60,40 V160 H160 V220"/>
            <path d="M260,150 H180 V200"/>
        </g>
        <g fill="var(--ca)"><circle cx="20" cy="40" r="5"/><circle cx="120" cy="120" r="5"/><circle cx="220" cy="40" r="5"/><circle cx="300" cy="90" r="5"/><circle cx="160" cy="220" r="5"/><circle cx="180" cy="200" r="5"/><circle cx="60" cy="40" r="5"/></g>
    </svg>
    {{-- gear --}}
    <svg width="120" height="120" style="position:absolute;top:42%;right:40px;opacity:.1">
        <g fill="var(--cps)" transform="translate(60,60)">
            @foreach(range(0,315,45) as $a)<rect x="-7" y="-58" width="14" height="20" rx="3" transform="rotate({{ $a }})"/>@endforeach
            <circle r="38"/><circle r="16" fill="#fff"/>
        </g>
    </svg>
    {{-- robot head bottom-right --}}
    <svg width="200" height="200" style="position:absolute;bottom:10px;right:60px;opacity:.13">
        <g transform="translate(100,110)" fill="var(--cp)">
            <line x1="0" y1="-86" x2="0" y2="-64" stroke="var(--cp)" stroke-width="4"/><circle cx="0" cy="-90" r="7"/>
            <rect x="-55" y="-62" width="110" height="92" rx="20"/>
            <rect x="-38" y="-44" width="76" height="40" rx="10" fill="#fff"/>
            <circle cx="-18" cy="-24" r="9" fill="var(--ca)"/><circle cx="18" cy="-24" r="9" fill="var(--ca)"/>
            <rect x="-22" y="8" width="44" height="8" rx="4" fill="#fff"/>
        </g>
    </svg>
</div>

{{-- ===================== SPACE ===================== --}}
<div class="motif-set" data-motif="space">
    {{-- stars --}}
    <svg width="100%" height="100%" preserveAspectRatio="none" style="position:absolute;inset:0;opacity:.5">
        <g fill="var(--cps)">
            @foreach([[88,12],[70,30],[55,8],[40,22],[92,40],[78,55],[64,18],[50,48],[84,68],[72,80],[58,72],[46,62],[90,88]] as [$x,$y])
            <circle cx="{{ $x }}%" cy="{{ $y }}%" r="{{ $loop->index % 3 == 0 ? 2.5 : 1.6 }}" opacity=".5"/>
            @endforeach
        </g>
    </svg>
    {{-- crescent moon --}}
    <svg width="130" height="130" style="position:absolute;top:36px;right:70px;opacity:.16">
        <defs><mask id="mcr"><rect width="130" height="130" fill="#fff"/><circle cx="82" cy="58" r="48" fill="#000"/></mask></defs>
        <circle cx="62" cy="65" r="50" fill="var(--ca)" mask="url(#mcr)"/>
    </svg>
    {{-- planet with ring --}}
    <svg width="160" height="120" style="position:absolute;top:44%;right:30px;opacity:.14">
        <g transform="translate(80,60)">
            <ellipse rx="56" ry="16" fill="none" stroke="var(--cps)" stroke-width="5" transform="rotate(-20)"/>
            <circle r="30" fill="var(--cp)"/>
        </g>
    </svg>
    {{-- rocket --}}
    <svg width="90" height="140" style="position:absolute;bottom:40px;right:34%;opacity:.14">
        <g transform="translate(45,60)" fill="var(--cp)">
            <path d="M0,-50 C18,-30 18,10 0,30 C-18,10 -18,-30 0,-50 Z"/>
            <circle cx="0" cy="-18" r="8" fill="#fff"/>
            <path d="M-14,18 L-26,40 L-8,28 Z" fill="var(--ca)"/><path d="M14,18 L26,40 L8,28 Z" fill="var(--ca)"/>
            <path d="M-6,30 L0,52 L6,30 Z" fill="var(--ca)" opacity=".8"/>
        </g>
    </svg>
</div>

{{-- ===================== MINIMAL ===================== --}}
<div class="motif-set" data-motif="minimal">
    <svg width="380" height="380" style="position:absolute;top:-120px;right:-100px;opacity:.07"><circle cx="190" cy="190" r="190" fill="var(--cp)"/></svg>
    <svg width="260" height="260" style="position:absolute;bottom:-90px;right:120px;opacity:.06"><circle cx="130" cy="130" r="130" fill="var(--ca)"/></svg>
    <svg width="160" height="160" style="position:absolute;top:46%;right:60px;opacity:.05"><rect width="160" height="160" rx="48" fill="var(--cps)"/></svg>
</div>
