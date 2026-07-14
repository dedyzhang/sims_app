{{-- Shared Arena Belajar game-stage styles --}}
<style>
[x-cloak]{display:none!important}

.arena-stage {
    --arena-ink: #0c1a24;
    --arena-ink-2: #152836;
    --arena-mint: color-mix(in srgb, var(--cp) 88%, #f0fdf4);
    --arena-warn: #f59e0b;
    --arena-ok: #34d399;
    --arena-bad: #fb7185;
    position: relative;
}

.arena-hero {
    background:
        radial-gradient(ellipse 80% 60% at 20% 0%, color-mix(in srgb, var(--cp) 45%, transparent), transparent 60%),
        radial-gradient(ellipse 70% 50% at 90% 20%, color-mix(in srgb, var(--cps, var(--cp)) 30%, transparent), transparent 55%),
        linear-gradient(155deg, #0c1a24 0%, #152836 48%, #0f2430 100%);
    color: #f8fafc;
    border-radius: 1.25rem;
    overflow: hidden;
    position: relative;
}
.arena-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size: 28px 28px;
    mask-image: linear-gradient(180deg, rgba(0,0,0,.5), transparent 80%);
    pointer-events: none;
}

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
    background:
        radial-gradient(ellipse 90% 70% at 50% -10%, color-mix(in srgb, var(--cp) 35%, transparent), transparent 55%),
        linear-gradient(180deg, #0c1a24 0%, #101f2a 40%, #0b1520 100%);
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
    border-radius: .65rem;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    background: rgba(255,255,255,.1);
    color: #e2e8f0;
}

.arena-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    min-height: 3.1rem;
    padding: .75rem 1.25rem;
    border-radius: 1rem;
    font-weight: 800;
    font-size: .9rem;
    color: #0c1a24;
    background: linear-gradient(135deg, #fff 0%, color-mix(in srgb, var(--cp) 25%, white) 100%);
    border: none;
    transition: transform .15s ease, filter .15s ease;
}
.arena-cta:hover { transform: translateY(-1px); filter: brightness(1.05); }
.arena-cta-ghost {
    background: transparent;
    color: #e2e8f0;
    border: 1.5px solid rgba(255,255,255,.2);
}

.arena-card-game {
    border-radius: 1.15rem;
    border: 1px solid color-mix(in srgb, var(--cp) 18%, transparent);
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--cp) 8%, white), white);
    transition: transform .18s ease, border-color .18s ease;
}
.dark .arena-card-game {
    background: linear-gradient(180deg, color-mix(in srgb, var(--cp) 14%, #0f172a), #0f172a);
    border-color: color-mix(in srgb, var(--cp) 28%, transparent);
}
.arena-card-game:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--cp) 55%, transparent);
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
.arena-live-stage.arena-is-fullscreen {
    border-radius: 0 !important;
    width: 100%;
    height: 100%;
    min-height: 100%;
    max-width: none;
    overflow: auto;
    padding: 1.5rem clamp(1rem, 4vw, 3rem);
    box-sizing: border-box;
}
.arena-play-shell.arena-is-fullscreen,
.arena-live-stage.arena-is-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 9999;
}
.arena-play-shell:fullscreen .arena-fs-stack,
.arena-play-shell.arena-is-fullscreen .arena-fs-stack,
.arena-live-stage:fullscreen .arena-fs-stack,
.arena-live-stage.arena-is-fullscreen .arena-fs-stack {
    max-width: 42rem;
    margin-inline: auto;
}
</style>
