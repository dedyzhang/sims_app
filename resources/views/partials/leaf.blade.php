{{-- Monstera-ish leaf — props: s(size), c(color), o(opacity) --}}
@php $s = $s ?? 80; $c = $c ?? '#8fae8f'; $o = $o ?? '.7'; @endphp
<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 100 100" style="opacity:{{ $o }}" xmlns="http://www.w3.org/2000/svg">
    <g transform="translate(50,50) rotate(25)">
        <path d="M0,42 C-30,30 -38,-6 -22,-34 C-12,-50 12,-50 22,-34 C38,-6 30,30 0,42 Z" fill="{{ $c }}"/>
        <path d="M0,40 L0,-40" stroke="#ffffff" stroke-width="1.4" opacity=".35"/>
        @foreach([-26,-13,0,13,26] as $i)
        <path d="M0,{{ $i }} C12,{{ $i-4 }} 18,{{ $i-8 }} 20,{{ $i-14 }}" stroke="#ffffff" stroke-width="1" fill="none" opacity=".28"/>
        <path d="M0,{{ $i }} C-12,{{ $i-4 }} -18,{{ $i-8 }} -20,{{ $i-14 }}" stroke="#ffffff" stroke-width="1" fill="none" opacity=".28"/>
        @endforeach
    </g>
</svg>
