{{-- Shared Arena Belajar game-stage styles (visual language Jagat Misi) --}}
<style>
[x-cloak]{display:none!important}

.arena-stage {
    --arena-navy: #12345b;
    --arena-teal: #00a99d;
    --arena-amber: #f5a524;
    --arena-rose: #e85d75;
    --arena-leaf: #5ba85b;
    --arena-ink: #172033;
    --arena-muted: #667085;
    --arena-line: #d9dee8;
    --arena-bg: #f3f6fb;
    --arena-shadow: 0 18px 45px rgba(18, 52, 91, 0.12);
    --arena-shadow-lg: 0 28px 60px rgba(18, 52, 91, 0.18);
    --arena-warn: #f5a524;
    --arena-ok: #5ba85b;
    --arena-bad: #e85d75;
    --arena-sky: #7ec8ff;
    --arena-play: #00c853;
    position: relative;
    margin: -0.25rem -0.15rem 0;
    padding: 0.25rem 0.15rem 1.5rem;
}

.arena-stage-play {
    isolation: isolate;
    overflow: hidden;
    border-radius: 1.5rem;
}

.arena-play-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    border-radius: inherit;
    overflow: hidden;
    background:
        radial-gradient(ellipse 120% 80% at 50% -20%, rgba(0, 169, 157, 0.22), transparent 55%),
        radial-gradient(ellipse 70% 50% at 100% 100%, rgba(245, 165, 36, 0.12), transparent 50%),
        linear-gradient(165deg, #0a1628 0%, #0f1f2e 45%, #081018 100%);
}
.arena-play-bg-grid {
    position: absolute;
    inset: 0;
    opacity: 0.35;
    background-image:
        linear-gradient(rgba(126, 200, 255, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(126, 200, 255, 0.08) 1px, transparent 1px);
    background-size: 28px 28px;
    mask-image: radial-gradient(ellipse 90% 70% at 50% 40%, #000 20%, transparent 75%);
}
.arena-play-bg-glow {
    position: absolute;
    width: 14rem;
    height: 14rem;
    border-radius: 50%;
    top: 8%;
    right: -4%;
    background: radial-gradient(circle, rgba(0, 200, 83, 0.18), transparent 70%);
    animation: arena-play-float 8s ease-in-out infinite alternate;
}
@keyframes arena-play-float {
    from { transform: translate(0, 0) scale(1); }
    to { transform: translate(-1.5rem, 1rem) scale(1.08); }
}

/* ─── Game lobby (Roblox-style Discover) ─── */
.arena-lobby {
    --lobby-font: 'Fredoka', 'Plus Jakarta Sans', system-ui, sans-serif;
    margin: -1rem -1.25rem 0;
    padding: 0 1rem 2.5rem;
    min-height: 72vh;
    isolation: isolate;
    overflow: hidden;
}
@media (min-width: 641px) {
    .arena-lobby { margin: -1.25rem -1.75rem 0; padding: 0 1.5rem 2.75rem; }
}
.arena-lobby, .arena-lobby button, .arena-lobby a, .arena-lobby input, .arena-lobby select {
    font-family: var(--lobby-font);
}

.arena-lobby-world {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
}
.arena-lobby-sky {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 80% 50% at 50% -10%, #fff8e8 0%, transparent 55%),
        radial-gradient(circle at 12% 70%, rgba(0, 194, 178, 0.28), transparent 42%),
        radial-gradient(circle at 88% 30%, rgba(255, 176, 32, 0.22), transparent 40%),
        linear-gradient(180deg, #9ad4ff 0%, #c9ebff 38%, #e8f4ff 70%, #f4f7fb 100%);
}
.dark .arena-lobby-sky {
    background:
        radial-gradient(ellipse 80% 50% at 50% -10%, rgba(255, 176, 32, 0.18), transparent 55%),
        radial-gradient(circle at 12% 70%, rgba(0, 169, 157, 0.22), transparent 42%),
        linear-gradient(180deg, #0a1a2e 0%, #10253d 45%, #0f172a 100%);
}
.arena-lobby-grid {
    position: absolute;
    inset: 45% 0 0;
    background-image:
        linear-gradient(rgba(18, 52, 91, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(18, 52, 91, 0.06) 1px, transparent 1px);
    background-size: 48px 48px;
    transform: perspective(400px) rotateX(58deg) scale(1.4);
    transform-origin: center top;
    mask-image: linear-gradient(180deg, rgba(0,0,0,.55), transparent 85%);
}
.dark .arena-lobby-grid {
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
}

.arena-float-block {
    position: absolute;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: .55rem;
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.35),
        0 8px 0 rgba(0,0,0,.12),
        0 14px 22px rgba(18, 52, 91, 0.18);
    animation: arena-block-float 5.5s ease-in-out infinite;
}
.arena-fb-a { left: 6%; top: 18%; background: linear-gradient(145deg, #00c2b2, #008f84); animation-delay: 0s; }
.arena-fb-b { right: 10%; top: 14%; width: 1.8rem; height: 1.8rem; background: linear-gradient(145deg, #ffb020, #e09410); animation-delay: .8s; }
.arena-fb-c { left: 14%; top: 52%; width: 1.5rem; height: 1.5rem; background: linear-gradient(145deg, #ff6b8a, #d94a68); animation-delay: 1.4s; }
.arena-fb-d { right: 16%; top: 48%; background: linear-gradient(145deg, #4da3ff, #2276d2); animation-delay: 2s; }
.arena-float-coin {
    position: absolute;
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #ffe9a8, #ffb020 55%, #d48900);
    box-shadow: 0 0 0 3px rgba(255,176,32,.25), 0 6px 12px rgba(212,137,0,.35);
    animation: arena-coin-spin 3.8s linear infinite;
}
.arena-fc-a { left: 42%; top: 22%; }
.arena-fc-b { right: 28%; top: 58%; animation-delay: 1.2s; }
@keyframes arena-block-float {
    0%, 100% { transform: translateY(0) rotate(-6deg); }
    50% { transform: translateY(-14px) rotate(8deg); }
}
@keyframes arena-coin-spin {
    0% { transform: rotateY(0deg) translateY(0); }
    50% { transform: rotateY(180deg) translateY(-8px); }
    100% { transform: rotateY(360deg) translateY(0); }
}

.arena-lobby-hud {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-top: 1rem;
    margin-bottom: .5rem;
}
.arena-hud-back {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    max-width: 55%;
    min-height: 2.5rem;
    padding: .45rem .9rem;
    border-radius: 999px;
    background: rgba(255,255,255,.82);
    border: 2px solid rgba(18, 52, 91, 0.1);
    color: var(--arena-navy);
    font-weight: 700;
    font-size: .8rem;
    box-shadow: 0 6px 0 rgba(18, 52, 91, 0.08);
    transition: transform .12s ease;
}
.dark .arena-hud-back {
    background: rgba(15, 23, 42, .85);
    border-color: #334155;
    color: #e2e8f0;
    box-shadow: 0 6px 0 rgba(0,0,0,.25);
}
.arena-hud-back:hover { transform: translateY(1px); box-shadow: 0 4px 0 rgba(18, 52, 91, 0.08); }
.arena-hud-player {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .4rem .7rem .4rem .4rem;
    border-radius: 999px;
    background: rgba(255,255,255,.9);
    border: 2px solid rgba(18, 52, 91, 0.1);
    box-shadow: 0 6px 0 rgba(18, 52, 91, 0.08);
}
.dark .arena-hud-player {
    background: rgba(15, 23, 42, .9);
    border-color: #334155;
    box-shadow: 0 6px 0 rgba(0,0,0,.25);
}
.arena-hud-avatar {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: .85rem;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: .85rem;
    color: #fff;
    background: linear-gradient(145deg, #00c2b2, #0b3d6e);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.25);
}
.arena-hud-name {
    margin: 0;
    font-size: .82rem;
    font-weight: 700;
    color: var(--arena-ink);
    max-width: 9rem;
}
.dark .arena-hud-name { color: #f1f5f9; }
.arena-hud-role {
    margin: 0;
    font-size: .65rem;
    font-weight: 700;
    color: var(--arena-muted);
    text-transform: uppercase;
    letter-spacing: .04em;
}

.arena-lobby-welcome {
    position: relative;
    z-index: 2;
    text-align: center;
    padding: 1.25rem 0 1.5rem;
    opacity: 0;
    transform: translateY(18px) scale(.98);
    transition: opacity .55s ease, transform .55s cubic-bezier(.2,.9,.2,1);
}
.arena-lobby-welcome.is-in {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.arena-lobby-mascot {
    position: relative;
    width: 7.5rem;
    height: 7.5rem;
    margin: 0 auto 1rem;
}
.arena-mascot-ring {
    position: absolute;
    inset: 0;
    border-radius: 1.6rem;
    border: 3px dashed rgba(0, 169, 157, 0.45);
    animation: arena-spin 18s linear infinite;
}
.arena-mascot-core {
    position: absolute;
    inset: .55rem;
    border-radius: 1.35rem;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 2rem;
    font-weight: 800;
    background:
        radial-gradient(circle at 30% 25%, rgba(255,255,255,.45), transparent 40%),
        linear-gradient(155deg, #00c2b2 0%, #0b3d6e 55%, #e85d75 120%);
    box-shadow:
        inset 0 3px 0 rgba(255,255,255,.3),
        0 10px 0 rgba(11, 61, 110, 0.35),
        0 18px 32px rgba(11, 61, 110, 0.35);
    animation: arena-float 3.2s ease-in-out infinite;
}
.arena-mascot-badge {
    position: absolute;
    left: 50%;
    bottom: -.35rem;
    transform: translateX(-50%);
    padding: .2rem .55rem;
    border-radius: 999px;
    background: var(--arena-play);
    color: #fff;
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .06em;
    box-shadow: 0 3px 0 #00963e;
}
.arena-lobby-kicker {
    margin: 0 0 .35rem;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #0b5e8a;
}
.dark .arena-lobby-kicker { color: #7dd3fc; }
.arena-lobby-brand {
    margin: 0;
    font-size: clamp(2.4rem, 7vw, 3.6rem);
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.02em;
    color: var(--arena-navy);
    text-shadow:
        0 3px 0 rgba(255,255,255,.65),
        0 8px 0 rgba(18, 52, 91, 0.12);
}
.dark .arena-lobby-brand {
    color: #f8fafc;
    text-shadow: 0 4px 0 rgba(0,0,0,.35);
}
.arena-lobby-tagline {
    margin: .65rem auto 0;
    max-width: 26rem;
    font-size: .95rem;
    font-weight: 600;
    color: #3d5678;
}
.dark .arena-lobby-tagline { color: #94a3b8; }

.arena-lobby-stats {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: .55rem;
    margin-top: 1.15rem;
}
.arena-chip3d {
    min-width: 6.5rem;
    padding: .65rem .9rem;
    border-radius: 1rem;
    background: #fff;
    border: 2px solid rgba(18, 52, 91, 0.1);
    box-shadow: 0 5px 0 rgba(18, 52, 91, 0.1);
    text-align: center;
}
.dark .arena-chip3d {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 5px 0 rgba(0,0,0,.35);
}
.arena-chip3d strong {
    display: block;
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--arena-teal);
    line-height: 1.1;
}
.arena-chip3d span {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--arena-muted);
}
.arena-chip3d-amber strong { color: #d48900; }
.arena-chip3d-sky strong { color: #2276d2; }

.arena-lobby-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: .65rem;
    margin-top: 1.25rem;
}
.arena-play-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    min-height: 3.15rem;
    padding: .75rem 1.5rem;
    border-radius: 1rem;
    border: none;
    font-weight: 800;
    font-size: .95rem;
    color: #fff;
    background: linear-gradient(180deg, #5cff8f 0%, #16e065 40%, #00c853 100%);
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.45),
        0 6px 0 #008f38,
        0 12px 28px rgba(0, 200, 83, 0.5);
    transition: transform .1s ease, box-shadow .1s ease, filter .15s ease, background .15s ease, opacity .15s ease;
    text-decoration: none;
    cursor: pointer;
    text-shadow: 0 1px 0 rgba(0, 80, 30, 0.25);
}
.arena-play-btn:hover:not(:disabled) {
    filter: brightness(1.08) saturate(1.1);
    transform: translateY(-1px);
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.5),
        0 7px 0 #008f38,
        0 16px 32px rgba(0, 200, 83, 0.55);
}
.arena-play-btn:active:not(:disabled) {
    transform: translateY(4px);
    filter: brightness(1);
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.25),
        0 2px 0 #008f38,
        0 6px 12px rgba(0, 200, 83, 0.3);
}
.arena-play-btn:disabled,
.arena-play-btn[disabled] {
    cursor: not-allowed;
    color: #94a3b8;
    text-shadow: none;
    background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.5),
        0 5px 0 #94a3b8;
    filter: none;
    opacity: 1;
}
.arena-play-btn-amber {
    background: linear-gradient(180deg, #ffc44d 0%, #ffb020 55%, #e09410 100%);
    color: #3b2700;
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.4),
        0 6px 0 #c07a00,
        0 12px 24px rgba(224, 148, 16, 0.35);
}
.arena-play-btn-amber:hover {
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.4),
        0 7px 0 #c07a00,
        0 14px 28px rgba(224, 148, 16, 0.42);
}
.arena-play-btn-amber:active {
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.25),
        0 2px 0 #c07a00,
        0 6px 12px rgba(224, 148, 16, 0.25);
}
.arena-play-btn-ghost {
    background: rgba(255,255,255,.85);
    color: var(--arena-navy);
    box-shadow:
        inset 0 2px 0 rgba(255,255,255,.5),
        0 6px 0 rgba(18, 52, 91, 0.12),
        0 12px 20px rgba(18, 52, 91, 0.1);
}
.dark .arena-play-btn-ghost {
    background: rgba(15, 23, 42, .9);
    color: #e2e8f0;
    box-shadow: 0 6px 0 rgba(0,0,0,.35);
}

.arena-world-portals {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .85rem;
    margin: .25rem 0 1.25rem;
}
@media (max-width: 639px) {
    .arena-world-portals { grid-template-columns: 1fr; }
}
.arena-portal {
    display: grid;
    grid-template-columns: 5.5rem 1fr;
    gap: .85rem;
    align-items: center;
    text-align: left;
    padding: .65rem;
    border-radius: 1.25rem;
    border: 3px solid rgba(18, 52, 91, 0.1);
    background: rgba(255,255,255,.92);
    box-shadow: 0 7px 0 rgba(18, 52, 91, 0.1);
    cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease, border-color .15s ease;
    position: relative;
}
.dark .arena-portal {
    background: rgba(15, 23, 42, .92);
    border-color: #334155;
    box-shadow: 0 7px 0 rgba(0,0,0,.35);
}
.arena-portal:hover { transform: translateY(-2px); }
.arena-portal:active { transform: translateY(3px); box-shadow: 0 3px 0 rgba(18, 52, 91, 0.1); }
.arena-portal.active {
    border-color: var(--arena-teal);
    box-shadow: 0 7px 0 rgba(0, 169, 157, 0.35);
}
.arena-portal-thumb {
    width: 5.5rem;
    height: 5.5rem;
    border-radius: 1rem;
    display: grid;
    place-items: center;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: inset 0 2px 0 rgba(255,255,255,.3);
}
.arena-portal-thumb-kuis {
    background: linear-gradient(145deg, #00c2b2, #0b3d6e);
}
.arena-portal-thumb-misi {
    background: linear-gradient(145deg, #ffb020, #e85d75);
}
.arena-portal-shine {
    position: absolute;
    inset: 0;
    background: linear-gradient(115deg, transparent 40%, rgba(255,255,255,.35) 50%, transparent 60%);
    animation: arena-shine-sweep 3.5s ease-in-out infinite;
}
@keyframes arena-shine-sweep {
    0%, 70%, 100% { transform: translateX(-120%); }
    85% { transform: translateX(120%); }
}
.arena-portal-label {
    display: block;
    font-size: .65rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--arena-muted);
}
.arena-portal-title {
    display: block;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--arena-ink);
    line-height: 1.15;
}
.dark .arena-portal-title { color: #f1f5f9; }
.arena-portal-meta {
    display: block;
    margin-top: .2rem;
    font-size: .72rem;
    font-weight: 600;
    color: var(--arena-muted);
}
.arena-portal-join {
    position: absolute;
    top: .55rem;
    right: .65rem;
    padding: .15rem .45rem;
    border-radius: .4rem;
    background: var(--arena-play);
    color: #fff;
    font-size: .58rem;
    font-weight: 800;
    letter-spacing: .06em;
    box-shadow: 0 2px 0 #00963e;
}

.arena-discover { position: relative; z-index: 2; }
.arena-discover-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
}
.arena-discover-title {
    margin: 0;
    font-size: clamp(1.35rem, 3vw, 1.75rem);
    font-weight: 800;
    color: var(--arena-ink);
}
.dark .arena-discover-title { color: #f1f5f9; }
.arena-mini-cta {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    min-height: 2.4rem;
    padding: .45rem .9rem;
    border-radius: .85rem;
    background: #fff;
    border: 2px solid rgba(18, 52, 91, 0.1);
    color: var(--arena-teal);
    font-size: .8rem;
    font-weight: 800;
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.08);
    text-decoration: none;
}
.dark .arena-mini-cta {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 4px 0 rgba(0,0,0,.3);
}
.arena-mini-cta-ghost { color: var(--arena-muted); }
.arena-lobby-panel {
    border-width: 2px;
    border-radius: 1.25rem;
    box-shadow: 0 7px 0 rgba(18, 52, 91, 0.08);
}

.arena-xp-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
@media (min-width: 1024px) {
    .arena-xp-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.15rem; }
}
@media (max-width: 639px) {
    .arena-xp-grid { grid-template-columns: 1fr; }
}
.arena-xp-card {
    display: flex;
    flex-direction: column;
    border-radius: 1.15rem;
    overflow: hidden;
    background: #fff;
    border: 3px solid rgba(18, 52, 91, 0.08);
    box-shadow: 0 8px 0 rgba(18, 52, 91, 0.1);
    text-decoration: none;
    color: inherit;
    transition: transform .14s ease, box-shadow .14s ease;
}
.dark .arena-xp-card {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 8px 0 rgba(0,0,0,.35);
}
.arena-xp-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 0 rgba(18, 52, 91, 0.12);
}
.arena-xp-card:active {
    transform: translateY(2px);
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.1);
}
.arena-xp-thumb {
    position: relative;
    aspect-ratio: 16 / 10;
    display: grid;
    place-items: center;
    color: #fff;
    background:
        radial-gradient(circle at 80% 20%, rgba(255,255,255,.3), transparent 40%),
        linear-gradient(145deg, var(--art-a, var(--arena-teal)), var(--art-b, var(--arena-navy)));
    overflow: hidden;
}
.arena-xp-blocks {
    position: absolute;
    inset: 0;
    opacity: .35;
    background:
        linear-gradient(90deg, transparent 46%, rgba(255,255,255,.2) 46% 54%, transparent 54%),
        linear-gradient(transparent 46%, rgba(255,255,255,.15) 46% 54%, transparent 54%);
    background-size: 28px 28px;
}
.arena-xp-play {
    position: absolute;
    right: .7rem;
    bottom: .7rem;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: .85rem;
    background: var(--arena-play);
    color: #fff;
    display: grid;
    place-items: center;
    box-shadow: 0 4px 0 #00963e;
    transition: transform .12s ease;
}
.arena-xp-card:hover .arena-xp-play { transform: scale(1.08); }
.arena-xp-status {
    position: absolute;
    top: .65rem;
    left: .65rem;
    padding: .2rem .5rem;
    border-radius: .45rem;
    background: rgba(0,0,0,.35);
    backdrop-filter: blur(4px);
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #fff;
}
.arena-xp-status.is-closed { background: rgba(232, 93, 117, 0.75); }
.arena-xp-info {
    display: grid;
    gap: .35rem;
    padding: .85rem .95rem 1rem;
    flex: 1;
}
.arena-xp-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.25;
    color: var(--arena-ink);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.dark .arena-xp-title { color: #f1f5f9; }
.arena-xp-meta {
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: .3rem;
    font-size: .75rem;
    font-weight: 700;
    color: var(--arena-muted);
}
.arena-xp-cta {
    margin-top: .35rem;
    font-size: .78rem;
    font-weight: 800;
    color: var(--arena-teal);
}
.arena-xp-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 2.5rem 1.25rem;
    border-radius: 1.25rem;
    background: rgba(255,255,255,.9);
    border: 3px dashed rgba(18, 52, 91, 0.15);
}
.dark .arena-xp-empty {
    background: rgba(15, 23, 42, .9);
    border-color: #334155;
}
.arena-xp-empty-ico {
    width: 4rem;
    height: 4rem;
    margin: 0 auto .85rem;
    border-radius: 1.1rem;
    display: grid;
    place-items: center;
    background: #eaf8f6;
    color: var(--arena-teal);
    box-shadow: 0 5px 0 rgba(0, 169, 157, 0.2);
}

/* ─── Roblox / modern experience tiles (Kuis & Live) ─── */
.arena-rx {
    --rx-live: #ff2d55;
    --rx-live-glow: rgba(255, 45, 85, 0.55);
    --rx-solo: #00d2ff;
    --rx-lime: #39ff14;
    --rx-panel: rgba(255,255,255,.94);
}
.dark .arena-rx { --rx-panel: rgba(12, 20, 36, .94); }

.arena-rx-filters {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin-bottom: .35rem;
}
.arena-rx-chip {
    min-height: 2.35rem;
    padding: .4rem .95rem;
    border-radius: 999px;
    border: 2.5px solid rgba(18, 52, 91, 0.12);
    background: rgba(255,255,255,.88);
    color: #334155;
    font-size: .78rem;
    font-weight: 800;
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.08);
    cursor: pointer;
    transition: transform .1s ease, box-shadow .1s ease, border-color .15s ease, background .15s ease;
}
.dark .arena-rx-chip {
    background: rgba(15, 23, 42, .9);
    border-color: #334155;
    color: #cbd5e1;
    box-shadow: 0 4px 0 rgba(0,0,0,.3);
}
.arena-rx-chip:hover { transform: translateY(-1px); }
.arena-rx-chip:active { transform: translateY(2px); box-shadow: 0 2px 0 rgba(18, 52, 91, 0.08); }
.arena-rx-chip.active {
    border-color: var(--arena-teal);
    background: linear-gradient(180deg, #e8fffb, #c5fff5);
    color: #0b5e55;
    box-shadow: 0 4px 0 #00a99d;
}
.dark .arena-rx-chip.active {
    background: linear-gradient(180deg, #134e4a, #0f3d3a);
    color: #99f6e4;
    box-shadow: 0 4px 0 #0d9488;
}
.arena-rx-chip-live.active {
    border-color: var(--rx-live);
    background: linear-gradient(180deg, #ffe4ea, #ffc2cf);
    color: #9f1239;
    box-shadow: 0 4px 0 #e11d48;
}
.dark .arena-rx-chip-live.active {
    background: linear-gradient(180deg, #4c0519, #881337);
    color: #fecdd3;
    box-shadow: 0 4px 0 #be123c;
}

.arena-rx-live-rail {
    position: relative;
    border-radius: 1.35rem;
    padding: 1rem 1.1rem 1.15rem;
    background:
        radial-gradient(circle at 8% 20%, rgba(255, 45, 85, 0.35), transparent 42%),
        radial-gradient(circle at 92% 80%, rgba(0, 210, 255, 0.28), transparent 45%),
        linear-gradient(125deg, #1a0a2e 0%, #0b1f3a 48%, #062a28 100%);
    border: 3px solid rgba(255, 45, 85, 0.45);
    box-shadow:
        0 0 0 1px rgba(255,255,255,.08) inset,
        0 10px 0 rgba(0,0,0,.35),
        0 0 40px var(--rx-live-glow);
    overflow: hidden;
}
.arena-rx-live-rail::before {
    content: '';
    position: absolute;
    inset: -40% auto auto -10%;
    width: 60%;
    height: 80%;
    background: linear-gradient(115deg, transparent, rgba(255,255,255,.08), transparent);
    animation: arena-rx-shine 4.5s ease-in-out infinite;
    pointer-events: none;
}
@keyframes arena-rx-shine {
    0%, 100% { transform: translateX(0) rotate(12deg); opacity: .4; }
    50% { transform: translateX(120%) rotate(12deg); opacity: .85; }
}
.arena-rx-live-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .85rem;
    position: relative;
    z-index: 1;
}
.arena-rx-live-badge {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .35rem .75rem;
    border-radius: 999px;
    background: var(--rx-live);
    color: #fff;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    box-shadow: 0 0 0 4px rgba(255, 45, 85, 0.25), 0 4px 0 #b91c3a;
}
.arena-rx-live-dot {
    width: .55rem;
    height: .55rem;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 0 0 0 rgba(255,255,255,.7);
    animation: arena-rx-pulse 1.4s ease-out infinite;
}
@keyframes arena-rx-pulse {
    0% { box-shadow: 0 0 0 0 rgba(255,255,255,.65); }
    70% { box-shadow: 0 0 0 10px rgba(255,255,255,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
}
.arena-rx-live-note {
    margin: 0;
    font-size: .78rem;
    font-weight: 700;
    color: rgba(255,255,255,.75);
}
.arena-rx-live-scroll {
    display: flex;
    gap: .75rem;
    overflow-x: auto;
    padding-bottom: .25rem;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    position: relative;
    z-index: 1;
}
.arena-rx-live-scroll::-webkit-scrollbar { height: 6px; }
.arena-rx-live-scroll::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.25);
    border-radius: 999px;
}
.arena-rx-live-card {
    flex: 0 0 min(78%, 15.5rem);
    scroll-snap-align: start;
    display: grid;
    grid-template-columns: 4.25rem 1fr;
    gap: .7rem;
    align-items: center;
    padding: .55rem;
    border-radius: 1rem;
    background: rgba(255,255,255,.1);
    border: 2px solid rgba(255,255,255,.18);
    text-decoration: none;
    color: #fff;
    backdrop-filter: blur(8px);
    transition: transform .12s ease, background .15s ease;
}
.arena-rx-live-card:hover {
    transform: translateY(-2px) scale(1.02);
    background: rgba(255,255,255,.16);
}
.arena-rx-live-thumb {
    aspect-ratio: 1;
    border-radius: .85rem;
    display: grid;
    place-items: center;
    background: linear-gradient(145deg, var(--art-a), var(--art-b));
    box-shadow: inset 0 2px 0 rgba(255,255,255,.35), 0 4px 0 rgba(0,0,0,.25);
}
.arena-rx-live-title {
    margin: 0;
    font-size: .92rem;
    font-weight: 800;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.arena-rx-live-meta {
    margin: .2rem 0 0;
    font-size: .68rem;
    font-weight: 700;
    color: rgba(255,255,255,.7);
    text-transform: uppercase;
    letter-spacing: .04em;
}

.arena-rx-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.1rem;
}
@media (min-width: 1024px) {
    .arena-rx-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.25rem; }
}
@media (max-width: 639px) {
    .arena-rx-grid { grid-template-columns: 1fr; }
}

.arena-rx-card {
    position: relative;
    display: flex;
    flex-direction: column;
    border-radius: 1.35rem;
    overflow: hidden;
    background: var(--rx-panel);
    border: 3.5px solid rgba(18, 52, 91, 0.12);
    box-shadow:
        0 10px 0 rgba(18, 52, 91, 0.14),
        0 18px 36px rgba(18, 52, 91, 0.12);
    text-decoration: none;
    color: inherit;
    transition: transform .15s cubic-bezier(.2,.9,.2,1), box-shadow .15s ease;
    animation: arena-rx-pop .45s both;
}
.dark .arena-rx-card {
    border-color: #334155;
    box-shadow: 0 10px 0 rgba(0,0,0,.45), 0 18px 36px rgba(0,0,0,.35);
}
.arena-rx-card:hover {
    transform: translateY(-6px) rotate(-0.4deg);
    box-shadow:
        0 16px 0 rgba(18, 52, 91, 0.16),
        0 28px 44px rgba(18, 52, 91, 0.18);
}
.arena-rx-card:active {
    transform: translateY(3px);
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.12);
}
@keyframes arena-rx-pop {
    from { opacity: 0; transform: translateY(16px) scale(.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.arena-rx-thumb {
    position: relative;
    aspect-ratio: 16 / 11;
    display: grid;
    place-items: center;
    color: #fff;
    overflow: hidden;
    background:
        radial-gradient(circle at 18% 22%, rgba(255,255,255,.4), transparent 38%),
        radial-gradient(circle at 85% 75%, rgba(0,0,0,.25), transparent 45%),
        linear-gradient(148deg, var(--art-a, #00c2b2), var(--art-b, #0b3d6e));
}
.arena-rx-thumb-grid {
    position: absolute;
    inset: 0;
    opacity: .28;
    background-image:
        linear-gradient(rgba(255,255,255,.25) 2px, transparent 2px),
        linear-gradient(90deg, rgba(255,255,255,.25) 2px, transparent 2px);
    background-size: 22px 22px;
    transform: perspective(220px) rotateX(48deg) scale(1.5);
    transform-origin: center 80%;
}
.arena-rx-thumb-blocks {
    position: absolute;
    inset: 12% 10% auto;
    height: 42%;
    display: flex;
    justify-content: center;
    gap: .35rem;
    align-items: flex-end;
    pointer-events: none;
}
.arena-rx-thumb-blocks span {
    display: block;
    width: 1.35rem;
    border-radius: .35rem;
    background: rgba(255,255,255,.55);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.5), 0 3px 0 rgba(0,0,0,.2);
    animation: arena-block-float 3.6s ease-in-out infinite;
}
.arena-rx-thumb-blocks span:nth-child(1) { height: 1.1rem; animation-delay: 0s; background: #fff; }
.arena-rx-thumb-blocks span:nth-child(2) { height: 1.85rem; animation-delay: .25s; background: #ffe08a; }
.arena-rx-thumb-blocks span:nth-child(3) { height: 1.4rem; animation-delay: .5s; background: #7dffb0; }
.arena-rx-thumb-icon {
    position: relative;
    z-index: 1;
    width: 3.6rem;
    height: 3.6rem;
    border-radius: 1.05rem;
    display: grid;
    place-items: center;
    background: rgba(0,0,0,.28);
    backdrop-filter: blur(6px);
    border: 2px solid rgba(255,255,255,.35);
    box-shadow: 0 8px 0 rgba(0,0,0,.2);
}
.arena-rx-play {
    position: absolute;
    right: .75rem;
    bottom: .75rem;
    z-index: 2;
    min-width: 2.75rem;
    height: 2.75rem;
    padding: 0 .65rem;
    border-radius: .95rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .25rem;
    background: linear-gradient(180deg, #6dff9b, #00e676 55%, #00c853);
    color: #043017;
    font-size: .68rem;
    font-weight: 900;
    letter-spacing: .02em;
    text-transform: uppercase;
    box-shadow: inset 0 2px 0 rgba(255,255,255,.55), 0 5px 0 #008c3a;
    transition: transform .12s ease;
}
.arena-rx-card:hover .arena-rx-play { transform: scale(1.08); }
.arena-rx-flags {
    position: absolute;
    top: .65rem;
    left: .65rem;
    right: .65rem;
    z-index: 2;
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}
.arena-rx-flag {
    padding: .22rem .5rem;
    border-radius: .5rem;
    font-size: .62rem;
    font-weight: 900;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #fff;
    background: rgba(0,0,0,.4);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255,255,255,.2);
}
.arena-rx-flag-live {
    background: var(--rx-live);
    border-color: rgba(255,255,255,.35);
    box-shadow: 0 0 12px var(--rx-live-glow);
    animation: arena-rx-live-blink 1.6s ease-in-out infinite;
}
@keyframes arena-rx-live-blink {
    0%, 100% { filter: brightness(1); }
    50% { filter: brightness(1.25); }
}
.arena-rx-flag-ok { background: #00a86b; }
.arena-rx-flag-draft { background: #64748b; }
.arena-rx-flag-closed { background: #e11d48; }

.arena-rx-body {
    display: grid;
    gap: .45rem;
    padding: .95rem 1rem 1.05rem;
    flex: 1;
}
.arena-rx-title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 800;
    line-height: 1.2;
    color: var(--arena-ink);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.dark .arena-rx-title { color: #f8fafc; }
.arena-rx-stats {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
}
.arena-rx-stat {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .28rem .55rem;
    border-radius: .65rem;
    background: #f1f5f9;
    color: #475569;
    font-size: .7rem;
    font-weight: 800;
}
.dark .arena-rx-stat {
    background: #1e293b;
    color: #94a3b8;
}
.arena-rx-xp {
    height: .45rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
    margin-top: .15rem;
}
.dark .arena-rx-xp { background: #1e293b; }
.arena-rx-xp > span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #00c2b2, #39ff14);
    box-shadow: 0 0 10px rgba(57, 255, 20, 0.45);
}
.arena-rx-actions {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: .45rem;
    margin-top: .25rem;
}
.arena-rx-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .3rem;
    min-height: 2.45rem;
    padding: .45rem .55rem;
    border-radius: .85rem;
    font-size: .72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .03em;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: transform .1s ease, filter .12s ease;
}
.arena-rx-btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
.arena-rx-btn:active { transform: translateY(2px); }
.arena-rx-btn-solo {
    color: #042f2e;
    background: linear-gradient(180deg, #5cffd2, #00c2b2);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.45), 0 4px 0 #0f766e;
}
.arena-rx-btn-live {
    color: #fff;
    background: linear-gradient(180deg, #ff6b8a, #ff2d55);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.35), 0 4px 0 #be123c;
}
.arena-rx-btn-ghost {
    color: var(--arena-navy);
    background: #fff;
    border: 2px solid rgba(18, 52, 91, 0.12);
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.1);
}
.dark .arena-rx-btn-ghost {
    color: #e2e8f0;
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 4px 0 rgba(0,0,0,.35);
}

.arena-rx-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 2.75rem 1.5rem;
    border-radius: 1.5rem;
    background:
        radial-gradient(circle at 50% 0%, rgba(0, 194, 178, 0.18), transparent 55%),
        var(--rx-panel);
    border: 3.5px dashed rgba(0, 169, 157, 0.35);
    box-shadow: 0 10px 0 rgba(18, 52, 91, 0.08);
}
.arena-rx-empty-ico {
    width: 5rem;
    height: 5rem;
    margin: 0 auto 1rem;
    border-radius: 1.35rem;
    display: grid;
    place-items: center;
    color: #fff;
    background: linear-gradient(145deg, #00c2b2, #0b3d6e);
    box-shadow: inset 0 3px 0 rgba(255,255,255,.35), 0 8px 0 rgba(11, 61, 110, 0.35);
    animation: arena-float 3s ease-in-out infinite;
}

/* Experience detail (show) */
.arena-rx-detail {
    max-width: 52rem;
    margin: 0 auto;
}
.arena-rx-detail-hero {
    position: relative;
    border-radius: 1.6rem;
    overflow: hidden;
    color: #fff;
    border: 3.5px solid rgba(255,255,255,.12);
    box-shadow: 0 14px 0 rgba(18, 52, 91, 0.2), 0 28px 50px rgba(18, 52, 91, 0.25);
    background:
        radial-gradient(circle at 85% 15%, rgba(255, 176, 32, 0.45), transparent 40%),
        radial-gradient(circle at 10% 90%, rgba(0, 194, 178, 0.5), transparent 42%),
        linear-gradient(145deg, #071526 0%, #12345b 45%, #0a3d38 100%);
}
.arena-rx-detail-hero-grid {
    position: absolute;
    inset: 0;
    opacity: .2;
    background-image:
        linear-gradient(rgba(255,255,255,.2) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.2) 1px, transparent 1px);
    background-size: 36px 36px;
    mask-image: linear-gradient(180deg, #000, transparent);
}
.arena-rx-detail-panel {
    border-radius: 1.35rem;
    padding: 1.15rem;
    background: var(--rx-panel);
    border: 3px solid rgba(18, 52, 91, 0.1);
    box-shadow: 0 8px 0 rgba(18, 52, 91, 0.1);
}
.dark .arena-rx-detail-panel {
    border-color: #334155;
    box-shadow: 0 8px 0 rgba(0,0,0,.35);
}
.arena-rx-cta-row {
    display: grid;
    gap: .65rem;
}
@media (min-width: 640px) {
    .arena-rx-cta-row { grid-template-columns: 1.3fr 1fr; }
}
.arena-rx-cta-big {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    min-height: 3.4rem;
    padding: .85rem 1.25rem;
    border-radius: 1.1rem;
    font-size: 1rem;
    font-weight: 900;
    text-decoration: none;
    border: none;
    cursor: pointer;
    width: 100%;
}
.arena-rx-cta-big.solo {
    color: #042f2e;
    background: linear-gradient(180deg, #6dffb0, #00c853);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.5), 0 7px 0 #008f38, 0 16px 28px rgba(0,200,83,.4);
}
.arena-rx-cta-big.live {
    color: #fff;
    background: linear-gradient(180deg, #ff7a96, #ff2d55);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.35), 0 7px 0 #be123c, 0 16px 28px rgba(255,45,85,.4);
}
.arena-rx-cta-big:active { transform: translateY(4px); }

.arena-rx-host-deck {
    position: relative;
    overflow: visible;
}
.arena-rx-host-deck::before {
    content: '';
    position: absolute;
    inset: auto -10% -40% 40%;
    height: 10rem;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(245, 165, 36, 0.14), transparent 70%);
    pointer-events: none;
}
.arena-rx-host-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .4rem .7rem;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 800;
    color: #92400e;
    background: linear-gradient(180deg, #fff7ed, #ffedd5);
    border: 2px solid #fdba74;
    box-shadow: 0 3px 0 #fb923c;
}
.dark .arena-rx-host-badge {
    color: #fde68a;
    background: linear-gradient(180deg, #422006, #78350f);
    border-color: #b45309;
    box-shadow: 0 3px 0 #92400e;
}
.arena-rx-manage-section { position: relative; z-[1]; display: grid; gap: .55rem; }
.arena-rx-manage-label {
    margin: 0;
    font-size: .68rem;
    font-weight: 900;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #94a3b8;
}
.arena-rx-manage-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .65rem;
}
@media (min-width: 720px) {
    .arena-rx-manage-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

/* Legacy flat buttons (kept for other pages) */
.arena-rx-manage-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    min-height: 3rem;
    padding: .65rem .75rem;
    border-radius: .95rem;
    font-size: .8rem;
    font-weight: 800;
    text-decoration: none;
    border: 2.5px solid rgba(18, 52, 91, 0.12);
    background: #fff;
    color: var(--arena-ink);
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.1);
}
.dark .arena-rx-manage-btn {
    background: #0f172a;
    border-color: #334155;
    color: #e2e8f0;
    box-shadow: 0 4px 0 rgba(0,0,0,.35);
}
.arena-rx-manage-btn.primary {
    border: none;
    color: #fff;
    background: linear-gradient(180deg, #00d4c8, #008f84);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.35), 0 4px 0 #0f766e;
}
.arena-rx-manage-btn.live {
    border: none;
    color: #fff;
    background: linear-gradient(180deg, #ff6b8a, #ff2d55);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.3), 0 4px 0 #be123c;
}

.arena-rx-tool {
    position: relative;
    display: flex;
    align-items: center;
    gap: .7rem;
    min-height: 4.35rem;
    padding: .75rem .85rem;
    border-radius: 1.1rem;
    text-decoration: none;
    text-align: left;
    border: 2.5px solid transparent;
    background: #fff;
    color: var(--arena-ink);
    box-shadow: 0 5px 0 rgba(18, 52, 91, 0.1);
    transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
    cursor: pointer;
    width: 100%;
    font: inherit;
}
.dark .arena-rx-tool {
    background: #0f172a;
    color: #e2e8f0;
    box-shadow: 0 5px 0 rgba(0,0,0,.4);
}
.arena-rx-tool:hover {
    transform: translateY(-2px);
    filter: brightness(1.03);
}
.arena-rx-tool:active {
    transform: translateY(3px);
    box-shadow: 0 2px 0 rgba(18, 52, 91, 0.1);
}
.arena-rx-tool-ico {
    flex-shrink: 0;
    width: 2.55rem;
    height: 2.55rem;
    border-radius: .9rem;
    display: grid;
    place-items: center;
    color: #fff;
    box-shadow: inset 0 2px 0 rgba(255,255,255,.35), 0 3px 0 rgba(0,0,0,.18);
}
.arena-rx-tool-ico i,
.arena-rx-tool-ico svg {
    width: 1.15rem;
    height: 1.15rem;
}
.arena-rx-tool-copy {
    display: grid;
    gap: .1rem;
    min-width: 0;
}
.arena-rx-tool-copy strong {
    font-size: .88rem;
    font-weight: 900;
    line-height: 1.15;
    font-family: 'Fredoka', system-ui, sans-serif;
}
.arena-rx-tool-copy small {
    font-size: .68rem;
    font-weight: 700;
    color: #64748b;
    line-height: 1.2;
}
.dark .arena-rx-tool-copy small { color: #94a3b8; }

.arena-rx-tool.tone-publish { border-color: rgba(0, 169, 157, .35); background: linear-gradient(180deg, #ecfffc, #dffaf6); }
.arena-rx-tool.tone-publish .arena-rx-tool-ico { background: linear-gradient(160deg, #2dd4bf, #0f766e); }
.arena-rx-tool.tone-close { border-color: rgba(225, 29, 72, .35); background: linear-gradient(180deg, #fff1f2, #ffe4e6); }
.arena-rx-tool.tone-close .arena-rx-tool-ico { background: linear-gradient(160deg, #fb7185, #be123c); }
.arena-rx-tool.tone-token { border-color: rgba(245, 158, 11, .4); background: linear-gradient(180deg, #fffbeb, #fef3c7); }
.arena-rx-tool.tone-token .arena-rx-tool-ico { background: linear-gradient(160deg, #fbbf24, #d97706); }
.arena-rx-tool.tone-unlock { border-color: rgba(100, 116, 139, .35); background: linear-gradient(180deg, #f8fafc, #e2e8f0); }
.arena-rx-tool.tone-unlock .arena-rx-tool-ico { background: linear-gradient(160deg, #94a3b8, #475569); }
.arena-rx-tool.tone-draft { border-color: rgba(99, 102, 241, .35); background: linear-gradient(180deg, #eef2ff, #e0e7ff); }
.arena-rx-tool.tone-draft .arena-rx-tool-ico { background: linear-gradient(160deg, #818cf8, #4f46e5); }
.arena-rx-token-card {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.1rem;
    border-radius: 1.1rem;
    border: 1.5px solid rgba(245, 158, 11, .35);
    background: linear-gradient(135deg, #fffbeb, #fff7ed);
}
.arena-rx-token-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
}
.dark .arena-rx-token-card {
    background: linear-gradient(135deg, #422006, #0f172a);
    border-color: rgba(245, 158, 11, .25);
}

.arena-rx-join-qr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    gap: 1rem;
}
.arena-rx-join-qr-card {
    text-align: center;
    padding: 1rem;
    border-radius: 1rem;
    border: 1.5px solid rgba(20, 184, 166, .25);
    background: linear-gradient(180deg, #f0fdfa, #ecfeff);
}
.dark .arena-rx-join-qr-card {
    background: linear-gradient(180deg, #042f2e, #0f172a);
    border-color: rgba(45, 212, 191, .2);
}
.arena-rx-join-qr-label {
    margin: 0 0 .65rem;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #0d9488;
}
.arena-rx-join-qr-box {
    display: inline-block;
    padding: .5rem;
    border-radius: .75rem;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .08);
}
.arena-rx-join-qr-box svg { display: block; width: 100%; height: auto; }
.arena-rx-join-qr-hint {
    margin: .5rem 0 0;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}
.arena-rx-join-barcode-wrap {
    margin-top: .65rem;
    padding: .45rem .5rem;
    border-radius: .65rem;
    background: #fff;
    border: 1px solid #e2e8f0;
}
.arena-rx-join-barcode-wrap .arena-join-barcode {
    display: block;
    width: 100%;
    height: auto;
}
.arena-rx-live-stage .arena-rx-join-barcode-wrap {
    background: rgba(255,255,255,.95);
    border-color: rgba(255,255,255,.25);
}

/* Coach: jari menunjuk Terbitkan */
.arena-rx-publish-spot {
    position: relative;
    z-index: 3;
}
.arena-rx-publish-spot.is-coaching {
    z-index: 6;
}
.arena-rx-publish-spot > form { margin: 0; }
.arena-rx-publish-spot .arena-rx-tool {
    width: 100%;
    position: relative;
    overflow: visible;
}
.arena-rx-tool.is-spotlight {
    animation: arena-rx-publish-pulse 1.6s ease-in-out infinite;
}
.arena-rx-publish-glow {
    position: absolute;
    inset: -6px;
    border-radius: 1.25rem;
    pointer-events: none;
    box-shadow: 0 0 0 0 rgba(0, 169, 157, 0.45);
    animation: arena-rx-publish-ring 1.6s ease-out infinite;
}
.arena-rx-point-coach {
    position: absolute;
    left: 50%;
    bottom: calc(100% + .15rem);
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .15rem;
    pointer-events: none;
    z-index: 8;
    filter: drop-shadow(0 8px 12px rgba(15, 23, 42, 0.25));
}
.arena-rx-point-bubble {
    pointer-events: auto;
    position: relative;
    max-width: 13.5rem;
    padding: .55rem .9rem .55rem .75rem;
    border-radius: .95rem;
    background: linear-gradient(180deg, #0f766e, #0d9488);
    color: #ecfeff;
    border: 2.5px solid #5eead4;
    box-shadow: 0 5px 0 #115e59;
    text-align: center;
    animation: arena-rx-point-bob 1.15s ease-in-out infinite;
}
.arena-rx-point-bubble strong {
    display: block;
    font-size: .68rem;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    opacity: .9;
}
.arena-rx-point-bubble span {
    display: block;
    margin-top: .15rem;
    font-size: .78rem;
    font-weight: 800;
    line-height: 1.25;
    font-family: 'Fredoka', system-ui, sans-serif;
}
.arena-rx-point-bubble em {
    font-style: normal;
    color: #fef08a;
}
.arena-rx-point-bubble::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -7px;
    width: 12px;
    height: 12px;
    background: #0d9488;
    border-right: 2.5px solid #5eead4;
    border-bottom: 2.5px solid #5eead4;
    transform: translateX(-50%) rotate(45deg);
}
.arena-rx-point-dismiss {
    position: absolute;
    top: -0.35rem;
    right: -0.35rem;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 999px;
    border: 2px solid #99f6e4;
    background: #134e4a;
    color: #fff;
    font-size: .85rem;
    font-weight: 900;
    line-height: 1;
    cursor: pointer;
    display: grid;
    place-items: center;
    padding: 0;
}
.arena-rx-point-finger {
    margin-top: .1rem;
    animation: arena-rx-finger-point 0.85s ease-in-out infinite;
    transform-origin: top center;
}
.arena-rx-point-finger svg {
    display: block;
    filter: drop-shadow(0 4px 0 rgba(15, 23, 42, 0.2));
}
@keyframes arena-rx-finger-point {
    0%, 100% { transform: translateY(-12px) rotate(6deg); }
    50% { transform: translateY(8px) rotate(-4deg); }
}
@keyframes arena-rx-point-bob {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}
@keyframes arena-rx-publish-pulse {
    0%, 100% { transform: translateY(0); filter: brightness(1); }
    50% { transform: translateY(-2px); filter: brightness(1.06); }
}
@keyframes arena-rx-publish-ring {
    0% { box-shadow: 0 0 0 0 rgba(0, 169, 157, 0.5); opacity: 1; }
    100% { box-shadow: 0 0 0 14px rgba(0, 169, 157, 0); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
    .arena-rx-point-finger,
    .arena-rx-point-bubble,
    .arena-rx-tool.is-spotlight,
    .arena-rx-publish-glow { animation: none !important; }
}
.arena-rx-tool.tone-live { border-color: rgba(255, 45, 85, .35); background: linear-gradient(180deg, #fff1f4, #ffe4ea); }
.arena-rx-tool.tone-live .arena-rx-tool-ico { background: linear-gradient(160deg, #ff7a96, #ff2d55); }
.arena-rx-tool.tone-hasil { border-color: rgba(245, 158, 11, .4); background: linear-gradient(180deg, #fffbeb, #fef3c7); }
.arena-rx-tool.tone-hasil .arena-rx-tool-ico { background: linear-gradient(160deg, #fbbf24, #d97706); }
.arena-rx-tool.tone-edit { border-color: rgba(59, 130, 246, .35); background: linear-gradient(180deg, #eff6ff, #dbeafe); }
.arena-rx-tool.tone-edit .arena-rx-tool-ico { background: linear-gradient(160deg, #60a5fa, #2563eb); }
.arena-rx-tool.tone-tim { border-color: rgba(139, 92, 246, .35); background: linear-gradient(180deg, #f5f3ff, #ede9fe); }
.arena-rx-tool.tone-tim .arena-rx-tool-ico { background: linear-gradient(160deg, #a78bfa, #7c3aed); }
.arena-rx-tool.tone-template { border-color: rgba(20, 184, 166, .35); background: linear-gradient(180deg, #f0fdfa, #ccfbf1); }
.arena-rx-tool.tone-template .arena-rx-tool-ico { background: linear-gradient(160deg, #2dd4bf, #0d9488); }
.arena-rx-tool.tone-pdf { border-color: rgba(100, 116, 139, .35); background: linear-gradient(180deg, #f8fafc, #e2e8f0); }
.arena-rx-tool.tone-pdf .arena-rx-tool-ico { background: linear-gradient(160deg, #94a3b8, #475569); }
.arena-rx-tool.tone-key { border-color: rgba(234, 179, 8, .4); background: linear-gradient(180deg, #fefce8, #fef08a); }
.arena-rx-tool.tone-key .arena-rx-tool-ico { background: linear-gradient(160deg, #facc15, #ca8a04); }

.dark .arena-rx-tool.tone-publish { background: linear-gradient(180deg, #134e4a, #0f172a); }
.dark .arena-rx-tool.tone-close { background: linear-gradient(180deg, #4c0519, #0f172a); }
.dark .arena-rx-tool.tone-token { background: linear-gradient(180deg, #78350f, #0f172a); }
.dark .arena-rx-tool.tone-unlock { background: linear-gradient(180deg, #334155, #0f172a); }
.dark .arena-rx-tool.tone-draft { background: linear-gradient(180deg, #312e81, #0f172a); }
.dark .arena-rx-tool.tone-live { background: linear-gradient(180deg, #4c0519, #0f172a); }
.dark .arena-rx-tool.tone-hasil { background: linear-gradient(180deg, #451a03, #0f172a); }
.dark .arena-rx-tool.tone-edit { background: linear-gradient(180deg, #1e3a8a, #0f172a); }
.dark .arena-rx-tool.tone-tim { background: linear-gradient(180deg, #4c1d95, #0f172a); }
.dark .arena-rx-tool.tone-template { background: linear-gradient(180deg, #134e4a, #0f172a); }
.dark .arena-rx-tool.tone-pdf { background: linear-gradient(180deg, #1e293b, #0f172a); }
.dark .arena-rx-tool.tone-key { background: linear-gradient(180deg, #713f12, #0f172a); }

.arena-rx-tool-pulse {
    position: absolute;
    top: .55rem;
    right: .55rem;
    width: .55rem;
    height: .55rem;
    border-radius: 999px;
    background: #ff2d55;
    box-shadow: 0 0 0 0 rgba(255, 45, 85, .55);
    animation: arena-rx-pulse 1.4s ease-out infinite;
}
@keyframes arena-rx-pulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 45, 85, .55); }
    70% { box-shadow: 0 0 0 8px rgba(255, 45, 85, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 45, 85, 0); }
}

.arena-rx-skin-bar {
    position: relative;
    z-[1];
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .85rem 1rem;
    border-radius: 1rem;
    border: 2.5px dashed rgba(18, 52, 91, 0.14);
    background: rgba(248, 250, 252, .8);
}
.dark .arena-rx-skin-bar {
    border-color: #334155;
    background: rgba(15, 23, 42, .55);
}
.arena-rx-skin-select {
    min-height: 2.75rem;
    min-width: 10rem;
    padding: .45rem .85rem;
    border-radius: .85rem;
    border: 2.5px solid rgba(18, 52, 91, 0.14);
    background: #fff;
    font-size: .85rem;
    font-weight: 800;
    color: var(--arena-ink);
    box-shadow: 0 3px 0 rgba(18, 52, 91, 0.08);
}
.dark .arena-rx-skin-select {
    background: #0f172a;
    border-color: #334155;
    color: #e2e8f0;
    box-shadow: 0 3px 0 rgba(0,0,0,.35);
}

.arena-rx-preview {
    position: relative;
    z-[1];
    border-radius: 1.05rem;
    border: 2.5px solid rgba(18, 52, 91, 0.1);
    background: #fff;
    padding: .15rem .85rem .85rem;
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.08);
}
.dark .arena-rx-preview {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 4px 0 rgba(0,0,0,.35);
}
.arena-rx-preview > summary {
    list-style: none;
    display: flex;
    align-items: center;
    gap: .7rem;
    min-height: 3.4rem;
    cursor: pointer;
    font-weight: 800;
}
.arena-rx-preview > summary::-webkit-details-marker { display: none; }
.arena-rx-preview > summary span:not(.arena-rx-tool-ico) {
    display: grid;
    gap: .05rem;
    flex: 1;
    min-width: 0;
}
.arena-rx-preview > summary strong {
    font-size: .88rem;
    font-family: 'Fredoka', system-ui, sans-serif;
}
.arena-rx-preview > summary small {
    font-size: .68rem;
    font-weight: 700;
    color: #64748b;
}
.arena-rx-preview .tone-preview-ico {
    background: linear-gradient(160deg, #38bdf8, #0284c7);
}
.arena-rx-preview-chevron {
    width: 1rem;
    height: 1rem;
    color: #94a3b8;
    transition: transform .15s ease;
}
.arena-rx-preview[open] .arena-rx-preview-chevron { transform: rotate(180deg); }

.arena-rx-danger-row {
    position: relative;
    z-[1];
    display: flex;
    justify-content: flex-end;
    margin: 0;
    padding-top: .15rem;
    border-top: 2px dashed rgba(244, 63, 94, 0.25);
}
.arena-rx-danger-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    min-height: 2.6rem;
    padding: .45rem .85rem;
    border-radius: .8rem;
    border: 2px solid rgba(244, 63, 94, 0.35);
    background: #fff1f2;
    color: #e11d48;
    font-size: .8rem;
    font-weight: 800;
    cursor: pointer;
}
.dark .arena-rx-danger-btn {
    background: #4c0519;
    border-color: #be123c;
    color: #fda4af;
}
.arena-rx-danger-btn:hover { filter: brightness(0.97); }

.arena-rx-live-stage {
    position: relative;
    min-height: min(62vh, 28rem);
    border-radius: 1.5rem;
    color: #f8fafc;
    border: 3.5px solid rgba(255, 45, 85, 0.35);
    background:
        radial-gradient(ellipse 90% 70% at 50% -15%, rgba(255, 45, 85, 0.35), transparent 55%),
        radial-gradient(circle at 90% 80%, rgba(0, 210, 255, 0.22), transparent 40%),
        linear-gradient(165deg, #0a0614 0%, #10253d 48%, #062a28 100%);
    box-shadow: 0 12px 0 rgba(0,0,0,.35), 0 0 40px rgba(255, 45, 85, 0.25);
    overflow: hidden;
}
.arena-rx-live-stage-grid {
    position: absolute;
    inset: 0;
    opacity: .22;
    pointer-events: none;
    background-image:
        linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px);
    background-size: 32px 32px;
    mask-image: linear-gradient(180deg, #000 30%, transparent);
}

.arena-rx-result-orb {
    width: 8.5rem;
    height: 8.5rem;
    margin: 0 auto;
    border-radius: 1.75rem;
    display: grid;
    place-items: center;
    background:
        radial-gradient(circle at 30% 25%, rgba(255,255,255,.45), transparent 42%),
        linear-gradient(145deg, #39ff14, #00c853 50%, #0b3d6e);
    box-shadow: inset 0 3px 0 rgba(255,255,255,.4), 0 10px 0 rgba(0, 140, 58, 0.45);
    color: #fff;
}
.arena-rx-copy-list {
    max-height: 16rem;
    overflow-y: auto;
    display: grid;
    gap: .45rem;
    padding: .15rem;
}
.arena-rx-copy-item {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .7rem .85rem;
    border-radius: .95rem;
    border: 2.5px solid rgba(18, 52, 91, 0.1);
    background: #fff;
    cursor: pointer;
    font-weight: 700;
    font-size: .85rem;
    color: var(--arena-ink);
    box-shadow: 0 3px 0 rgba(18, 52, 91, 0.08);
}
.dark .arena-rx-copy-item {
    background: #0f172a;
    border-color: #334155;
    color: #e2e8f0;
    box-shadow: 0 3px 0 rgba(0,0,0,.3);
}
.arena-rx-copy-item:has(input:checked) {
    border-color: var(--arena-teal);
    background: #e8fffb;
    box-shadow: 0 3px 0 #00a99d;
}
.dark .arena-rx-copy-item:has(input:checked) {
    background: #134e4a;
    border-color: #14b8a6;
}
.arena-rx-copy-item input { width: 1.1rem; height: 1.1rem; accent-color: #00a99d; }

/* ===== Ular Tangga ===== */
.arena-snake-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.4rem;
    padding: 1.25rem 1.35rem;
    color: #f8fafc;
    border: 3px solid rgba(34, 197, 94, 0.35);
    background:
        radial-gradient(ellipse 80% 70% at 90% 10%, rgba(250, 204, 21, 0.35), transparent 50%),
        radial-gradient(ellipse 60% 50% at 5% 90%, rgba(34, 197, 94, 0.4), transparent 50%),
        linear-gradient(155deg, #052e16 0%, #0f3d2e 45%, #12345b 100%);
    box-shadow: 0 10px 0 rgba(5, 46, 22, 0.45);
}
.arena-snake-hero-grid {
    position: absolute;
    inset: 0;
    opacity: .18;
    background-image:
        linear-gradient(rgba(255,255,255,.15) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.15) 1px, transparent 1px);
    background-size: 28px 28px;
    mask-image: linear-gradient(180deg, #000, transparent);
}
.arena-snake-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .55rem;
}
.arena-snake-stat {
    border-radius: 1rem;
    padding: .65rem .7rem;
    background: var(--rx-panel, #fff);
    border: 2.5px solid rgba(18, 52, 91, 0.1);
    box-shadow: 0 4px 0 rgba(18, 52, 91, 0.08);
    text-align: center;
}
.dark .arena-snake-stat {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 4px 0 rgba(0,0,0,.35);
}
.arena-snake-stat span {
    display: block;
    font-size: .65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #94a3b8;
}
.arena-snake-stat strong {
    display: block;
    margin-top: .15rem;
    font-size: 1.15rem;
    font-weight: 900;
    font-family: 'Fredoka', system-ui, sans-serif;
    color: var(--arena-ink, #12345b);
}
.dark .arena-snake-stat strong { color: #e2e8f0; }

.arena-snake-board-wrap {
    border-radius: 1.35rem;
    padding: .85rem;
    background:
        radial-gradient(circle at 20% 20%, rgba(134, 239, 172, 0.35), transparent 45%),
        radial-gradient(circle at 90% 80%, rgba(252, 211, 77, 0.3), transparent 40%),
        linear-gradient(180deg, #ecfdf5, #fef9c3);
    border: 3px solid rgba(22, 101, 52, 0.2);
    box-shadow: 0 10px 0 rgba(22, 101, 52, 0.15);
}
.dark .arena-snake-board-wrap {
    background: linear-gradient(180deg, #052e16, #1e293b);
    border-color: #166534;
    box-shadow: 0 10px 0 rgba(0,0,0,.4);
}
.arena-snake-board {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: .4rem;
}
.arena-snake-cell {
    position: relative;
    aspect-ratio: 1;
    border-radius: .75rem;
    padding: .3rem;
    border: 2px solid rgba(15, 23, 42, 0.12);
    overflow: hidden;
    min-height: 3.2rem;
}
.arena-snake-cell.is-odd { background: #fff; }
.arena-snake-cell.is-even { background: #dcfce7; }
.dark .arena-snake-cell.is-odd { background: #0f172a; border-color: #334155; }
.dark .arena-snake-cell.is-even { background: #14532d; border-color: #166534; }
.arena-snake-cell.is-start {
    background: linear-gradient(145deg, #93c5fd, #3b82f6) !important;
    border-color: #1d4ed8;
    color: #fff;
}
.arena-snake-cell.is-finish {
    background: linear-gradient(145deg, #fde047, #f59e0b) !important;
    border-color: #d97706;
    color: #422006;
}
.arena-snake-cell.has-ladder { box-shadow: inset 0 0 0 2px rgba(34, 197, 94, 0.55); }
.arena-snake-cell.has-snake { box-shadow: inset 0 0 0 2px rgba(244, 63, 94, 0.5); }
.arena-snake-cell.has-token {
    outline: 3px solid #00a99d;
    outline-offset: 1px;
    z-index: 2;
}
.arena-snake-num {
    position: absolute;
    top: .2rem;
    left: .35rem;
    font-size: .65rem;
    font-weight: 900;
    opacity: .7;
}
.arena-snake-mark {
    position: absolute;
    bottom: .2rem;
    left: .25rem;
    right: .25rem;
    display: flex;
    align-items: center;
    gap: .15rem;
    font-size: .55rem;
    font-weight: 800;
    color: #15803d;
}
.arena-snake-mark.snake { color: #e11d48; }
.arena-snake-mark i,
.arena-snake-mark svg { width: .7rem; height: .7rem; }
.arena-snake-mark em { font-style: normal; }
.arena-snake-token {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    pointer-events: none;
}
.arena-snake-token i,
.arena-snake-token svg {
    width: 1.35rem;
    height: 1.35rem;
    color: #0f766e;
    filter: drop-shadow(0 2px 0 rgba(0,0,0,.2));
}
.arena-snake-legend {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem 1.25rem;
    margin-top: .75rem;
    font-size: .72rem;
    font-weight: 800;
    color: #475569;
}
.dark .arena-snake-legend { color: #94a3b8; }
.arena-snake-legend i.lg,
.arena-snake-legend i.sn {
    display: inline-block;
    width: .7rem;
    height: .7rem;
    border-radius: .2rem;
    margin-right: .35rem;
    vertical-align: -1px;
}
.arena-snake-legend i.lg { background: #22c55e; }
.arena-snake-legend i.sn { background: #f43f5e; }

.arena-snake-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 1.25rem;
    background: var(--rx-panel, #fff);
    border: 3px solid rgba(18, 52, 91, 0.1);
    box-shadow: 0 8px 0 rgba(18, 52, 91, 0.1);
}
.dark .arena-snake-controls {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 8px 0 rgba(0,0,0,.35);
}
.arena-snake-dice {
    width: 4.25rem;
    height: 4.25rem;
    border-radius: 1rem;
    display: grid;
    place-items: center;
    font-size: 2.4rem;
    line-height: 1;
    background: linear-gradient(160deg, #fff, #e2e8f0);
    border: 3px solid #94a3b8;
    box-shadow: inset 0 2px 0 #fff, 0 6px 0 #64748b;
    user-select: none;
}
.dark .arena-snake-dice {
    background: linear-gradient(160deg, #1e293b, #0f172a);
    border-color: #475569;
    box-shadow: inset 0 2px 0 rgba(255,255,255,.08), 0 6px 0 #020617;
}
.arena-snake-dice.rolling {
    animation: arena-snake-shake .12s linear infinite;
}
@keyframes arena-snake-shake {
    0%, 100% { transform: rotate(-6deg) scale(1.02); }
    50% { transform: rotate(6deg) scale(0.98); }
}

.arena-snake-modal {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(2, 6, 23, 0.62);
    backdrop-filter: blur(4px);
}
.arena-snake-modal-card {
    width: min(100%, 28rem);
    max-height: min(90vh, 36rem);
    overflow-y: auto;
    border-radius: 1.35rem;
    padding: 1.25rem;
    background: #fff;
    border: 3px solid rgba(18, 52, 91, 0.12);
    box-shadow: 0 16px 0 rgba(18, 52, 91, 0.2);
}
.dark .arena-snake-modal-card {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 16px 0 rgba(0,0,0,.45);
}
.arena-snake-opt {
    display: block;
    width: 100%;
    text-align: left;
    padding: .8rem 1rem;
    border-radius: .95rem;
    border: 2.5px solid rgba(18, 52, 91, 0.12);
    background: #f8fafc;
    font-size: .9rem;
    font-weight: 800;
    color: var(--arena-ink, #12345b);
    cursor: pointer;
    box-shadow: 0 3px 0 rgba(18, 52, 91, 0.08);
}
.dark .arena-snake-opt {
    background: #1e293b;
    border-color: #334155;
    color: #e2e8f0;
    box-shadow: 0 3px 0 rgba(0,0,0,.35);
}
.arena-snake-opt:hover:not(:disabled) { transform: translateY(-1px); }
.arena-snake-opt.picked { border-color: #0ea5e9; }
.arena-snake-opt.ok {
    border-color: #22c55e;
    background: #dcfce7;
    color: #166534;
}
.arena-snake-opt.bad {
    border-color: #f43f5e;
    background: #ffe4e6;
    color: #9f1239;
}
.arena-snake-input {
    width: 100%;
    min-height: 2.9rem;
    padding: .65rem .9rem;
    border-radius: .9rem;
    border: 2.5px solid rgba(18, 52, 91, 0.14);
    font-weight: 700;
    background: #fff;
}
.dark .arena-snake-input {
    background: #1e293b;
    border-color: #334155;
    color: #e2e8f0;
}
.arena-snake-win {
    position: fixed;
    inset: 0;
    z-index: 85;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(2, 6, 23, 0.55);
}
.arena-snake-win-card {
    width: min(100%, 22rem);
    text-align: center;
    display: grid;
    gap: .65rem;
    place-items: center;
    padding: 1.5rem;
    border-radius: 1.4rem;
    background: linear-gradient(180deg, #ecfdf5, #fff);
    border: 3px solid #86efac;
    box-shadow: 0 14px 0 #16a34a;
    color: #052e16;
}
.dark .arena-snake-win-card {
    background: linear-gradient(180deg, #14532d, #0f172a);
    border-color: #22c55e;
    color: #ecfdf5;
    box-shadow: 0 14px 0 #052e16;
}

@media (max-width: 480px) {
    .arena-snake-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .arena-snake-cell { min-height: 2.6rem; border-radius: .55rem; }
    .arena-snake-mark em { display: none; }
}

.arena-hero {
    background:
        radial-gradient(ellipse 55% 70% at 92% 8%, rgba(245, 165, 36, 0.42), transparent 52%),
        radial-gradient(ellipse 50% 60% at 6% 90%, rgba(0, 169, 157, 0.48), transparent 48%),
        radial-gradient(circle at 50% 120%, rgba(232, 93, 117, 0.18), transparent 45%),
        linear-gradient(148deg, #0a223d 0%, #12345b 42%, #0c3d4a 100%);
    color: #f8fafc;
    border-radius: 1.35rem;
    overflow: hidden;
    position: relative;
    box-shadow: var(--arena-shadow-lg);
    isolation: isolate;
}
.arena-hero-stars {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image:
        radial-gradient(1.5px 1.5px at 12% 22%, rgba(255,255,255,.55), transparent),
        radial-gradient(1px 1px at 28% 68%, rgba(255,255,255,.4), transparent),
        radial-gradient(1.5px 1.5px at 48% 18%, rgba(255,255,255,.35), transparent),
        radial-gradient(1px 1px at 66% 42%, rgba(255,255,255,.5), transparent),
        radial-gradient(1.5px 1.5px at 78% 72%, rgba(255,255,255,.3), transparent),
        radial-gradient(1px 1px at 90% 28%, rgba(255,255,255,.45), transparent);
    opacity: .7;
    animation: arena-twinkle 4.5s ease-in-out infinite;
}
@keyframes arena-twinkle {
    0%, 100% { opacity: .45; }
    50% { opacity: .85; }
}
.arena-hero::before {
    content: '';
    position: absolute;
    width: 280px;
    height: 280px;
    right: -70px;
    top: -80px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,.1);
    pointer-events: none;
}
.arena-hero::after {
    content: '';
    position: absolute;
    width: 180px;
    height: 180px;
    right: 36px;
    bottom: -50px;
    border-radius: 50%;
    border: 2px solid rgba(0, 169, 157, 0.28);
    pointer-events: none;
}

.arena-intro {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(200px, 0.7fr);
    gap: 1.75rem;
    align-items: center;
}
.arena-intro-katalog {
    grid-template-columns: minmax(200px, 0.75fr) minmax(0, 1.35fr);
    padding-top: 2.25rem;
}
@media (max-width: 639px) {
    .arena-intro { grid-template-columns: 1fr; }
    .arena-intro-katalog { grid-template-columns: 1fr; padding-top: 2.75rem; }
    .arena-planet { display: none; }
}

.arena-hero-back {
    position: absolute;
    top: 1.1rem;
    left: 1.1rem;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    min-height: 2.15rem;
    padding: .35rem .85rem .35rem .55rem;
    border-radius: 999px;
    font-size: .8rem;
    font-weight: 700;
    color: #e2e8f0;
    text-decoration: none;
    background: rgba(15, 35, 60, 0.55);
    border: 1.5px solid rgba(148, 190, 220, 0.35);
    backdrop-filter: blur(6px);
    transition: background .15s ease, border-color .15s ease;
}
.arena-hero-back:hover {
    background: rgba(30, 58, 95, 0.75);
    border-color: rgba(186, 230, 253, 0.55);
    color: #fff;
}
@media (min-width: 640px) {
    .arena-hero-back { top: 1.35rem; left: 1.5rem; }
}

.arena-eyebrow {
    margin: 0 0 .45rem;
    color: var(--arena-amber);
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.arena-title {
    font-size: clamp(2rem, 5vw, 2.75rem);
    font-weight: 900;
    letter-spacing: -0.03em;
    line-height: 1.05;
    text-shadow: 0 8px 28px rgba(0,0,0,.25);
}

.arena-planet {
    position: relative;
    width: min(100%, 220px);
    aspect-ratio: 1;
    margin-inline: auto;
    display: grid;
    place-items: center;
}
.arena-planet-core {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 28%, #fff 0 8%, var(--arena-amber) 9% 40%, var(--arena-rose) 41% 100%);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 1.85rem;
    font-weight: 900;
    box-shadow:
        0 0 0 10px rgba(245, 165, 36, 0.12),
        0 16px 32px rgba(232, 93, 117, 0.35);
    z-index: 1;
    animation: arena-float 3.6s ease-in-out infinite;
}
.arena-orbit {
    position: absolute;
    border: 2px solid rgba(255,255,255,.16);
    border-radius: 50%;
}
.arena-orbit-a { width: 78%; height: 78%; animation: arena-spin 16s linear infinite; }
.arena-orbit-b {
    width: 98%; height: 44%;
    border-color: rgba(0, 169, 157, 0.42);
    animation: arena-spin-rev 20s linear infinite;
}
.arena-orbit-dot {
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--arena-teal);
    box-shadow: 0 0 12px rgba(0, 169, 157, .8);
    top: 8%;
    left: 50%;
    transform: translateX(-50%);
}
@keyframes arena-spin { to { transform: rotate(360deg); } }
@keyframes arena-spin-rev {
    from { transform: rotate(-18deg); }
    to { transform: rotate(-378deg); }
}
@keyframes arena-float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.arena-mode-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .4rem;
    padding: .4rem;
    border-radius: 1.1rem;
    background: rgba(255,255,255,.92);
    border: 1px solid var(--arena-line);
    box-shadow: var(--arena-shadow);
    backdrop-filter: blur(8px);
}
.dark .arena-mode-tabs {
    background: rgba(15, 23, 42, .92);
    border-color: #334155;
}
.arena-mode-tab {
    min-height: 3.35rem;
    border-radius: .85rem;
    border: 1px solid transparent;
    background: transparent;
    color: var(--arena-navy);
    font-weight: 800;
    font-size: .9rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    cursor: pointer;
    transition: transform .15s ease, background .18s ease, color .18s ease, box-shadow .18s ease;
}
.dark .arena-mode-tab { color: #e2e8f0; }
.arena-mode-tab .arena-mode-ico {
    width: 2rem;
    height: 2rem;
    border-radius: .65rem;
    display: grid;
    place-items: center;
    background: #eef2f7;
    color: var(--arena-navy);
    transition: background .18s ease, color .18s ease;
}
.dark .arena-mode-tab .arena-mode-ico {
    background: #1e293b;
    color: #cbd5e1;
}
.arena-mode-tab.active {
    background: linear-gradient(135deg, #00a99d, #0d8f86);
    color: #fff;
    box-shadow: 0 10px 24px rgba(0, 169, 157, 0.35);
    transform: translateY(-1px);
}
.arena-mode-tab.active .arena-mode-ico {
    background: rgba(255,255,255,.22);
    color: #fff;
}
.arena-mode-tab:not(.active):hover {
    background: #f3f7f7;
}
.dark .arena-mode-tab:not(.active):hover {
    background: #1e293b;
}

.arena-panel {
    border: 1px solid var(--arena-line);
    border-radius: 1.1rem;
    background: #fff;
    box-shadow: var(--arena-shadow);
    padding: 1.25rem 1.35rem;
}
.dark .arena-panel {
    background: #0f172a;
    border-color: #334155;
    box-shadow: none;
}

.arena-mission-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.1rem;
}
@media (min-width: 1024px) {
    .arena-mission-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 639px) {
    .arena-mission-grid { grid-template-columns: 1fr; }
}

.arena-mission-card {
    min-height: 300px;
    border: 1px solid transparent;
    border-radius: 1.15rem;
    padding: 0;
    background: #fff;
    display: grid;
    grid-template-rows: auto 1fr;
    overflow: hidden;
    transition: transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 8px 24px rgba(18, 52, 91, 0.08);
}
.dark .arena-mission-card {
    background: #0f172a;
    box-shadow: none;
    border-color: #334155;
}
.arena-mission-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: var(--arena-shadow-lg);
}
.arena-mission-card-body {
    display: grid;
    gap: .7rem;
    padding: 1rem 1.1rem 1.15rem;
    align-content: start;
    min-height: 0;
}

/* Katalog Misi — grid & kartu sejajar */
.arena-catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.1rem;
}
.arena-catalog-empty { grid-column: 1 / -1; }
.arena-catalog-card {
    height: 100%;
    min-height: 340px;
}
.arena-catalog-body {
    grid-template-rows: auto auto 1fr auto auto;
    gap: .55rem;
    height: 100%;
}
.arena-catalog-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    align-items: center;
    min-height: 1.7rem;
}
.arena-catalog-pills .arena-pill {
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.arena-catalog-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 900;
    line-height: 1.3;
    color: #172033;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.6em;
}
.dark .arena-catalog-title { color: #f1f5f9; }
.arena-catalog-summary {
    margin: 0;
    font-size: .84rem;
    line-height: 1.45;
    color: #667085;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.5em;
}
.dark .arena-catalog-summary { color: #94a3b8; }
.arena-catalog-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .35rem;
    font-size: .72rem;
    font-weight: 700;
    color: #94a3b8;
}
.arena-catalog-cta {
    margin-top: .15rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .78rem;
    font-weight: 800;
    color: var(--arena-teal);
    padding-top: .55rem;
    border-top: 1px dashed var(--arena-line);
}

.arena-card-art {
    height: 124px;
    border-radius: 0;
    background:
        radial-gradient(circle at 80% 20%, rgba(255,255,255,.28), transparent 40%),
        linear-gradient(135deg, var(--art-a, var(--arena-teal)), var(--art-b, var(--arena-navy)));
    position: relative;
    overflow: hidden;
    display: grid;
    place-items: center;
    color: #fff;
}
.arena-card-art::before,
.arena-card-art::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.22);
}
.arena-card-art::before {
    width: 110px; height: 110px;
    right: -18px; top: -28px;
}
.arena-card-art::after {
    width: 48px; height: 48px;
    left: 16px; bottom: -10px;
}
.arena-card-art > * { position: relative; z-index: 1; }
.arena-card-shine {
    position: absolute;
    inset: 0;
    background: linear-gradient(115deg, transparent 35%, rgba(255,255,255,.28) 48%, transparent 62%);
    transform: translateX(-120%);
    transition: transform .55s ease;
}
.arena-mission-card:hover .arena-card-shine { transform: translateX(120%); }
.arena-card-play {
    position: absolute;
    right: .85rem;
    bottom: .85rem;
    z-index: 2;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 999px;
    background: #fff;
    color: var(--arena-navy);
    display: grid;
    place-items: center;
    box-shadow: 0 8px 18px rgba(0,0,0,.18);
    transition: transform .18s ease;
}
.arena-mission-card:hover .arena-card-play { transform: scale(1.08); }

.arena-pill {
    border-radius: 999px;
    padding: .32rem .6rem;
    color: #334155;
    background: #eef2f7;
    font-size: .65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.dark .arena-pill {
    background: #1e293b;
    color: #94a3b8;
}
.arena-pill-teal { background: #eaf8f6; color: #007a72; }
.arena-pill-amber { background: #fff4e0; color: #9a6700; }
.arena-pill-rose { background: #fde8ec; color: #b42345; }
.arena-pill-sd { background: #e8f5e9; color: #1b7a3a; }
.arena-pill-smp { background: #e3f2fd; color: #1565c0; }
.arena-pill-sma { background: #f3e8ff; color: #7c3aed; }
.arena-pill-umum { background: #eef2f7; color: #475569; }
.dark .arena-pill-teal { background: rgba(0,169,157,.2); color: #5eead4; }
.dark .arena-pill-amber { background: rgba(245,165,36,.2); color: #fbbf24; }
.dark .arena-pill-rose { background: rgba(232,93,117,.2); color: #fda4af; }
.dark .arena-pill-sd { background: rgba(34,197,94,.18); color: #86efac; }
.dark .arena-pill-smp { background: rgba(59,130,246,.2); color: #93c5fd; }
.dark .arena-pill-sma { background: rgba(168,85,247,.2); color: #d8b4fe; }
.dark .arena-pill-umum { background: #1e293b; color: #94a3b8; }

.arena-jenjang-filters { display: flex; flex-wrap: wrap; gap: .4rem; }
.arena-jenjang-chip {
    border: 1px solid var(--arena-line);
    background: #fff;
    color: var(--arena-navy);
    border-radius: 999px;
    padding: .4rem .85rem;
    font-size: .75rem;
    font-weight: 800;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease;
}
.dark .arena-jenjang-chip { background: #0f172a; color: #e2e8f0; border-color: #334155; }
.arena-jenjang-chip.active {
    background: var(--arena-navy);
    color: #fff;
    border-color: var(--arena-navy);
    transform: translateY(-1px);
}
.arena-jenjang-chip.arena-jenjang-sd.active { background: #1b7a3a; border-color: #1b7a3a; }
.arena-jenjang-chip.arena-jenjang-smp.active { background: #1565c0; border-color: #1565c0; }
.arena-jenjang-chip.arena-jenjang-sma.active { background: #7c3aed; border-color: #7c3aed; }
.arena-jenjang-chip.arena-jenjang-tren.active {
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    border-color: #ea580c;
    color: #fff;
}
.arena-pill-tren {
    background: linear-gradient(135deg, #fff7ed, #ffedd5);
    color: #c2410c;
    border: 1px solid #fdba74;
}
.dark .arena-pill-tren {
    background: rgba(234, 88, 12, 0.22);
    color: #fdba74;
    border-color: rgba(251, 146, 60, 0.35);
}
.arena-tren-card { background: linear-gradient(180deg, #fffbeb, #f8fafc); }
.dark .arena-tren-card { background: linear-gradient(180deg, #1c1917, #0f172a); }
.arena-jenjang-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .85rem;
}
@media (max-width: 900px) {
    .arena-jenjang-grid { grid-template-columns: 1fr; }
}
.arena-jenjang-card {
    border: 1px solid var(--arena-line);
    border-radius: .9rem;
    padding: .9rem 1rem;
    background: #f8fafc;
}
.dark .arena-jenjang-card { background: #0f172a; border-color: #334155; }
.arena-jenjang-card.arena-jenjang-sd { border-top: 3px solid #1b7a3a; }
.arena-jenjang-card.arena-jenjang-smp { border-top: 3px solid #1565c0; }
.arena-jenjang-card.arena-jenjang-sma { border-top: 3px solid #7c3aed; }
.arena-jenjang-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: .7rem;
}
.arena-jenjang-list li {
    display: grid;
    gap: .15rem;
    padding-bottom: .65rem;
    border-bottom: 1px dashed var(--arena-line);
}
.arena-jenjang-list li:last-child { border-bottom: 0; padding-bottom: 0; }
.arena-jenjang-list strong {
    font-size: .84rem;
    color: var(--arena-ink);
    line-height: 1.3;
}
.dark .arena-jenjang-list strong { color: #e2e8f0; }
.arena-jenjang-list span {
    font-size: .7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: var(--arena-teal);
}
.arena-jenjang-list em {
    font-style: normal;
    font-size: .75rem;
    color: var(--arena-muted);
    line-height: 1.35;
}

.arena-stat-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .65rem;
}
@media (min-width: 640px) {
    .arena-stat-row { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.arena-stat {
    border-radius: .85rem;
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.08);
    padding: .8rem .95rem;
    backdrop-filter: blur(4px);
    transition: background .15s ease, transform .15s ease;
}
.arena-stat:hover {
    background: rgba(255,255,255,.14);
    transform: translateY(-1px);
}
.arena-stat strong {
    display: block;
    font-size: 1.45rem;
    font-weight: 900;
    line-height: 1.1;
}
.arena-stat span {
    font-size: .66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    opacity: .75;
}

.arena-section-head h2 {
    letter-spacing: -0.02em;
}
.arena-cta-row { display: flex; flex-wrap: wrap; gap: .55rem; }

@keyframes arena-pop {
    0% { transform: scale(.92); opacity: 0; }
    70% { transform: scale(1.03); }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes arena-slide-up {
    from { transform: translateY(18px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@keyframes arena-pulse-bar {
    0%, 100% { filter: brightness(1); }
    50% { filter: brightness(1.15); }
}
@keyframes arena-shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}
@keyframes arena-score-burst {
    0% { transform: scale(.6); opacity: 0; }
    40% { transform: scale(1.12); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}

.arena-anim-in { animation: arena-slide-up .38s ease-out both; }
.arena-anim-pop { animation: arena-pop .42s cubic-bezier(.2,1.2,.4,1) both; }
.arena-feedback-ok { animation: arena-score-burst .45s ease-out; color: var(--arena-ok); }
.arena-feedback-bad { animation: arena-shake .35s ease-out; color: var(--arena-bad); }

.arena-progress {
    height: .55rem;
    border-radius: 999px;
    background: rgba(255,255,255,.12);
    overflow: hidden;
}
.arena-progress > span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--cp), color-mix(in srgb, var(--cp) 40%, #fbbf24));
    transition: width .45s cubic-bezier(.2,.8,.2,1);
    animation: arena-pulse-bar 2.2s ease-in-out infinite;
}

.arena-opt {
    display: flex;
    align-items: center;
    gap: .85rem;
    width: 100%;
    text-align: left;
    min-height: 3.4rem;
    padding: .9rem 1rem;
    border-radius: 1rem;
    border: 2px solid rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
    color: #f1f5f9;
    font-weight: 650;
    font-size: .95rem;
    transition: transform .15s ease, border-color .15s ease, background .15s ease, box-shadow .15s ease;
    cursor: pointer;
}
.arena-opt:hover:not(:disabled) {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--cp) 70%, white);
    background: rgba(255,255,255,.1);
}
.arena-opt:active:not(:disabled) { transform: scale(.98); }
.arena-opt.is-selected {
    border-color: transparent;
    background: var(--cp);
    color: #fff;
    box-shadow: 0 8px 24px color-mix(in srgb, var(--cp) 35%, transparent);
}
.arena-opt.is-correct {
    border-color: var(--arena-ok);
    background: color-mix(in srgb, var(--arena-ok) 28%, transparent);
}
.arena-opt-letter {
    flex-shrink: 0;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: .7rem;
    display: grid;
    place-items: center;
    font-weight: 900;
    font-size: .85rem;
    background: rgba(255,255,255,.14);
    letter-spacing: .02em;
}
.arena-opt.is-selected .arena-opt-letter {
    background: rgba(255,255,255,.25);
}

.arena-play-shell {
    position: relative;
    background:
        radial-gradient(ellipse 90% 70% at 50% -10%, color-mix(in srgb, var(--cp, #00a99d) 35%, transparent), transparent 55%),
        linear-gradient(180deg, rgba(12, 26, 36, 0.92) 0%, rgba(16, 31, 42, 0.95) 40%, rgba(11, 21, 32, 0.98) 100%);
    border-radius: 1.5rem;
    color: #f8fafc;
    padding: 1.25rem;
    min-height: min(70vh, 36rem);
}
@media (min-width: 640px) {
    .arena-play-shell { padding: 1.75rem; }
}

.arena-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .3rem .7rem;
    border-radius: .5rem;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    background: rgba(245, 165, 36, 0.22);
    color: #ffe8b8;
}

.arena-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    min-height: 2.85rem;
    padding: .7rem 1.25rem;
    border-radius: .85rem;
    font-weight: 800;
    font-size: .875rem;
    color: #fff;
    background: linear-gradient(135deg, #00a99d, #0d8f86);
    border: none;
    box-shadow: 0 8px 20px rgba(0, 169, 157, 0.28);
    transition: transform .15s ease, filter .15s ease, box-shadow .15s ease;
}
.arena-cta:hover { transform: translateY(-2px); filter: brightness(1.05); box-shadow: 0 12px 28px rgba(0, 169, 157, 0.38); }
.arena-cta-amber {
    background: linear-gradient(135deg, #f5a524, #e09410);
    color: #1b2434;
    box-shadow: 0 8px 20px rgba(245, 165, 36, 0.28);
}
.arena-cta-amber:hover { filter: brightness(1.05); box-shadow: 0 12px 28px rgba(245, 165, 36, 0.38); }
.arena-cta-ghost {
    background: rgba(255,255,255,.1);
    color: #e2e8f0;
    border: 1.5px solid rgba(255,255,255,.22);
    box-shadow: none;
}
.arena-cta-ghost:hover { background: rgba(255,255,255,.18); filter: none; box-shadow: none; }

.arena-card-game {
    border-radius: .75rem;
    border: 1px solid var(--arena-line);
    background: #fff;
    box-shadow: 0 1px 0 rgba(18, 52, 91, 0.04);
    transition: transform .18s ease, box-shadow .18s ease;
}
.dark .arena-card-game {
    background: #0f172a;
    border-color: #334155;
}
.arena-card-game:hover {
    transform: translateY(-3px);
    box-shadow: var(--arena-shadow);
}

.arena-rank {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: .55rem;
    display: grid;
    place-items: center;
    font-weight: 900;
    font-size: .75rem;
    background: #e2e8f0;
    color: #475569;
}
.arena-rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #422006; }
.arena-rank-2 { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: #0f172a; }
.arena-rank-3 { background: linear-gradient(135deg, #fdba74, #ea580c); color: #431407; }

.arena-online-dot {
    width: .65rem;
    height: .65rem;
    border-radius: 999px;
    flex-shrink: 0;
    display: inline-block;
    box-shadow: 0 0 0 2px rgba(255,255,255,.35);
}
.arena-online-dot.is-on {
    background: #39ff14;
    box-shadow: 0 0 0 2px rgba(57,255,20,.25), 0 0 8px rgba(57,255,20,.65);
    animation: arena-online-pulse 1.6s ease-in-out infinite;
}
.arena-online-dot.is-off {
    background: #94a3b8;
    box-shadow: none;
}
@keyframes arena-online-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.15); opacity: .85; }
}

.arena-score-orb {
    width: 8.5rem;
    height: 8.5rem;
    margin: 0 auto;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background:
        radial-gradient(circle at 30% 25%, rgba(255,255,255,.35), transparent 45%),
        linear-gradient(145deg, var(--cp), color-mix(in srgb, var(--cp) 45%, #0c1a24));
    color: #fff;
    box-shadow: 0 16px 40px color-mix(in srgb, var(--cp) 40%, transparent);
    animation: arena-score-burst .55s ease-out;
}

.arena-fs-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    min-height: 2.5rem;
    padding: .45rem .75rem;
    border-radius: .75rem;
    border: 1px solid rgba(255,255,255,.2);
    background: rgba(255,255,255,.1);
    color: #f1f5f9;
    font-size: .75rem;
    font-weight: 800;
    cursor: pointer;
    transition: background .15s ease, border-color .15s ease;
}
.arena-fs-btn:hover {
    background: rgba(255,255,255,.18);
    border-color: rgba(255,255,255,.35);
}
.arena-fs-btn svg { width: 1rem; height: 1rem; flex-shrink: 0; }

.arena-play-shell:fullscreen,
.arena-play-shell:-webkit-full-screen,
.arena-live-stage:fullscreen,
.arena-live-stage:-webkit-full-screen,
.arena-play-shell.arena-is-fullscreen,
.arena-live-stage.arena-is-fullscreen,
[data-arena-focus-target].arena-is-fullscreen {
    border-radius: 0 !important;
    width: 100%;
    height: 100%;
    min-height: 100%;
    max-width: none;
    overflow: auto;
    padding: 1.5rem clamp(1rem, 4vw, 3rem);
    box-sizing: border-box;
}
/* CSS immersive — andalan Android WebView (Fullscreen API sering tidak tersedia) */
.arena-play-shell.arena-is-fullscreen,
.arena-live-stage.arena-is-fullscreen,
[data-arena-focus-target].arena-is-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: inherit;
}
body.arena-focus-active {
    overflow: hidden;
}
body.arena-focus-active #sidebar,
body.arena-focus-active aside.sidebar,
body.arena-focus-active .sidebar {
    visibility: hidden !important;
    pointer-events: none !important;
}
.arena-play-shell:fullscreen .arena-fs-stack,
.arena-play-shell.arena-is-fullscreen .arena-fs-stack,
.arena-live-stage:fullscreen .arena-fs-stack,
.arena-live-stage.arena-is-fullscreen .arena-fs-stack,
[data-arena-focus-target].arena-is-fullscreen .arena-fs-stack {
    max-width: 42rem;
    margin-inline: auto;
}
</style>
