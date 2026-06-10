{{-- Plumeria/frangipani flower — props: s(size), c1(petal), c2(center glow), o(opacity) --}}
@php $s = $s ?? 110; $c1 = $c1 ?? '#8fae8f'; $c2 = $c2 ?? '#e8a06a'; $o = $o ?? '.85'; @endphp
<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 100 100" style="opacity:{{ $o }}" xmlns="http://www.w3.org/2000/svg">
    <g transform="translate(50,50)">
        @foreach([0,72,144,216,288] as $r)
        <g transform="rotate({{ $r }})">
            <path d="M0,-6 C16,-12 24,-30 12,-42 C6,-48 -6,-48 -12,-42 C-22,-32 -16,-14 0,-6 Z"
                  fill="{{ $c1 }}" transform="translate(0,-2)"/>
            <path d="M0,-6 C10,-12 15,-26 8,-38 C4,-42 -4,-42 -8,-38 C-14,-30 -10,-14 0,-6 Z"
                  fill="{{ $c2 }}" opacity=".35"/>
        </g>
        @endforeach
        <circle r="7" fill="{{ $c2 }}" opacity=".9"/>
        <circle r="3.5" fill="#fff6e6" opacity=".7"/>
    </g>
</svg>
