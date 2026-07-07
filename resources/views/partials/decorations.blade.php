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
    @php
        $cp = strtolower($pref->primary_color ?? '');
    @endphp
    @if($cp === '#0f5132')
        {{-- Zamrud Pro (Emerald Diamonds) --}}
        <svg width="450" height="450" style="position:absolute;top:-150px;right:-100px;opacity:.06;transform:rotate(15deg);" fill="none" stroke="var(--cp)" stroke-width="4"><polygon points="225,20 430,225 225,430 20,225"/></svg>
        <svg width="300" height="300" style="position:absolute;bottom:-100px;right:100px;opacity:.05;transform:rotate(-10deg);" fill="none" stroke="var(--cps)" stroke-width="3"><polygon points="150,15 285,150 150,285 15,150"/></svg>
    @elseif($cp === '#3d2314')
        {{-- Kopi Karamel (Coffee Rings) --}}
        <svg width="500" height="500" style="position:absolute;top:-150px;right:-120px;opacity:.06;" fill="none" stroke="var(--cp)" stroke-width="3"><circle cx="250" cy="250" r="220" stroke-dasharray="8 8"/><circle cx="250" cy="250" r="170"/><circle cx="250" cy="250" r="120" stroke-dasharray="4 4"/></svg>
        <svg width="320" height="320" style="position:absolute;bottom:-80px;right:80px;opacity:.05;" fill="none" stroke="var(--cps)" stroke-width="2.5"><circle cx="160" cy="160" r="140"/><circle cx="160" cy="160" r="90" stroke-dasharray="6 6"/></svg>
    @elseif($cp === '#212529')
        {{-- Arang Pro (Charcoal Hexagons) --}}
        <svg width="480" height="480" style="position:absolute;top:-120px;right:-80px;opacity:.05;transform:rotate(30deg);" fill="none" stroke="var(--cp)" stroke-width="3"><polygon points="240,20 430,130 430,350 240,460 50,350 50,130"/></svg>
        <svg width="320" height="320" style="position:absolute;bottom:-100px;right:120px;opacity:.04;transform:rotate(-15deg);" fill="none" stroke="var(--cps)" stroke-width="2"><polygon points="160,15 285,90 285,230 160,305 35,230 35,90"/></svg>
    @elseif($cp === '#2563eb')
        {{-- Maitreyawira (Clean - No background shapes) --}}
    @else
        {{-- Minimalis Default --}}
        <svg width="380" height="380" style="position:absolute;top:-120px;right:-100px;opacity:.07"><circle cx="190" cy="190" r="190" fill="var(--cp)"/></svg>
        <svg width="260" height="260" style="position:absolute;bottom:-90px;right:120px;opacity:.06"><circle cx="130" cy="130" r="130" fill="var(--ca)"/></svg>
        <svg width="160" height="160" style="position:absolute;top:46%;right:60px;opacity:.05"><rect width="160" height="160" rx="48" fill="var(--cps)"/></svg>
    @endif
</div>

{{-- ===================== WARNA-WARNI ===================== --}}
<div class="motif-set" data-motif="rainbow">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;opacity:.82">
        <path d="M-120 160 C210 30 430 220 730 112 C980 22 1160 30 1540 124" fill="none" stroke="#4285f4" stroke-width="64" stroke-linecap="round" opacity=".14"/>
        <path d="M-90 265 C190 160 440 340 705 225 C980 105 1195 180 1530 250" fill="none" stroke="#34a853" stroke-width="54" stroke-linecap="round" opacity=".12"/>
        <path d="M900 -80 L1530 210 L1500 350 L820 70 Z" fill="#fbbc05" opacity=".12"/>
        <path d="M-80 700 L460 540 L620 760 L70 930 Z" fill="#ea4335" opacity=".10"/>
        <path d="M1080 520 L1440 430 L1510 760 L1160 820 Z" fill="var(--cp)" opacity=".08"/>
        <g fill="none" stroke-linecap="round" stroke-width="4" opacity=".16">
            <path d="M1090 150 L1130 190 M1130 150 L1090 190" stroke="#ea4335"/>
            <path d="M260 390 L300 430 M300 390 L260 430" stroke="#4285f4"/>
            <path d="M1180 610 L1220 650 M1220 610 L1180 650" stroke="#34a853"/>
        </g>
        <g fill="var(--ca)" opacity=".16">
            <rect x="185" y="140" width="42" height="42" rx="10" transform="rotate(18 206 161)"/>
            <rect x="1015" y="310" width="50" height="50" rx="12" transform="rotate(-16 1040 335)"/>
            <rect x="650" y="640" width="38" height="38" rx="10" transform="rotate(28 669 659)"/>
        </g>
    </svg>
</div>
{{-- ===================== NIGHT OCEAN (Samudera Malam) ===================== --}}
<div class="motif-set" data-motif="nightocean">
    {{-- compass rose top-right --}}
    <svg width="180" height="180" style="position:absolute;top:28px;right:60px;opacity:.12">
        <g transform="translate(90,90)">
            <circle r="72" fill="none" stroke="var(--cp)" stroke-width="2.5" opacity=".4"/>
            <circle r="56" fill="none" stroke="var(--cps)" stroke-width="1.5" opacity=".3"/>
            @foreach([0,90,180,270] as $a)
            <polygon points="0,-66 6,-24 -6,-24" fill="var(--cp)" transform="rotate({{ $a }})"/>
            @endforeach
            @foreach([45,135,225,315] as $a)
            <polygon points="0,-48 4,-22 -4,-22" fill="var(--cps)" opacity=".6" transform="rotate({{ $a }})"/>
            @endforeach
            <circle r="8" fill="var(--ca)"/>
            <circle r="3" fill="#fff"/>
        </g>
    </svg>
    {{-- deep waves bottom --}}
    <svg viewBox="0 0 1440 260" preserveAspectRatio="none" style="position:absolute;bottom:0;left:0;width:100%;height:220px;opacity:.10">
        <path d="M0,120 C180,60 360,180 540,120 C720,60 900,180 1080,120 C1200,80 1320,160 1440,130 L1440,260 L0,260 Z" fill="var(--cp)"/>
        <path d="M0,160 C200,120 400,200 600,160 C800,120 1000,200 1200,160 C1320,130 1440,180 1440,170 L1440,260 L0,260 Z" fill="var(--cps)" opacity=".6"/>
        <path d="M0,200 C240,180 480,220 720,200 C960,180 1200,220 1440,210 L1440,260 L0,260 Z" fill="var(--cp)" opacity=".35"/>
    </svg>
    {{-- lighthouse mid-right --}}
    <svg width="80" height="160" style="position:absolute;top:38%;right:40px;opacity:.11">
        <g transform="translate(40,140)">
            {{-- light beam --}}
            <polygon points="0,-128 -60,-80 60,-80" fill="var(--ca)" opacity=".25"/>
            {{-- tower --}}
            <rect x="-12" y="-120" width="24" height="100" rx="4" fill="var(--cp)"/>
            {{-- stripes --}}
            <rect x="-12" y="-90" width="24" height="12" fill="var(--ca)" opacity=".5"/>
            <rect x="-12" y="-60" width="24" height="12" fill="var(--ca)" opacity=".5"/>
            {{-- lantern --}}
            <rect x="-16" y="-132" width="32" height="16" rx="4" fill="var(--cps)"/>
            <circle cx="0" cy="-124" r="5" fill="var(--ca)"/>
            {{-- base --}}
            <rect x="-20" y="-22" width="40" height="8" rx="3" fill="var(--cp)"/>
        </g>
    </svg>
    {{-- anchor bottom-right --}}
    <svg width="120" height="140" style="position:absolute;bottom:30px;right:32%;opacity:.10">
        <g transform="translate(60,70)" fill="none" stroke="var(--cps)" stroke-width="5" stroke-linecap="round">
            {{-- ring --}}
            <circle cx="0" cy="-48" r="12"/>
            {{-- shank --}}
            <line x1="0" y1="-36" x2="0" y2="40"/>
            {{-- arms --}}
            <path d="M-36,40 C-36,14 0,14 0,40 C0,14 36,14 36,40"/>
            {{-- crossbar --}}
            <line x1="-18" y1="-20" x2="18" y2="-20"/>
        </g>
    </svg>
    {{-- small bubbles scattered --}}
    <svg width="100%" height="100%" preserveAspectRatio="none" style="position:absolute;inset:0;opacity:.08">
        <g fill="var(--cps)">
            @foreach([[82,22],[68,45],[55,18],[92,55],[76,70],[62,38],[88,80],[50,65]] as [$x,$y])
            <circle cx="{{ $x }}%" cy="{{ $y }}%" r="{{ $loop->index % 3 == 0 ? 4 : 2.5 }}"/>
            @endforeach
        </g>
    </svg>
</div>

