@extends('layouts.app')
@section('title', 'Asisten Guru')

@section('content')
<style>
    /*
     * Generator Soal | Hasil | History.
     * Desktop: tinggi baris = form Generator (tanpa scroll form).
     * Hasil & History mengisi sel sampai bawah (absolute shell) + scroll internal.
     */
    .ai-teacher-tools-grid {
        align-items: stretch;
    }
    .ai-teacher-form-card {
        display: block;
        height: auto;
        min-height: 0;
        max-height: none;
        overflow: visible;
    }
    .ai-teacher-form-scroll {
        overflow: visible;
        min-height: 0;
        height: auto;
        max-height: none;
    }
    .ai-teacher-hasil,
    .ai-teacher-history {
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }
    .ai-teacher-hasil__toolbar { flex: 0 0 auto; z-index: 2; }
    .ai-teacher-hasil__body {
        flex: 1 1 0%;
        min-height: 0;
        overflow-x: auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: auto;
    }
    .ai-teacher-hasil__empty {
        box-sizing: border-box;
        min-height: 100%;
        display: grid;
        place-items: center;
        padding: 2.5rem 1rem;
    }
    .ai-teacher-hasil__body .quiz-preview-scroll,
    .ai-teacher-hasil__body .ai-answer {
        overflow: visible;
        max-height: none;
        min-height: 0;
    }
    .ai-teacher-history-body {
        flex: 1 1 0%;
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: auto;
    }
    /* ≥xl: form | hasil sejajar — hasil samakan tinggi form */
    @media (min-width: 1280px) {
        .ai-teacher-hasil {
            position: relative;
            align-self: stretch;
            min-height: 0;
            height: auto;
        }
        .ai-teacher-hasil > .ai-teacher-col-shell {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
        /* History full-width di bawah (xl): tinggi nyaman + scroll */
        .ai-teacher-history {
            min-height: min(40vh, 360px);
            max-height: min(50vh, 480px);
        }
        .ai-teacher-history > .ai-teacher-col-shell {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
            overflow: hidden;
        }
    }
    /* ≥2xl: tiga kolom sejajar — hasil & history samakan tinggi form sampai bawah */
    @media (min-width: 1536px) {
        .ai-teacher-hasil,
        .ai-teacher-history {
            position: relative;
            align-self: stretch;
            height: auto;
            min-height: 0;
            max-height: none;
        }
        .ai-teacher-hasil > .ai-teacher-col-shell,
        .ai-teacher-history > .ai-teacher-col-shell {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
    }
    .nalar-answer {
        line-height: 1.7;
        white-space: pre-wrap;
        word-break: break-word;
        white-space: normal;
    }

    /* ── AI Console — clean white, match SIMS theme ── */
    .ai-console {
        --c-lime: #059669;
        --c-rose: #e11d48;
        --c-line: color-mix(in srgb, var(--cp) 14%, #e2e8f0);
        --c-panel: #ffffff;
        position: relative;
        overflow: hidden;
        border-radius: 22px;
        border: 1.5px solid var(--c-line);
        background:
            radial-gradient(900px 280px at 0% 0%, color-mix(in srgb, var(--cp) 10%, transparent), transparent 55%),
            radial-gradient(700px 260px at 100% 0%, color-mix(in srgb, var(--cps, var(--cp)) 8%, transparent), transparent 50%),
            linear-gradient(165deg, #ffffff 0%, color-mix(in srgb, var(--cp) 4%, #f8fafc) 55%, #ffffff 100%);
        box-shadow: 0 4px 18px -10px rgba(15, 23, 42, 0.08);
        color: #334155;
    }
    .ai-console::before {
        content: "";
        pointer-events: none;
        position: absolute;
        inset: 0;
        opacity: 0.45;
        background-image:
            linear-gradient(color-mix(in srgb, var(--cp) 6%, transparent) 1px, transparent 1px),
            linear-gradient(90deg, color-mix(in srgb, var(--cp) 6%, transparent) 1px, transparent 1px);
        background-size: 28px 28px;
        mask-image: linear-gradient(180deg, #000 0%, transparent 88%);
    }
    .ai-console__sheen {
        pointer-events: none;
        position: absolute;
        top: -35%;
        right: -8%;
        width: 50%;
        height: 80%;
        background: radial-gradient(ellipse at center, color-mix(in srgb, var(--cp) 12%, transparent), transparent 70%);
        filter: blur(24px);
    }
    .ai-console__body {
        position: relative;
        z-index: 1;
        padding: 1.15rem 1.2rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .ai-console__top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }
    .ai-console__brand {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 0;
    }
    .ai-console__orb {
        position: relative;
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: linear-gradient(145deg, var(--cp), color-mix(in srgb, var(--cps, var(--cp)) 55%, var(--cp)));
        border: 1px solid color-mix(in srgb, var(--cp) 30%, transparent);
        box-shadow: 0 8px 20px -10px color-mix(in srgb, var(--cp) 55%, transparent);
        color: #fff;
        flex-shrink: 0;
    }
    .ai-console__orb::after {
        content: "";
        position: absolute;
        inset: -4px;
        border-radius: 16px;
        border: 1px solid color-mix(in srgb, var(--cp) 22%, transparent);
        animation: ai-orb-ring 2.8s ease-in-out infinite;
    }
    @keyframes ai-orb-ring {
        0%, 100% { opacity: 0.3; transform: scale(1); }
        50% { opacity: 0.85; transform: scale(1.05); }
    }
    .ai-console__kicker {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: color-mix(in srgb, var(--cp) 72%, #64748b);
        margin: 0 0 0.15rem;
    }
    .ai-console__title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #0f172a;
        line-height: 1.2;
    }
    .ai-console__sub {
        margin: 0.2rem 0 0;
        font-size: 12px;
        color: #64748b;
        line-height: 1.4;
    }
    .ai-console__status-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
    }
    .ai-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        height: 28px;
        padding: 0 0.7rem;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .ai-pill--live {
        color: #047857;
        background: #ecfdf5;
        border-color: #a7f3d0;
    }
    .ai-pill--dim {
        color: #64748b;
        background: #f8fafc;
        border-color: #e2e8f0;
    }
    .ai-pill--fault {
        color: #be123c;
        background: #fff1f2;
        border-color: #fecdd3;
    }
    .ai-pill__dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: currentColor;
    }
    .ai-pill__dot--pulse { animation: ai-dot-pulse 1.5s ease-in-out infinite; }
    @keyframes ai-dot-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.35; }
    }
    .ai-console__grid {
        display: grid;
        gap: 0.75rem;
    }
    @media (min-width: 768px) {
        .ai-console__grid {
            /* Status key | Kuota | API Key / AI Studio */
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, 1.15fr);
        }
    }
    .ai-tile {
        position: relative;
        border-radius: 16px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 10%, #e2e8f0);
        background: var(--c-panel);
        padding: 0.95rem 1rem;
        min-height: 118px;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    }
    .ai-tile::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--cp) 55%, transparent), transparent);
        opacity: 0.85;
    }
    .ai-tile__label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: color-mix(in srgb, var(--cp) 40%, #64748b);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .ai-tile__row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        flex: 1;
        min-height: 0;
    }
    .ai-ring {
        position: relative;
        width: 64px;
        height: 64px;
        flex-shrink: 0;
    }
    .ai-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
    .ai-ring__track { fill: none; stroke: color-mix(in srgb, var(--cp) 12%, #e2e8f0); stroke-width: 5; }
    .ai-ring__fill {
        fill: none;
        stroke: var(--cp);
        stroke-width: 5;
        stroke-linecap: round;
        transition: stroke-dashoffset 0.5s ease;
    }
    .ai-ring__center {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12px;
        font-weight: 800;
        color: color-mix(in srgb, var(--cp) 85%, #0f172a);
    }
    .ai-tile__value {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: clamp(1.35rem, 2.4vw, 1.7rem);
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.1;
        color: #0f172a;
    }
    .ai-tile__hint {
        margin: 0.2rem 0 0;
        font-size: 11px;
        color: #94a3b8;
        line-height: 1.35;
    }
    .ai-tile__meta {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        margin-top: auto;
        font-size: 11px;
        color: #64748b;
    }
    .ai-tile__meta strong {
        color: #0f172a;
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .ai-tile__meta-row {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .ai-bar {
        height: 4px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--cp) 8%, #f1f5f9);
        border: 1px solid color-mix(in srgb, var(--cp) 10%, #e2e8f0);
        overflow: hidden;
        margin-top: auto;
    }
    .ai-bar__fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--cp), color-mix(in srgb, var(--cps, var(--cp)) 50%, var(--cp)));
        transition: width 0.4s ease;
    }
    .ai-usage {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        flex: 1;
        min-height: 0;
    }
    .ai-usage__row {
        display: flex;
        flex-direction: column;
        gap: 0.28rem;
    }
    .ai-usage__head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .ai-usage__name {
        font-size: 12px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.01em;
    }
    .ai-usage__nums {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        white-space: nowrap;
    }
    .ai-usage__nums strong { color: #0f172a; }
    .ai-usage__hint {
        margin: 0;
        font-size: 10px;
        color: #94a3b8;
        line-height: 1.3;
    }
    .ai-usage__bar {
        height: 6px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--cp) 8%, #f1f5f9);
        border: 1px solid color-mix(in srgb, var(--cp) 10%, #e2e8f0);
        overflow: hidden;
    }
    .ai-usage__fill {
        height: 100%;
        border-radius: inherit;
        transition: width 0.45s ease;
    }
    .ai-usage__fill--studio {
        background: linear-gradient(90deg, var(--cp), color-mix(in srgb, var(--cps, var(--cp)) 40%, var(--cp)));
    }
    .ai-usage__fill--school {
        background: linear-gradient(90deg, #0ea5e9, #6366f1);
    }
    .dark .ai-usage__name,
    .dark .ai-usage__nums strong { color: #f8fafc; }
    .dark .ai-usage__bar {
        background: #0f172a;
        border-color: #334155;
    }
    .ai-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        height: 34px;
        padding: 0 0.8rem;
        border-radius: 10px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 22%, #e2e8f0);
        background: color-mix(in srgb, var(--cp) 6%, #fff);
        color: color-mix(in srgb, var(--cp) 78%, #0f172a);
        font-size: 12px;
        font-weight: 700;
        transition: background .15s, border-color .15s, box-shadow .15s, transform .1s;
        cursor: pointer;
    }
    .ai-btn:hover {
        background: color-mix(in srgb, var(--cp) 12%, #fff);
        border-color: color-mix(in srgb, var(--cp) 40%, #cbd5e1);
    }
    .ai-btn:active { transform: scale(0.98); }
    .ai-btn:disabled { opacity: 0.4; cursor: not-allowed; box-shadow: none; }
    .ai-btn--ghost {
        border-color: #e2e8f0;
        background: #fff;
        color: #475569;
    }
    .ai-btn--ghost:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        box-shadow: none;
    }
    .ai-btn--danger {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }
    .ai-btn--danger:hover {
        background: #ffe4e6;
        border-color: #fda4af;
    }
    .ai-btn--solid {
        border-color: transparent;
        background: var(--cp);
        color: #fff;
        box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--cp) 55%, transparent);
    }
    .ai-btn--solid:hover {
        filter: brightness(1.05);
        border-color: transparent;
        box-shadow: 0 10px 22px -8px color-mix(in srgb, var(--cp) 50%, transparent);
    }
    .ai-btn--icon {
        width: 34px;
        padding: 0;
    }
    .ai-input {
        width: 100%;
        height: 40px;
        border-radius: 12px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #cbd5e1);
        background: #fff;
        padding: 0 0.75rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12px;
        color: #0f172a;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .ai-input:focus {
        border-color: var(--cp);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--cp) 14%, transparent);
    }
    .ai-input::placeholder { color: #94a3b8; }
    .ai-console__keyform {
        border-radius: 14px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        background: color-mix(in srgb, var(--cp) 3%, #fff);
        padding: 0.85rem 1rem;
        display: grid;
        gap: 0.65rem;
    }
    @media (min-width: 640px) {
        .ai-console__keyform {
            grid-template-columns: 1fr auto;
            align-items: end;
        }
        .ai-console__keyform-actions {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
    }
    .ai-console__warn {
        margin: 0;
        font-size: 11px;
        color: #b45309;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .ai-console__msg-ok {
        margin: 0;
        font-size: 11px;
        color: var(--c-lime);
        font-weight: 600;
    }
    .ai-console__msg-err {
        margin: 0;
        font-size: 11px;
        color: var(--c-rose);
        font-weight: 600;
    }

    /* Masthead + tabs */
    .ai-masthead {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }
    .ai-masthead__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.65rem;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: color-mix(in srgb, var(--cp) 80%, #0f172a);
        background: color-mix(in srgb, var(--cp) 10%, #fff);
        border: 1px solid color-mix(in srgb, var(--cp) 22%, #e2e8f0);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .ai-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.35rem;
        border-radius: 16px;
        background: color-mix(in srgb, var(--cp) 4%, #fff);
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        box-shadow: 0 2px 10px -8px rgba(15, 23, 42, 0.08);
    }
    .ai-tabs__btn {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        border-radius: 12px;
        border: 1px solid transparent;
        padding: 0.55rem 0.95rem;
        font-size: 13px;
        font-weight: 700;
        color: #64748b;
        background: transparent;
        transition: color .15s, background .15s, border-color .15s, box-shadow .15s;
    }
    .ai-tabs__btn:hover {
        color: #0f172a;
        background: color-mix(in srgb, var(--cp) 8%, #fff);
    }
    .ai-tabs__btn.is-active {
        color: #fff;
        background: var(--cp);
        border-color: transparent;
        box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--cp) 55%, transparent);
    }

    /* Nalar chat shell — clean light */
    .ai-chat {
        border-radius: 22px;
        overflow: hidden;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        background: #fff;
        box-shadow: 0 4px 18px -10px rgba(15, 23, 42, 0.08);
        display: flex;
        flex-direction: column;
        min-height: min(70vh, 720px);
    }
    .ai-chat__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.9rem 1.1rem;
        border-bottom: 1px solid color-mix(in srgb, var(--cp) 10%, #e2e8f0);
        background: linear-gradient(115deg, color-mix(in srgb, var(--cp) 10%, #fff) 0%, #fff 55%, color-mix(in srgb, var(--cps, var(--cp)) 8%, #fff) 100%);
    }
    .ai-chat__body {
        flex: 1;
        overflow-y: auto;
        padding: 1.15rem 1.1rem;
        background:
            radial-gradient(ellipse 70% 40% at 10% 0%, color-mix(in srgb, var(--cp) 8%, transparent), transparent 55%),
            radial-gradient(ellipse 50% 35% at 90% 100%, color-mix(in srgb, var(--cps, var(--cp)) 8%, transparent), transparent 50%),
            color-mix(in srgb, var(--cp) 2.5%, #f8fafc);
    }
    .ai-chat__composer {
        border-top: 1px solid color-mix(in srgb, var(--cp) 10%, #e2e8f0);
        padding: 0.75rem;
        background: #fff;
    }
    .ai-chat__composer-inner {
        display: flex;
        gap: 0.5rem;
        align-items: end;
        border-radius: 16px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 16%, #e2e8f0);
        background: color-mix(in srgb, var(--cp) 3%, #fff);
        padding: 0.45rem;
        transition: border-color .15s, box-shadow .15s;
    }
    .ai-chat__composer-inner:focus-within {
        border-color: var(--cp);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--cp) 12%, transparent);
    }
    .ai-chat__ta {
        flex: 1;
        resize: none;
        background: transparent;
        border: 0;
        outline: none;
        box-shadow: none;
        font-size: 14px;
        padding: 0.55rem 0.65rem;
        color: #0f172a;
        min-height: 48px;
    }
    .ai-chat__ta::placeholder { color: #94a3b8; }
    .ai-chat__bubble-user {
        max-width: 92%;
        background: var(--cp);
        color: #fff;
        border-radius: 18px 18px 6px 18px;
        box-shadow: 0 8px 20px -12px color-mix(in srgb, var(--cp) 50%, transparent);
    }
    .ai-chat__bubble-ai {
        max-width: 92%;
        background: #fff;
        color: #1e293b;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        border-radius: 18px 18px 18px 6px;
        box-shadow: 0 2px 10px -8px rgba(15, 23, 42, 0.08);
    }
    @media (min-width: 640px) {
        .ai-chat__bubble-user,
        .ai-chat__bubble-ai { max-width: 80%; }
        .ai-chat__bubble-ai.ai-chat__bubble-ai--wide { max-width: 48rem; width: 100%; }
    }
    .ai-chat__suggest {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        width: 100%;
        text-align: left;
        border-radius: 14px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        background: #fff;
        padding: 0.75rem 0.9rem;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        transition: border-color .15s, background .15s, color .15s, box-shadow .15s;
    }
    .ai-chat__suggest:hover {
        border-color: color-mix(in srgb, var(--cp) 40%, #cbd5e1);
        background: color-mix(in srgb, var(--cp) 6%, #fff);
        color: color-mix(in srgb, var(--cp) 80%, #0f172a);
        box-shadow: 0 4px 14px -10px color-mix(in srgb, var(--cp) 35%, transparent);
    }
    .ai-chat__icon-soft {
        background: color-mix(in srgb, var(--cp) 12%, #fff);
        color: var(--cp);
        border: 1px solid color-mix(in srgb, var(--cp) 18%, transparent);
    }

    .dark .ai-console {
        background: linear-gradient(165deg, #1e293b 0%, #0f172a 100%);
        border-color: #334155;
        color: #e2e8f0;
        box-shadow: none;
    }
    .dark .ai-console__title,
    .dark .ai-tile__value,
    .dark .ai-tile__meta strong { color: #f8fafc; }
    .dark .ai-tile {
        background: #1e293b;
        border-color: #334155;
    }
    .dark .ai-tabs {
        background: #1e293b;
        border-color: #334155;
    }
    .dark .ai-tabs__btn { color: #94a3b8; }
    .dark .ai-tabs__btn:hover { color: #f1f5f9; background: #334155; }
    .dark .ai-chat {
        background: #1e293b;
        border-color: #334155;
    }
    .dark .ai-chat__head {
        background: linear-gradient(115deg, color-mix(in srgb, var(--cp) 18%, #1e293b), #1e293b);
        border-color: #334155;
    }
    .dark .ai-chat__body {
        background: #0f172a;
    }
    .dark .ai-chat__bubble-ai {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }
    .dark .ai-chat__composer { background: #1e293b; border-color: #334155; }
    .dark .ai-chat__composer-inner {
        background: #0f172a;
        border-color: #334155;
    }
    .dark .ai-chat__ta { color: #e2e8f0; }
    .dark .ai-chat__suggest {
        background: #0f172a;
        border-color: #334155;
        color: #cbd5e1;
    }
    .dark .ai-input {
        background: #0f172a;
        border-color: #334155;
        color: #e2e8f0;
    }
    .dark .ai-btn--ghost {
        background: transparent;
        border-color: #475569;
        color: #cbd5e1;
    }

    /* Mobile/stack: Hasil & History tinggi nyaman + scroll sendiri */
    @media (max-width: 1279px) {
        .ai-teacher-form-card {
            height: auto;
            overflow: visible;
        }
        .ai-teacher-hasil,
        .ai-teacher-history {
            height: auto;
            min-height: min(55vh, 480px);
            max-height: min(70vh, 720px);
        }
        .ai-teacher-hasil > .ai-teacher-col-shell,
        .ai-teacher-history > .ai-teacher-col-shell {
            display: flex;
            flex-direction: column;
            min-height: min(55vh, 480px);
            max-height: min(70vh, 720px);
            height: 100%;
            overflow: hidden;
        }
        .ai-console__keyform-actions {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
    }
    .ai-console__warn {
        margin: 0;
        font-size: 11px;
        color: #b45309;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .ai-console__msg-ok {
        margin: 0;
        font-size: 11px;
        color: var(--c-lime);
        font-weight: 600;
    }
    .ai-console__msg-err {
        margin: 0;
        font-size: 11px;
        color: var(--c-rose);
        font-weight: 600;
    }

    /* Masthead + tabs */
    .ai-masthead {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
    }
    .ai-masthead__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.65rem;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: color-mix(in srgb, var(--cp) 80%, #0f172a);
        background: color-mix(in srgb, var(--cp) 10%, #fff);
        border: 1px solid color-mix(in srgb, var(--cp) 22%, #e2e8f0);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .ai-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.35rem;
        border-radius: 16px;
        background: color-mix(in srgb, var(--cp) 4%, #fff);
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        box-shadow: 0 2px 10px -8px rgba(15, 23, 42, 0.08);
    }
    .ai-tabs__btn {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        border-radius: 12px;
        border: 1px solid transparent;
        padding: 0.55rem 0.95rem;
        font-size: 13px;
        font-weight: 700;
        color: #64748b;
        background: transparent;
        transition: color .15s, background .15s, border-color .15s, box-shadow .15s;
    }
    .ai-tabs__btn:hover {
        color: #0f172a;
        background: color-mix(in srgb, var(--cp) 8%, #fff);
    }
    .ai-tabs__btn.is-active {
        color: #fff;
        background: var(--cp);
        border-color: transparent;
        box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--cp) 55%, transparent);
    }

    /* Nalar chat shell — clean light */
    .ai-chat {
        border-radius: 22px;
        overflow: hidden;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        background: #fff;
        box-shadow: 0 4px 18px -10px rgba(15, 23, 42, 0.08);
        display: flex;
        flex-direction: column;
        min-height: min(70vh, 720px);
    }
    .ai-chat__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.9rem 1.1rem;
        border-bottom: 1px solid color-mix(in srgb, var(--cp) 10%, #e2e8f0);
        background: linear-gradient(115deg, color-mix(in srgb, var(--cp) 10%, #fff) 0%, #fff 55%, color-mix(in srgb, var(--cps, var(--cp)) 8%, #fff) 100%);
    }
    .ai-chat__body {
        flex: 1;
        overflow-y: auto;
        padding: 1.15rem 1.1rem;
        background:
            radial-gradient(ellipse 70% 40% at 10% 0%, color-mix(in srgb, var(--cp) 8%, transparent), transparent 55%),
            radial-gradient(ellipse 50% 35% at 90% 100%, color-mix(in srgb, var(--cps, var(--cp)) 8%, transparent), transparent 50%),
            color-mix(in srgb, var(--cp) 2.5%, #f8fafc);
    }
    .ai-chat__composer {
        border-top: 1px solid color-mix(in srgb, var(--cp) 10%, #e2e8f0);
        padding: 0.75rem;
        background: #fff;
    }
    .ai-chat__composer-inner {
        display: flex;
        gap: 0.5rem;
        align-items: end;
        border-radius: 16px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 16%, #e2e8f0);
        background: color-mix(in srgb, var(--cp) 3%, #fff);
        padding: 0.45rem;
        transition: border-color .15s, box-shadow .15s;
    }
    .ai-chat__composer-inner:focus-within {
        border-color: var(--cp);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--cp) 12%, transparent);
    }
    .ai-chat__ta {
        flex: 1;
        resize: none;
        background: transparent;
        border: 0;
        outline: none;
        box-shadow: none;
        font-size: 14px;
        padding: 0.55rem 0.65rem;
        color: #0f172a;
        min-height: 48px;
    }
    .ai-chat__ta::placeholder { color: #94a3b8; }
    .ai-chat__bubble-user {
        max-width: 92%;
        background: var(--cp);
        color: #fff;
        border-radius: 18px 18px 6px 18px;
        box-shadow: 0 8px 20px -12px color-mix(in srgb, var(--cp) 50%, transparent);
    }
    .ai-chat__bubble-ai {
        max-width: 92%;
        background: #fff;
        color: #1e293b;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        border-radius: 18px 18px 18px 6px;
        box-shadow: 0 2px 10px -8px rgba(15, 23, 42, 0.08);
    }
    @media (min-width: 640px) {
        .ai-chat__bubble-user,
        .ai-chat__bubble-ai { max-width: 80%; }
        .ai-chat__bubble-ai.ai-chat__bubble-ai--wide { max-width: 48rem; width: 100%; }
    }
    .ai-chat__suggest {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        width: 100%;
        text-align: left;
        border-radius: 14px;
        border: 1.5px solid color-mix(in srgb, var(--cp) 12%, #e2e8f0);
        background: #fff;
        padding: 0.75rem 0.9rem;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        transition: border-color .15s, background .15s, color .15s, box-shadow .15s;
    }
    .ai-chat__suggest:hover {
        border-color: color-mix(in srgb, var(--cp) 40%, #cbd5e1);
        background: color-mix(in srgb, var(--cp) 6%, #fff);
        color: color-mix(in srgb, var(--cp) 80%, #0f172a);
        box-shadow: 0 4px 14px -10px color-mix(in srgb, var(--cp) 35%, transparent);
    }
    .ai-chat__icon-soft {
        background: color-mix(in srgb, var(--cp) 12%, #fff);
        color: var(--cp);
        border: 1px solid color-mix(in srgb, var(--cp) 18%, transparent);
    }

    .dark .ai-console {
        background: linear-gradient(165deg, #1e293b 0%, #0f172a 100%);
        border-color: #334155;
        color: #e2e8f0;
        box-shadow: none;
    }
    .dark .ai-console__title,
    .dark .ai-tile__value,
    .dark .ai-tile__meta strong { color: #f8fafc; }
    .dark .ai-tile {
        background: #1e293b;
        border-color: #334155;
    }
    .dark .ai-tabs {
        background: #1e293b;
        border-color: #334155;
    }
    .dark .ai-tabs__btn { color: #94a3b8; }
    .dark .ai-tabs__btn:hover { color: #f1f5f9; background: #334155; }
    .dark .ai-chat {
        background: #1e293b;
        border-color: #334155;
    }
    .dark .ai-chat__head {
        background: linear-gradient(115deg, color-mix(in srgb, var(--cp) 18%, #1e293b), #1e293b);
        border-color: #334155;
    }
    .dark .ai-chat__body {
        background: #0f172a;
    }
    .dark .ai-chat__bubble-ai {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }
    .dark .ai-chat__composer { background: #1e293b; border-color: #334155; }
    .dark .ai-chat__composer-inner {
        background: #0f172a;
        border-color: #334155;
    }
    .dark .ai-chat__ta { color: #e2e8f0; }
    .dark .ai-chat__suggest {
        background: #0f172a;
        border-color: #334155;
        color: #cbd5e1;
    }
    .dark .ai-input {
        background: #0f172a;
        border-color: #334155;
        color: #e2e8f0;
    }
    .dark .ai-btn--ghost {
        background: transparent;
        border-color: #475569;
        color: #cbd5e1;
    }

    /* Mobile/stack: 1 kolom, tidak overflow frame, scroll internal */
    @media (max-width: 1279px) {
        .ai-teacher-tools-grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 0.85rem;
        }
        .ai-teacher-form-card {
            height: auto;
            overflow: visible;
            padding: 1rem;
        }
        .ai-teacher-hasil,
        .ai-teacher-history {
            height: auto;
            min-height: min(50vh, 420px);
            max-height: min(68vh, 640px);
            width: 100%;
        }
        .ai-teacher-hasil > .ai-teacher-col-shell,
        .ai-teacher-history > .ai-teacher-col-shell {
            display: flex;
            flex-direction: column;
            min-height: min(50vh, 420px);
            max-height: min(68vh, 640px);
            height: 100%;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        .ai-teacher-hasil > .ai-teacher-col-shell {
            padding: 0.85rem;
        }
        /* Di mobile history full-width → pakai judul lengkap */
        .ai-teacher-history__title-full { display: inline !important; }
        .ai-teacher-history__title-short { display: none !important; }
        .ai-teacher-history__meta-label { display: inline !important; }
        .ai-teacher-history__toggle {
            padding: 0.75rem 1rem;
        }
        .ai-tabs {
            max-width: 100%;
            overflow-x: auto;
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        .ai-tabs__btn {
            flex: 0 0 auto;
            padding: 0.5rem 0.75rem;
            font-size: 12px;
            white-space: nowrap;
        }
        .ai-console__body {
            padding: 0.9rem;
        }
        .ai-console__title {
            font-size: 0.98rem;
        }
        .ai-console__sub {
            font-size: 11px;
            overflow-wrap: anywhere;
        }
        .ai-btn {
            max-width: 100%;
        }
        .ai-usage__head {
            flex-wrap: wrap;
            gap: 0.15rem 0.5rem;
        }
        .ai-usage__nums {
            font-size: 10px;
        }
        .ai-usage__hint {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    }
    @media (max-width: 639px) {
        .ai-teacher-form-card {
            padding: 0.85rem;
        }
        .ai-teacher-hasil,
        .ai-teacher-history {
            min-height: min(48vh, 380px);
            max-height: min(62vh, 560px);
        }
        .ai-teacher-hasil > .ai-teacher-col-shell,
        .ai-teacher-history > .ai-teacher-col-shell {
            min-height: min(48vh, 380px);
            max-height: min(62vh, 560px);
        }
        .ai-teacher-history-body {
            padding-left: 0.65rem;
            padding-right: 0.65rem;
        }
        /* Tombol form generator: full width stack di HP sempit */
        .ai-teacher-form-scroll .ai-btn {
            justify-content: center;
        }
        .ai-teacher-form-scroll .grid.grid-cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .ai-teacher-form-scroll .grid.grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    /* Cegah overflow horizontal di seluruh modul Asisten Guru */
    .ai-teacher-page {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: clip;
    }
    .ai-teacher-page *,
    .ai-teacher-page *::before,
    .ai-teacher-page *::after {
        box-sizing: border-box;
    }
    .ai-teacher-page img,
    .ai-teacher-page video,
    .ai-teacher-page canvas {
        max-width: 100%;
        height: auto;
    }
    .ai-teacher-page pre,
    .ai-teacher-page code {
        max-width: 100%;
        overflow-x: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
<div class="ai-teacher-page space-y-5 relative min-w-0 max-w-full w-full" x-data="teacherAi()">

    @if(session('error'))
    <div class="rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-900/30 dark:border-rose-800 px-4 py-3 text-sm font-semibold text-rose-800 dark:text-rose-200">
        {{ session('error') }}
    </div>
    @endif
    @if(session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/30 dark:border-emerald-800 px-4 py-3 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
        {{ session('success') }}
    </div>
    @endif

    {{-- Gate: wajib API key Gemini pribadi --}}
    <template x-if="needsApiKeySetup">
        <div class="fixed inset-0 z-[80] grid place-items-center bg-slate-900/55 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg card p-6 space-y-4 shadow-xl" @click.stop>
                <div class="flex items-start gap-3">
                    <span class="grid place-items-center w-11 h-11 rounded-2xl bg-primary text-white shrink-0">
                        <i data-lucide="key-round" class="w-5 h-5"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-extrabold text-slate-800 dark:text-slate-100 text-lg">Hubungkan API key Gemini</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            Generate di SIMS memakai API key akun Google Anda.
                            SIMS tidak membuat key otomatis — buat sekali di Google AI Studio, lalu tempel di sini.
                        </p>
                    </div>
                </div>
                <ol class="list-decimal pl-5 text-xs text-slate-600 dark:text-slate-300 space-y-1.5 leading-relaxed">
                    <li>Buka Google AI Studio → Create API key</li>
                    <li>Salin key, tempel di bawah, lalu simpan</li>
                </ol>
                <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer"
                   class="btn-primary w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold min-h-[48px]">
                    <i data-lucide="external-link" class="w-4 h-4"></i> Buka Google AI Studio
                </a>
                <div>
                    <label class="form-label">Tempel API key <span class="text-rose-500">*</span></label>
                    <input type="password" x-model="apiKeyInput" x-ref="apiKeyGateInput"
                           class="form-input font-mono text-sm" placeholder="AIza…" autocomplete="off"
                           @keydown.enter.prevent="saveGeminiApiKey()">
                </div>
                <p class="text-xs text-rose-500 font-semibold" x-show="apiKeyError" x-cloak x-text="apiKeyError"></p>
                <button type="button" @click="saveGeminiApiKey" :disabled="apiKeySaving || !(apiKeyInput || '').trim()"
                        class="btn-primary w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold min-h-[48px] disabled:opacity-40">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span x-text="apiKeySaving ? 'Memvalidasi & menyimpan…' : 'Simpan API key'"></span>
                </button>
            </div>
        </div>
    </template>

    <div class="space-y-5" :class="needsApiKeySetup ? 'pointer-events-none select-none opacity-40 blur-[1px]' : ''">
    {{-- Masthead --}}
    <div class="ai-masthead">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                <span class="ai-masthead__badge">
                    <span class="ai-pill__dot ai-pill__dot--pulse" style="background:var(--cp)"></span>
                    AI CORE
                </span>
            </div>
            <h1 class="page-title flex items-center gap-2 m-0">
                <span class="grid place-items-center w-9 h-9 rounded-xl text-white shrink-0 bg-primary"
                      style="box-shadow:0 8px 20px -10px color-mix(in srgb, var(--cp) 55%, transparent)">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </span>
                Asisten Guru
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Nalar Guru · generator soal · RPM · ringkasan · umpan balik</p>
        </div>
    </div>

    {{-- AI Console: kuota + credential --}}
    <div class="ai-console" x-show="!needsApiKeySetup" x-cloak>
        <div class="ai-console__sheen" aria-hidden="true"></div>
        {{-- SVG gradient defs for capacity ring --}}
        <svg width="0" height="0" class="absolute" aria-hidden="true">
            <defs>
                <linearGradient id="aiRingGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="var(--cp)"/>
                    <stop offset="100%" stop-color="var(--cps, var(--cp))"/>
                </linearGradient>
            </defs>
        </svg>

        <div class="ai-console__body">
            <div class="ai-console__top">
                <div class="ai-console__brand">
                    <div class="ai-console__orb" aria-hidden="true">
                        <i data-lucide="cpu" class="w-5 h-5"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="ai-console__kicker">Nalar · runtime</p>
                        <h2 class="ai-console__title">AI Console</h2>
                        <p class="ai-console__sub">Monitor kuota generate &amp; kredensial model</p>
                    </div>
                </div>
                <div class="ai-console__status-row">
                    <template x-if="quota">
                        <span class="ai-pill"
                              :class="quota.key_alive === false ? 'ai-pill--fault' : (quota.live ? 'ai-pill--live' : 'ai-pill--dim')">
                            <span class="ai-pill__dot"
                                  :class="quota.live && quota.key_alive !== false && 'ai-pill__dot--pulse'"></span>
                            <span x-text="quota.key_alive === false ? 'Key error' : (quota.live ? 'Live' : 'Cached')"></span>
                        </span>
                    </template>
                    <button type="button" class="ai-btn ai-btn--icon" title="Refresh kuota"
                            @click="refreshQuota(true)" :disabled="quotaLoading">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5" :class="quotaLoading && 'animate-spin'"></i>
                    </button>
                </div>
            </div>

            <div class="ai-console__grid" x-show="quota" x-cloak>
                {{-- Kiri: status key / runtime --}}
                <div class="ai-tile">
                    <div class="ai-tile__label">Status key</div>
                    <div class="min-w-0 flex-1">
                        <div class="ai-tile__value" style="font-size:clamp(1rem,2vw,1.25rem);letter-spacing:-0.02em"
                             x-text="quota.status === 'error' || quota.key_alive === false
                                ? (quota.remaining_label || quota.status_label || 'Key Asisten Guru tidak aktif')
                                : (quota.key_alive === true ? 'Key Asisten Guru aktif' : (quota.status_label || 'Status key'))"></div>
                        <p class="ai-tile__hint"
                           x-text="quota.status === 'error' || quota.key_alive === false
                                ? (quota.message || 'Periksa API key di panel kanan')
                                : 'Siap dipakai untuk Nalar dan generate'"></p>
                    </div>
                    <div class="ai-tile__meta" style="margin-top:auto;gap:.45rem">
                        <div class="ai-tile__meta-row">
                            <i data-lucide="activity" class="w-3.5 h-3.5 text-primary"></i>
                            <span>Runtime
                                <strong x-show="quota.key_alive === true" class="text-emerald-600">Online</strong>
                                <strong x-show="quota.key_alive === false" class="text-rose-600">Tidak aktif</strong>
                                <strong x-show="quota.key_alive !== true && quota.key_alive !== false">—</strong>
                            </span>
                        </div>
                        <div class="ai-tile__meta-row" x-show="quota.updated_at_human">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Update <strong x-text="quota.updated_at_human"></strong></span>
                        </div>
                    </div>
                </div>

                {{-- Tengah: kuota dual (AI Studio + AI Sekolah), realtime via poll --}}
                <div class="ai-tile">
                    <div class="flex items-center justify-between gap-2">
                        <div class="ai-tile__label m-0">Kuota tersisa</div>
                        <span class="font-mono text-[10px] font-bold text-slate-400"
                              x-show="quota.updated_at_human"
                              x-text="'live · ' + (quota.updated_at_human || '')"></span>
                    </div>

                    <div class="ai-usage" x-show="quota.studio || quota.school">
                        {{-- AI Studio (key pribadi guru) --}}
                        <div class="ai-usage__row" x-show="quota.studio">
                            <div class="ai-usage__head">
                                <span class="ai-usage__name">AI Studio</span>
                                <span class="ai-usage__nums">
                                    <strong x-text="quota.studio?.used_label ?? '0'"></strong>
                                    <span> / </span>
                                    <span x-text="quota.studio?.limit_label ?? '—'"></span>
                                </span>
                            </div>
                            <div class="ai-usage__bar">
                                <div class="ai-usage__fill ai-usage__fill--studio"
                                     :style="'width:' + Math.max(0, Math.min(100, Number(quota.studio?.percent_used) || 0)) + '%'"></div>
                            </div>
                            <p class="ai-usage__hint"
                               x-text="(quota.studio?.remaining_label || '') + (quota.studio?.hint ? ' · ' + quota.studio.hint : '')"></p>
                        </div>

                        {{-- AI Sekolah (key server) --}}
                        <div class="ai-usage__row" x-show="quota.school">
                            <div class="ai-usage__head">
                                <span class="ai-usage__name">AI Sekolah</span>
                                <span class="ai-usage__nums">
                                    <strong x-text="quota.school?.used_label ?? '0'"></strong>
                                    <span x-show="quota.school?.limit != null"> / </span>
                                    <span x-show="quota.school?.limit != null" x-text="quota.school?.limit_label"></span>
                                    <span x-show="quota.school?.limit == null"> req</span>
                                </span>
                            </div>
                            <div class="ai-usage__bar">
                                <div class="ai-usage__fill ai-usage__fill--school"
                                     :style="'width:' + (quota.school?.percent_used != null
                                        ? Math.max(0, Math.min(100, Number(quota.school.percent_used) || 0))
                                        : Math.min(100, (Number(quota.school?.used) || 0) > 0 ? 35 : 8)) + '%'"></div>
                            </div>
                            <p class="ai-usage__hint"
                               x-text="(quota.school?.remaining_label || '') + (quota.school?.hint ? ' · ' + quota.school.hint : '')"></p>
                        </div>
                    </div>

                    <p class="ai-tile__hint m-0" x-show="quota.usage_window?.reset_human"
                       x-text="'Reset: ' + quota.usage_window.reset_human"></p>
                    <p class="ai-console__warn"
                       x-show="quota.provider !== 'ninerouter' && quota.status && quota.status !== 'ok' && quota.status !== 'error'"
                       x-text="quota.message || 'Status kuota tidak optimal'"></p>
                </div>

                {{-- Kanan: API Key + AI Studio --}}
                <div class="ai-tile">
                    <div class="ai-tile__label">API Key · AI Studio</div>
                    <div class="min-w-0">
                        <div class="ai-tile__value" style="font-size:1rem;letter-spacing:0"
                             x-text="external.has_gemini_api_key ? (external.gemini_api_key_masked || '••••') : 'Belum di-set'"></div>
                        <p class="ai-tile__hint">Key pribadi untuk Nalar &amp; generator</p>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-auto justify-end">
                        <button type="button" class="ai-btn" @click="showReplaceKey = !showReplaceKey">
                            <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                            <span x-text="showReplaceKey ? 'Tutup' : 'Ganti key'"></span>
                        </button>
                        <button type="button" class="ai-btn ai-btn--danger"
                                @click="deleteGeminiApiKey" :disabled="apiKeySaving || !external.has_gemini_api_key">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            Hapus
                        </button>
                        <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer"
                           class="ai-btn ai-btn--ghost">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            AI Studio
                        </a>
                    </div>
                    <p class="ai-console__msg-ok" x-show="externalSaved" x-cloak x-text="externalMessage"></p>
                    <p class="ai-console__msg-err" x-show="apiKeyError && !needsApiKeySetup && !showReplaceKey" x-cloak x-text="apiKeyError"></p>
                </div>
            </div>

            <div x-show="showReplaceKey" x-cloak class="ai-console__keyform">
                <div class="min-w-0 space-y-1.5">
                    <label class="ai-tile__label" for="ai-key-input">Tempel API key baru</label>
                    <input id="ai-key-input" type="password" x-model="apiKeyInput" class="ai-input"
                           placeholder="AIza…" autocomplete="off"
                           @keydown.enter.prevent="saveGeminiApiKey()">
                    <p class="ai-console__msg-err" x-show="apiKeyError" x-cloak x-text="apiKeyError"></p>
                </div>
                <div class="ai-console__keyform-actions flex flex-wrap gap-2 justify-end">
                    <button type="button" class="ai-btn ai-btn--solid"
                            @click="saveGeminiApiKey" :disabled="apiKeySaving || !(apiKeyInput || '').trim()">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        <span x-text="apiKeySaving ? 'Menyimpan…' : 'Simpan'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($launcherAktif))
    <div class="card p-4 space-y-3" x-show="!needsApiKeySetup" x-cloak>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <span class="grid place-items-center w-8 h-8 rounded-xl bg-primary/15 text-primary">
                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                    </span>
                    Nalar Guru
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    Generate di SIMS memakai API key akun Google Anda.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <button type="button" @click="selectTab('gemini')"
                        class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold min-h-[44px]">
                    <i data-lucide="message-circle" class="w-4 h-4"></i> Buka Nalar Guru
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Canva Pendidikan (belajar.id, gratis) --}}
    <div class="card p-4 space-y-3" x-show="!needsApiKeySetup && canva.feature_enabled" x-cloak>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <span class="grid place-items-center w-8 h-8 rounded-xl bg-sky-500/15 text-sky-600">
                        <i data-lucide="palette" class="w-4 h-4"></i>
                    </span>
                    Canva Pendidikan
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    Hubungkan dengan akun <strong>belajar.id</strong> sekolah. Gratis, tanpa Canva Pro.
                    <span x-show="canva.connected" x-cloak>
                        Terhubung: <span class="font-semibold font-mono" x-text="canva.email_masked"></span>
                    </span>
                </p>
                <div class="mt-3 flex flex-col sm:flex-row gap-2" x-show="!canva.connected" x-cloak>
                    <input type="email" x-model="belajarIdInput" placeholder="nama@sekolah.belajar.id"
                           class="form-input text-sm font-mono flex-1 min-w-0"
                           :disabled="canvaBusy">
                    <button type="button" @click="saveBelajarId" :disabled="canvaBusy || !(belajarIdInput || '').trim()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-bold min-h-[44px] disabled:opacity-40">
                        Simpan email
                    </button>
                </div>
                <p class="text-[11px] text-slate-400 mt-1" x-show="!canva.connected && canva.belajar_hint" x-cloak>
                    Siap hubungkan: <span class="font-mono font-semibold" x-text="canva.belajar_hint"></span>
                </p>
                <p class="text-[11px] text-rose-500 font-semibold mt-1" x-show="canvaError" x-cloak x-text="canvaError"></p>
                <p class="text-[11px] text-emerald-600 font-semibold mt-1" x-show="canvaMessage" x-cloak x-text="canvaMessage"></p>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <a href="{{ route('ai.teacher.presentasi.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 text-sm font-bold min-h-[44px] hover:border-primary">
                    <i data-lucide="presentation" class="w-4 h-4"></i> Studio Presentasi
                </a>
                <a x-show="!canva.connected" x-cloak href="{{ route('ai.teacher.canva.connect') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-sky-600 text-white px-4 py-2.5 text-sm font-bold min-h-[44px] hover:bg-sky-700"
                   :class="(!canva.configured || !canva.belajar_hint) && 'opacity-50 pointer-events-none'">
                    <i data-lucide="link" class="w-4 h-4"></i> Hubungkan Canva
                </a>
                <button x-show="canva.connected" x-cloak type="button" @click="disconnectCanva" :disabled="canvaBusy"
                        class="inline-flex items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 px-4 py-2.5 text-sm font-bold text-rose-600 min-h-[44px] disabled:opacity-50">
                    <i data-lucide="unlink" class="w-4 h-4"></i> Putuskan
                </button>
            </div>
        </div>
        <p class="text-[11px] text-amber-700 dark:text-amber-300" x-show="!canva.configured" x-cloak>
            Admin belum mengisi <code>CANVA_CLIENT_ID</code> / <code>CANVA_CLIENT_SECRET</code> di server.
        </p>
    </div>

    {{-- Tabs --}}
    <div class="ai-tabs" role="tablist" aria-label="Modul Asisten Guru">
        <template x-for="t in tabs" :key="t.key">
            <button type="button" role="tab" @click="selectTab(t.key)"
                    class="ai-tabs__btn"
                    :class="{ 'is-active': tab === t.key }"
                    :aria-selected="tab === t.key">
                <i :data-lucide="t.icon" class="w-4 h-4"></i>
                <span x-text="t.label"></span>
            </button>
        </template>
    </div>

    {{-- Nalar Guru (chat) --}}
    <div x-show="tab === 'gemini'" x-cloak class="ai-chat">
        <div class="ai-chat__head">
            <div class="min-w-0 flex items-center gap-3">
                <span class="ai-console__orb" style="width:40px;height:40px;border-radius:12px">
                    <i data-lucide="brain" class="w-4 h-4"></i>
                </span>
                <div>
                    <h2 class="m-0 text-[15px] font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Nalar Guru</h2>
                    <p class="m-0 text-[11px] text-slate-500 mt-0.5">Chat AI · generate di SIMS · key akun Anda</p>
                </div>
            </div>
            <button type="button" @click="clearGeminiChat" class="ai-btn ai-btn--ghost">
                <i data-lucide="eraser" class="w-3.5 h-3.5"></i> Reset
            </button>
        </div>

        <div class="ai-chat__body space-y-3" x-ref="geminiScroll">
            <div x-show="geminiMessages.length === 0" class="h-full min-h-[260px] grid place-items-center text-center px-3">
                <div class="w-full max-w-lg">
                    <div class="mx-auto mb-4 ai-console__orb" style="width:64px;height:64px;border-radius:18px">
                        <i data-lucide="brain" class="w-7 h-7"></i>
                    </div>
                    <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100 tracking-tight m-0">Tanya Nalar Guru</p>
                    <p class="text-sm text-slate-500 mt-1.5 leading-relaxed m-0">
                        Minta soal, penjelasan materi, atau rubrik — generate langsung di SIMS.
                        Soal siap dikirim ke Arena Belajar dari balasan chat.
                    </p>
                    <div class="mt-5 grid gap-2 text-left">
                        <template x-for="s in geminiSuggestions" :key="s">
                            <button type="button" @click="geminiInput = s; sendGeminiChat()" class="ai-chat__suggest">
                                <span class="mt-0.5 grid place-items-center w-7 h-7 shrink-0 rounded-lg ai-chat__icon-soft">
                                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                </span>
                                <span class="leading-snug pt-1" x-text="s"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <template x-for="(m, i) in geminiMessages" :key="i">
                <div class="flex gap-2" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div x-show="m.role === 'assistant'"
                         class="hidden sm:grid place-items-center w-8 h-8 shrink-0 rounded-xl mt-1 ai-chat__icon-soft">
                        <i data-lucide="brain" class="w-4 h-4"></i>
                    </div>
                    <div class="px-4 py-3 text-sm leading-relaxed"
                         :class="m.role === 'user'
                            ? 'ai-chat__bubble-user'
                            : (m.previewHtml
                                ? 'ai-chat__bubble-ai ai-chat__bubble-ai--wide overflow-auto'
                                : 'ai-chat__bubble-ai')">
                        <div x-show="m.role === 'assistant' && m.previewHtml" x-cloak
                             class="min-w-0 max-w-full overflow-x-auto overflow-y-auto overscroll-contain"
                             x-html="m.previewHtml"></div>
                        <div x-show="m.role === 'assistant' && !m.previewHtml"
                             class="ai-answer nalar-answer break-words whitespace-pre-wrap font-normal tracking-normal"
                             x-text="m.text"></div>
                        <div x-show="m.role === 'user'" class="whitespace-pre-wrap" x-text="m.text"></div>
                        <div x-show="m.role === 'assistant'"
                             class="mt-2.5 flex flex-wrap gap-2 border-t border-slate-100 dark:border-slate-700 pt-2">
                            <button type="button" @click="copyGeminiMessage(m)"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <i :data-lucide="copiedMessageKey === geminiMessageKey(m) ? 'check' : 'copy'" class="w-3.5 h-3.5"></i>
                                <span x-text="copiedMessageKey === geminiMessageKey(m) ? 'Tersalin' : 'Salin'"></span>
                            </button>
                            <button type="button"
                                    x-show="arenaBelajarAktif && arenaClassrooms.length && looksLikeQuizDocument(m.text)"
                                    @click="sendGeminiToArena(m)"
                                    :disabled="sendingArena"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-primary hover:bg-primary/10 disabled:opacity-50">
                                <i :data-lucide="sendingArena ? 'loader-circle' : 'gamepad-2'" class="w-3.5 h-3.5" :class="sendingArena ? 'animate-spin' : ''"></i>
                                <span x-text="sendingArena ? 'Mengirim…' : 'Kirim ke Arena'"></span>
                            </button>
                            <button type="button" @click="useGeminiAsQuizResult(m)"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-primary hover:bg-primary/10">
                                <i data-lucide="file-question" class="w-3.5 h-3.5"></i> Generator Soal
                            </button>
                            <button type="button" @click="result = m.text; exportQuiz('word')"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <i data-lucide="file-down" class="w-3.5 h-3.5"></i> Word
                            </button>
                            <button type="button" @click="result = m.text; exportQuiz('pdf')"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <i data-lucide="file-type" class="w-3.5 h-3.5"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="geminiLoading" class="flex justify-start gap-2" x-cloak>
                <div class="hidden sm:grid place-items-center w-8 h-8 shrink-0 rounded-xl ai-chat__icon-soft">
                    <i data-lucide="brain" class="w-4 h-4"></i>
                </div>
                <div class="ai-chat__bubble-ai px-4 py-3 text-sm text-slate-500 flex items-center gap-2">
                    <i data-lucide="loader-circle" class="w-4 h-4 animate-spin text-primary"></i>
                    Nalar sedang menyusun…
                    <span class="inline-flex gap-0.5 ml-1">
                        <span class="w-1 h-1 rounded-full bg-primary animate-pulse"></span>
                        <span class="w-1 h-1 rounded-full bg-primary animate-pulse" style="animation-delay:.15s"></span>
                        <span class="w-1 h-1 rounded-full bg-primary animate-pulse" style="animation-delay:.3s"></span>
                    </span>
                </div>
            </div>
            <p class="text-xs text-rose-500 font-semibold" x-show="geminiError" x-cloak x-text="geminiError"></p>
        </div>

        <div x-show="externalFlow && externalTool === 'chat'" x-cloak
             class="border-t border-slate-100 dark:border-slate-700 px-4 py-3 space-y-2 bg-primary/[0.04]">
            <p class="text-xs font-bold text-slate-700 dark:text-slate-200 m-0">
                Tempel jawaban Gemini
                <span class="font-medium text-slate-500">· pastikan akun Google Anda login di Gemini web</span>
            </p>
            <p class="text-[11px] text-emerald-600 font-semibold m-0" x-show="promptCopied" x-cloak>Perintah disalin · tempel di Gemini (Ctrl+V)</p>
            <textarea x-model="externalPaste" rows="5" class="ai-input" style="height:auto;padding:.65rem .75rem"
                      placeholder="Tempel hasil dari Gemini web di sini…"></textarea>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="applyExternalResult()" :disabled="applyingExternal || !(externalPaste || '').trim()"
                        class="ai-btn ai-btn--solid disabled:opacity-40">
                    <i data-lucide="check" class="w-4 h-4"></i> Pakai hasil ini
                </button>
                <button type="button" @click="reopenExternalGemini()" class="ai-btn ai-btn--ghost">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Buka Gemini lagi
                </button>
            </div>
        </div>

        <form @submit.prevent="sendGeminiChat" class="ai-chat__composer">
            <div class="ai-chat__composer-inner">
                <textarea x-model="geminiInput" rows="2"
                          @keydown.enter.prevent="if (!$event.shiftKey) sendGeminiChat()"
                          class="ai-chat__ta"
                          placeholder="Tanya Nalar… Enter kirim · Shift+Enter baris baru"></textarea>
                <button type="submit" :disabled="geminiLoading || !geminiInput.trim()"
                        class="ai-btn ai-btn--solid min-h-[48px] px-4 disabled:opacity-40 disabled:pointer-events-none">
                    <i data-lucide="send" class="w-4 h-4"></i> Kirim
                </button>
            </div>
        </form>
    </div>

    <div class="ai-teacher-tools-grid grid gap-5 min-w-0 xl:grid-cols-2 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(240px,0.55fr)]"
         x-show="isToolTab" x-cloak>
        {{-- Form Generator Soal --}}
        <div class="ai-teacher-form-card card p-5 min-w-0">
            <div class="ai-teacher-form-scroll">
            {{-- Generator Soal --}}
            <div x-show="tab === 'quiz'" class="space-y-4">
                <div>
                    <label class="form-label">Topik / Fokus Materi <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="quiz.topik" :placeholder="topicPlaceholder(quiz.output_language)" class="form-input">
                    <p class="text-[11px] text-slate-400 mt-1" x-show="quiz.source === 'ai'" x-cloak>Topik menjadi sumber soal bila generate tanpa file.</p>
                    <p class="text-[11px] text-slate-400 mt-1" x-show="quiz.source === 'file'" x-cloak>
                        Wajib diisi. Topik dipakai untuk mencari bagian buku yang relevan (bukan hanya halaman awal). Contoh: <span class="font-medium text-slate-500" x-text="quiz.output_language === 'zh-CN' ? '第三课 打招呼' : 'Bab 5 — Ekosistem'"></span>.
                    </p>
                    <ul class="mt-1.5 list-disc pl-4 text-[11px] text-slate-500 space-y-0.5" x-show="quiz.output_language === 'zh-CN'" x-cloak>
                        <template x-for="(example, idx) in hsk1TopicExamples" :key="'quiz-hsk-' + idx">
                            <li x-text="example"></li>
                        </template>
                    </ul>
                </div>

                <div>
                    <label class="form-label">Bahasa output <span class="text-rose-500">*</span></label>
                    <select x-model="quiz.output_language" class="form-input">
                        <template x-for="opt in outputLanguageOptions" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Kop sekolah dan identitas resmi tetap dari data SIMS.</p>
                    <label class="mt-2 flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"
                           x-show="quiz.output_language === 'zh-CN'" x-cloak
                           :class="quiz.include_pinyin ? 'border-primary bg-primary/5' : ''">
                        <input type="checkbox" x-model="quiz.include_pinyin" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">Sertakan pinyin</span>
                            <span class="mt-0.5 block text-[11px] text-slate-500">Tambahkan baris Hanyu Pinyin di bawah teks Hanzi pada soal dan lampiran.</span>
                        </span>
                    </label>
                </div>

                <div>
                    <label class="form-label">Sumber Materi <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button type="button" @click="quiz.source = 'ai'; quiz.document_uuid = ''; clearQuizFile()"
                                :class="quiz.source === 'ai' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-2 py-2 text-[11px] font-semibold transition">Dari topik</button>
                        <button type="button" @click="quiz.source = 'file'; loadMaterials()"
                                :class="quiz.source === 'file' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-2 py-2 text-[11px] font-semibold transition">Upload file</button>
                        <button type="button" @click="quiz.source = 'camera'; $nextTick(() => lucide && lucide.createIcons())"
                                :class="quiz.source === 'camera' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-2 py-2 text-[11px] font-semibold transition">Foto buku</button>
                    </div>
                </div>

                <div x-show="quiz.source === 'file'" x-cloak class="space-y-3">
                    <div x-show="materials.length" x-cloak>
                        <label class="form-label">Buku yang sudah diunggah</label>
                        <div class="space-y-2 max-h-44 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                            <template x-for="m in materials" :key="m.uuid">
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                            @click="selectMaterial(m)"
                                            class="flex flex-1 items-start gap-2 rounded-lg border px-3 py-2 text-left transition"
                                            :class="quiz.document_uuid === m.uuid ? 'border-primary bg-primary/5' : 'border-slate-200 hover:border-primary/40 dark:border-slate-700'">
                                        <i data-lucide="book-open" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400"></i>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-semibold text-slate-700 dark:text-slate-200" x-text="m.title"></span>
                                            <span class="mt-0.5 block text-[11px]"
                                                  :class="{
                                                      'text-emerald-600 dark:text-emerald-400': m.status === 'processed',
                                                      'text-amber-600 dark:text-amber-400': m.status === 'partial' || m.status === 'pending',
                                                      'text-rose-600 dark:text-rose-400': m.status === 'failed',
                                                      'text-slate-400': !['processed','partial','pending','failed'].includes(m.status)
                                                  }"
                                                  x-text="m.status_label + (m.chunk_count ? ' · ' + m.chunk_count + ' bagian' : '')"></span>
                                        </span>
                                    </button>
                                    <button type="button" @click.stop="cancelMaterial(m.uuid)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition shrink-0" title="Batalkan / Hapus materi ini">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">Pilih buku lama agar tidak perlu unggah ulang. Status "menunggu kuota" dilanjutkan otomatis keesokan hari (free tier).</p>
                    </div>

                    <div>
                        <label class="form-label">
                            Unggah materi baru
                            <span class="text-rose-500" x-show="!quiz.document_uuid" x-cloak>*</span>
                            <span class="text-slate-400 font-normal" x-show="quiz.document_uuid" x-cloak>(opsional, ganti buku terpilih)</span>
                        </label>
                        <label class="flex min-h-[104px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center transition hover:border-primary hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-primary/70">
                            <input x-ref="quizFile" type="file" class="sr-only" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="setQuizFile($event)">
                            <i data-lucide="upload-cloud" class="w-7 h-7 text-slate-400"></i>
                            <span class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="quiz.fileName || 'Unggah PDF atau Word'"></span>
                            <span class="mt-1 text-[11px] text-slate-400">File besar diindeks (RAG) agar soal diambil dari bab yang diminta. Maks. 10 MB.</span>
                        </label>
                        <div class="mt-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-[11px] leading-relaxed text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                            <p class="font-semibold">PDF hasil scan atau buku foto?</p>
                            <p class="mt-0.5 opacity-90">Upload hanya untuk PDF/Word yang teksnya bisa disalin. Buku scan/Hanzi → pilih <strong>Foto buku</strong>.</p>
                        </div>
                        <div x-show="quiz.file" x-cloak class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <span class="truncate" x-text="quiz.fileName"></span>
                            <button type="button" @click="clearQuizFile()" class="inline-flex items-center gap-1 text-rose-600 hover:text-rose-700 dark:text-rose-300">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                            </button>
                        </div>
                    </div>

                    <div x-show="selectedMaterial()" x-cloak
                         class="rounded-xl border px-3 py-2 text-[11px]"
                         :class="selectedMaterial()?.ready
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200'
                            : (selectedMaterial()?.status === 'failed'
                                ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-200'
                                : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100')">
                        <span class="font-semibold" x-text="selectedMaterial()?.title"></span>
                        <span class="mx-1">·</span>
                        <span x-text="selectedMaterial()?.status_label"></span>
                        <template x-if="selectedMaterial()?.awaiting_quota">
                            <span class="block mt-1 opacity-90">Kuota embedding harian habis — sisa bagian dilanjutkan otomatis setelah reset (gratis). Bagian yang sudah siap tetap bisa dipakai untuk membuat soal.</span>
                        </template>
                    </div>
                    <div x-show="materialError && materialError.tool === 'quiz'" x-cloak
                         class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-3 text-[11px] leading-relaxed text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-100">
                        <p class="font-semibold" x-text="materialError.message"></p>
                        <p class="mt-1 opacity-90" x-show="materialError.hint" x-text="materialError.hint"></p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" class="ai-btn ai-btn--solid min-h-[36px] text-[11px]"
                                    x-show="materialError.suggest_camera"
                                    @click="switchToCameraFromMaterialError()">
                                <i data-lucide="camera" class="w-3.5 h-3.5"></i> Pakai Foto buku
                            </button>
                            <button type="button" class="ai-btn ai-btn--ghost min-h-[36px] text-[11px]"
                                    @click="clearMaterialError()">Tutup</button>
                        </div>
                    </div>
                </div>

                <div x-show="quiz.source === 'camera'" x-cloak class="space-y-3">
                    <label class="form-label">Foto halaman buku <span class="text-rose-500">*</span></label>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Fokus ke teks, cahaya cukup, kamera stabil. Foto buram ditolak otomatis — potret ulang. Tak perlu "Jadikan teks" manual — foto langsung dibaca otomatis saat klik <strong>Buat Soal</strong>.</p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                        <p class="font-bold text-slate-700 dark:text-slate-200 mb-1">Batas &amp; konversi otomatis</p>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li>Maks. <strong x-text="ocr.maxImages"></strong> foto · format JPEG/PNG/WebP</li>
                            <li>Ukuran unggah maks. <strong x-text="formatBytes(ocr.maxBytes)"></strong>/foto · target kompres ~<strong x-text="formatBytes(ocr.targetBytes)"></strong></li>
                            <li>Foto besar / resolusi tinggi <strong>otomatis dikompres</strong> ke JPEG (sisi max <span x-text="ocr.maxEdge"></span>px, kualitas tinggi)</li>
                            <li>Teks hasil OCR maks. <strong x-text="formatNumber(ocr.maxChars)"></strong> karakter (kelebihan dipotong otomatis)</li>
                        </ul>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ai-btn min-h-[44px]" @click="openOcrCamera('quiz')">
                            <i data-lucide="camera" class="w-4 h-4"></i> Buka kamera
                        </button>
                        {{-- Fallback native capture: TANPA multiple (multiple memaksa galeri di banyak HP) --}}
                        <input x-ref="ocrCameraNativeQuiz" type="file" accept="image/*" capture="environment"
                               class="sr-only" @change="addOcrImages($event, 'quiz')">
                        <label class="ai-btn ai-btn--ghost cursor-pointer min-h-[44px]">
                            <i data-lucide="image" class="w-4 h-4"></i> Dari galeri
                            <input type="file" accept="image/jpeg,image/png,image/webp,image/*" class="sr-only"
                                   @change="addOcrImages($event, 'quiz')" multiple>
                        </label>
                    </div>
                    <div class="grid grid-cols-3 gap-2" x-show="ocr.quiz.images.length">
                        <template x-for="(img, idx) in ocr.quiz.images" :key="img.id">
                            <div class="relative rounded-xl border overflow-hidden"
                                 :class="img.blurry && !img.forceKeep ? 'border-rose-300' : 'border-slate-200 dark:border-slate-700'">
                                <img :src="img.preview" alt="" class="h-24 w-full object-cover">
                                <span class="absolute left-1 top-1 rounded px-1.5 py-0.5 text-[9px] font-bold"
                                      :class="img.blurry && !img.forceKeep ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'"
                                      x-text="img.blurry && !img.forceKeep ? 'Buram' : 'Tajam'"></span>
                                <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1 py-0.5 text-[9px] font-mono text-white"
                                      x-text="(img.converted ? '→ ' : '') + (img.sizeKb || 0) + ' KB'"></span>
                                <button type="button" @click="removeOcrImage('quiz', idx)"
                                        class="absolute right-1 top-1 rounded-md bg-black/55 p-1 text-white">
                                    <i data-lucide="x" class="w-3 h-3"></i>
                                </button>
                                <button type="button" x-show="img.blurry && !img.forceKeep" x-cloak
                                        @click="img.forceKeep = true; $nextTick(() => lucide && lucide.createIcons())"
                                        class="absolute bottom-6 left-1 right-1 rounded bg-white/95 px-1 py-0.5 text-[9px] font-bold text-slate-700">
                                    Tetap pakai
                                </button>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-emerald-700 dark:text-emerald-300 font-semibold" x-show="ocr.quiz.notice" x-cloak x-text="ocr.quiz.notice"></p>
                    <p class="text-xs text-rose-600 font-semibold" x-show="ocr.quiz.error" x-cloak x-text="ocr.quiz.error"></p>
                    <div x-show="ocr.quiz.text" x-cloak class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <label class="form-label mb-0">Teks hasil scan (bisa diedit)</label>
                            <span class="text-[10px] font-mono"
                                  :class="(ocr.quiz.text || '').length > ocr.maxChars ? 'text-rose-600 font-bold' : 'text-slate-400'"
                                  x-text="formatNumber((ocr.quiz.text || '').length) + ' / ' + formatNumber(ocr.maxChars) + ' karakter'"></span>
                        </div>
                        <textarea x-model="ocr.quiz.text" rows="4" class="form-input text-sm leading-relaxed"
                                  placeholder="Teks dari foto akan muncul di sini… (juga di panel Hasil)"
                                  @input="syncResultFromOcr('quiz'); clampOcrText('quiz')"></textarea>
                        <p class="text-[11px] text-slate-400">Panel <strong>Hasil</strong> menampilkan teks lebih besar — edit, salin, Word/PDF di sana.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Jumlah Soal <span class="text-rose-500">*</span></label>
                        <input type="number" x-model.number="quiz.jumlah" min="1" max="20" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Jenjang (opsional)</label>
                        <input type="text" x-model="quiz.jenjang" placeholder="mis. Kelas 5 SD" class="form-input">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <div>
                        <label class="form-label">Jenis Soal <span class="text-rose-500">*</span></label>
                        <div class="grid gap-2 rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                            <template x-for="option in quizTypeOptions" :key="option.value">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                                       :class="quiz.jenis_soal.includes(option.value) ? 'border-primary bg-primary/5 text-primary' : 'border-slate-200 text-slate-600 hover:border-primary/50 dark:border-slate-700 dark:text-slate-300'">
                                    <input type="checkbox" :value="option.value" x-model="quiz.jenis_soal" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                                    <span x-text="option.label"></span>
                                </label>
                            </template>
                        </div>
                        <p class="mt-1 text-[11px] text-rose-500" x-show="quiz.jenis_soal.length === 0" x-cloak>Pilih minimal satu jenis soal.</p>
                    </div>
                    <div>
                        <label class="form-label">Tingkat Kesulitan <span class="text-rose-500">*</span></label>
                        <select x-model="quiz.tingkat" class="form-input">
                            <option value="mudah">Mudah</option>
                            <option value="sedang">Sedang</option>
                            <option value="sulit">Sulit</option>
                        </select>
                    </div>
                </div>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition"
                       :class="quiz.soal_bergambar ? 'border-primary bg-primary/5' : 'border-slate-200 dark:border-slate-700'">
                    <input type="checkbox" x-model="quiz.soal_bergambar" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <span>
                        <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Soal bergambar (Gemini Image)</span>
                        <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">AI menambahkan diagram/ilustrasi pada soal. Memakai kuota Gemini Image terpisah (maks. {{ (int) config('ai.image.max_per_quiz', 5) }} gambar/batch).</span>
                    </span>
                </label>
                <button type="button" @click="submit('quiz')" :disabled="loading || quiz.jenis_soal.length === 0 || !canSubmitQuiz()" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="wand-2" class="w-4 h-4" :class="loading && 'animate-spin'"></i>
                    <span x-text="loading && ocr.loading ? 'Membaca foto…' : (loading ? 'Menyusun soal…' : 'Buat Soal')"></span>
                </button>
                <button type="button" @click="submitExternal('quiz')" :disabled="loading || quiz.jenis_soal.length === 0 || !canSubmitQuiz() || quiz.source === 'camera'"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-xs font-semibold text-slate-500 hover:border-primary hover:text-primary disabled:opacity-40">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    <span x-text="quiz.source === 'camera' ? 'Cadangan web: tidak untuk foto' : 'Cadangan: buka Gemini web'"></span>
                </button>
            </div>

            {{-- RPM Learning --}}
            <div x-show="tab === 'learning'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Topik / Judul RPM <span class="text-rose-500" x-show="learning.source === 'ai'" x-cloak>*</span></label>
                    <input type="text" x-model="learning.topik" :placeholder="topicPlaceholder(learning.output_language)" class="form-input">
                    <p class="text-[11px] text-slate-400 mt-1">Jika upload/foto materi, topik boleh dipakai sebagai fokus/judul RPM.</p>
                    <ul class="mt-1.5 list-disc pl-4 text-[11px] text-slate-500 space-y-0.5" x-show="learning.output_language === 'zh-CN'" x-cloak>
                        <template x-for="(example, idx) in hsk1TopicExamples" :key="'learning-hsk-' + idx">
                            <li x-text="example"></li>
                        </template>
                    </ul>
                </div>
                <div>
                    <label class="form-label">Bahasa output <span class="text-rose-500">*</span></label>
                    <select x-model="learning.output_language" class="form-input">
                        <template x-for="opt in outputLanguageOptions" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Kop sekolah dan identitas resmi tetap dari data SIMS.</p>
                    <label class="mt-2 flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"
                           x-show="learning.output_language === 'zh-CN'" x-cloak
                           :class="learning.include_pinyin ? 'border-primary bg-primary/5' : ''">
                        <input type="checkbox" x-model="learning.include_pinyin" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">Sertakan pinyin</span>
                            <span class="mt-0.5 block text-[11px] text-slate-500">Tambahkan baris Hanyu Pinyin di bawah teks Hanzi pada narasi RPM dan lampiran.</span>
                        </span>
                    </label>
                </div>
                <div>
                    <label class="form-label">Sumber Materi <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button type="button" @click="learning.source = 'ai'"
                                :class="learning.source === 'ai' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-2 py-2 text-[11px] font-semibold transition">Dari topik</button>
                        <button type="button" @click="learning.source = 'file'; loadMaterials()"
                                :class="learning.source === 'file' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-2 py-2 text-[11px] font-semibold transition">Upload file</button>
                        <button type="button" @click="learning.source = 'camera'; $nextTick(() => lucide && lucide.createIcons())"
                                :class="learning.source === 'camera' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-2 py-2 text-[11px] font-semibold transition">Foto buku</button>
                    </div>
                </div>
                <div x-show="learning.source === 'file'" x-cloak class="space-y-3">
                    <div x-show="materials.length" x-cloak>
                        <label class="form-label">Buku yang sudah diunggah</label>
                        <div class="space-y-2 max-h-44 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                            <template x-for="m in materials" :key="'learning-' + m.uuid">
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                            @click="selectLearningMaterial(m)"
                                            class="flex flex-1 items-start gap-2 rounded-lg border px-3 py-2 text-left transition"
                                            :class="learning.document_uuid === m.uuid ? 'border-primary bg-primary/5' : 'border-slate-200 hover:border-primary/40 dark:border-slate-700'">
                                        <i data-lucide="book-open" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400"></i>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-semibold text-slate-700 dark:text-slate-200" x-text="m.title"></span>
                                            <span class="mt-0.5 block text-[11px]"
                                                  :class="{
                                                      'text-emerald-600 dark:text-emerald-400': m.status === 'processed',
                                                      'text-amber-600 dark:text-amber-400': m.status === 'partial' || m.status === 'pending',
                                                      'text-rose-600 dark:text-rose-400': m.status === 'failed',
                                                      'text-slate-400': !['processed','partial','pending','failed'].includes(m.status)
                                                  }"
                                                  x-text="m.status_label + (m.chunk_count ? ' · ' + m.chunk_count + ' bagian' : '')"></span>
                                        </span>
                                    </button>
                                    <button type="button" @click.stop="cancelMaterial(m.uuid)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition shrink-0" title="Batalkan / Hapus materi ini">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">Pilih buku lama + isi topik agar RPM diambil dari bab yang relevan (RAG).</p>
                    </div>
                    <div>
                        <label class="form-label">
                            Unggah materi baru
                            <span class="text-rose-500" x-show="!learning.document_uuid" x-cloak>*</span>
                            <span class="text-slate-400 font-normal" x-show="learning.document_uuid" x-cloak>(opsional, ganti buku terpilih)</span>
                        </label>
                        <label class="flex min-h-[104px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center transition hover:border-primary hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-primary/70">
                            <input x-ref="learningFile" type="file" class="sr-only" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="setLearningFile($event)">
                            <i data-lucide="upload-cloud" class="w-7 h-7 text-slate-400"></i>
                            <span class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="learning.fileName || 'Unggah PDF atau Word'"></span>
                            <span class="mt-1 text-[11px] text-slate-400">File besar diindeks (RAG) agar RPM diambil dari bab yang diminta. Maks. 10 MB.</span>
                        </label>
                        <div class="mt-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-[11px] leading-relaxed text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                            <p class="font-semibold">PDF hasil scan atau buku foto?</p>
                            <p class="mt-0.5 opacity-90">Upload hanya untuk PDF/Word ber-teks. Buku scan/Hanzi → pilih <strong>Foto buku</strong>.</p>
                        </div>
                        <div x-show="learning.file" x-cloak class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <span class="truncate" x-text="learning.fileName"></span>
                            <button type="button" @click="clearLearningFile()" class="inline-flex items-center gap-1 text-rose-600 hover:text-rose-700 dark:text-rose-300">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                            </button>
                        </div>
                    </div>
                    <div x-show="selectedLearningMaterial()" x-cloak
                         class="rounded-xl border px-3 py-2 text-[11px]"
                         :class="selectedLearningMaterial()?.ready
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200'
                            : (selectedLearningMaterial()?.status === 'failed'
                                ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-200'
                                : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100')">
                        <span class="font-semibold" x-text="selectedLearningMaterial()?.title"></span>
                        <span class="mx-1">·</span>
                        <span x-text="selectedLearningMaterial()?.status_label"></span>
                    </div>
                    <div x-show="materialError && materialError.tool === 'learning'" x-cloak
                         class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-3 text-[11px] leading-relaxed text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-100">
                        <p class="font-semibold" x-text="materialError.message"></p>
                        <p class="mt-1 opacity-90" x-show="materialError.hint" x-text="materialError.hint"></p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" class="ai-btn ai-btn--solid min-h-[36px] text-[11px]"
                                    x-show="materialError.suggest_camera"
                                    @click="switchToCameraFromMaterialError()">
                                <i data-lucide="camera" class="w-3.5 h-3.5"></i> Pakai Foto buku
                            </button>
                            <button type="button" class="ai-btn ai-btn--ghost min-h-[36px] text-[11px]"
                                    @click="clearMaterialError()">Tutup</button>
                        </div>
                    </div>
                </div>
                <div x-show="learning.source === 'camera'" x-cloak class="space-y-3">
                    <label class="form-label">Foto halaman buku <span class="text-rose-500">*</span></label>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Foto buram ditolak — potret ulang. Teks hasil scan bisa diedit sebelum buat RPM.</p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                        <p class="font-bold text-slate-700 dark:text-slate-200 mb-1">Batas &amp; konversi otomatis</p>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li>Maks. <strong x-text="ocr.maxImages"></strong> foto · format JPEG/PNG/WebP</li>
                            <li>Ukuran unggah maks. <strong x-text="formatBytes(ocr.maxBytes)"></strong>/foto · target kompres ~<strong x-text="formatBytes(ocr.targetBytes)"></strong></li>
                            <li>Foto besar <strong>otomatis dikompres</strong> ke JPEG (sisi max <span x-text="ocr.maxEdge"></span>px)</li>
                            <li>Teks hasil OCR maks. <strong x-text="formatNumber(ocr.maxChars)"></strong> karakter</li>
                        </ul>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ai-btn min-h-[44px]" @click="openOcrCamera('learning')">
                            <i data-lucide="camera" class="w-4 h-4"></i> Buka kamera
                        </button>
                        <input x-ref="ocrCameraNativeLearning" type="file" accept="image/*" capture="environment"
                               class="sr-only" @change="addOcrImages($event, 'learning')">
                        <label class="ai-btn ai-btn--ghost cursor-pointer min-h-[44px]">
                            <i data-lucide="image" class="w-4 h-4"></i> Dari galeri
                            <input type="file" accept="image/jpeg,image/png,image/webp,image/*" class="sr-only"
                                   @change="addOcrImages($event, 'learning')" multiple>
                        </label>
                        <button type="button" class="ai-btn ai-btn--solid min-h-[44px]"
                                @click="runOcr('learning')"
                                :disabled="ocr.loading || !ocrHasUsable('learning')">
                            <i data-lucide="scan-text" class="w-4 h-4" :class="ocr.loading && 'animate-spin'"></i>
                            <span x-text="ocr.loading ? 'Membaca teks…' : 'Jadikan teks'"></span>
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-2" x-show="ocr.learning.images.length">
                        <template x-for="(img, idx) in ocr.learning.images" :key="img.id">
                            <div class="relative rounded-xl border overflow-hidden"
                                 :class="img.blurry && !img.forceKeep ? 'border-rose-300' : 'border-slate-200 dark:border-slate-700'">
                                <img :src="img.preview" alt="" class="h-24 w-full object-cover">
                                <span class="absolute left-1 top-1 rounded px-1.5 py-0.5 text-[9px] font-bold"
                                      :class="img.blurry && !img.forceKeep ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'"
                                      x-text="img.blurry && !img.forceKeep ? 'Buram' : 'Tajam'"></span>
                                <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1 py-0.5 text-[9px] font-mono text-white"
                                      x-text="(img.converted ? '→ ' : '') + (img.sizeKb || 0) + ' KB'"></span>
                                <button type="button" @click="removeOcrImage('learning', idx)"
                                        class="absolute right-1 top-1 rounded-md bg-black/55 p-1 text-white">
                                    <i data-lucide="x" class="w-3 h-3"></i>
                                </button>
                                <button type="button" x-show="img.blurry && !img.forceKeep" x-cloak
                                        @click="img.forceKeep = true; $nextTick(() => lucide && lucide.createIcons())"
                                        class="absolute bottom-6 left-1 right-1 rounded bg-white/95 px-1 py-0.5 text-[9px] font-bold text-slate-700">
                                    Tetap pakai
                                </button>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-emerald-700 dark:text-emerald-300 font-semibold" x-show="ocr.learning.notice" x-cloak x-text="ocr.learning.notice"></p>
                    <p class="text-xs text-rose-600 font-semibold" x-show="ocr.learning.error" x-cloak x-text="ocr.learning.error"></p>
                    <div x-show="ocr.learning.text" x-cloak class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <label class="form-label mb-0">Teks hasil scan (bisa diedit)</label>
                            <span class="text-[10px] font-mono"
                                  :class="(ocr.learning.text || '').length > ocr.maxChars ? 'text-rose-600 font-bold' : 'text-slate-400'"
                                  x-text="formatNumber((ocr.learning.text || '').length) + ' / ' + formatNumber(ocr.maxChars) + ' karakter'"></span>
                        </div>
                        <textarea x-model="ocr.learning.text" rows="4" class="form-input text-sm leading-relaxed"
                                  placeholder="Teks dari foto akan muncul di sini… (juga di panel Hasil)"
                                  @input="syncResultFromOcr('learning'); clampOcrText('learning')"></textarea>
                        <p class="text-[11px] text-slate-400">Panel <strong>Hasil</strong> menampilkan teks lebih besar — edit, salin, Word/PDF di sana.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Mata Pelajaran</label>
                        <input type="text" x-model="learning.mapel" placeholder="mis. IPAS" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Jenjang / Kelas</label>
                        <input type="text" x-model="learning.jenjang" placeholder="mis. Kelas 5 SD" class="form-input">
                    </div>
                </div>
                <div>
                    <label class="form-label">Alokasi Waktu</label>
                    <input type="text" x-model="learning.durasi" placeholder="mis. 2 x 40 menit" class="form-input">
                </div>
                <button type="button" @click="submit('learning')" :disabled="loading || !learningSourceReady()" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="clipboard-list" class="w-4 h-4"></i> Buat RPM Learning
                </button>
                <button type="button" @click="submitExternal('learning')" :disabled="loading || !learningSourceReady()"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-xs font-semibold text-slate-500 hover:border-primary hover:text-primary disabled:opacity-40">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    <span x-text="learning.source === 'camera' ? 'Cadangan web: tidak untuk foto' : 'Cadangan: buka Gemini web'"></span>
                </button>
            </div>
            {{-- Perangkum Materi --}}
            <div x-show="tab === 'summary'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Materi <span class="text-rose-500">*</span></label>
                    <textarea x-model="summary.materi" rows="12" placeholder="Tempel materi panjang di sini..." class="form-input resize-y"></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Maks. {{ number_format(config('ai.max_input_chars')) }} karakter.</p>
                </div>
                <button type="button" @click="submit('summary')" :disabled="loading || summary.materi.trim() === ''" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="list-collapse" class="w-4 h-4"></i> Rangkum
                </button>
            </div>

            {{-- Catatan Siswa --}}
            <div x-show="tab === 'feedback'" class="space-y-4" x-cloak>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Susun catatan hangat dan membangun untuk siswa — dari jawaban, sikap, atau hal yang ingin Anda sampaikan.
                    Hasil adalah draf; edit dulu sebelum dibagikan.
                </p>
                <div>
                    <label class="form-label">Nama siswa (opsional)</label>
                    <input type="text" x-model="feedback.nama" placeholder="mis. Andi" class="form-input">
                </div>
                <div>
                    <label class="form-label">Apa yang ingin dicatat? <span class="text-rose-500">*</span></label>
                    <textarea x-model="feedback.konteks" rows="9" placeholder="mis. Jawaban ujian, sikap belajar, tugas, atau hal yang ingin dikomentari..." class="form-input resize-y"></textarea>
                </div>
                <button type="button" @click="submit('feedback')" :disabled="loading || feedback.konteks.trim() === ''" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="message-square-heart" class="w-4 h-4"></i> Susun Catatan Siswa
                </button>
            </div>
            </div>{{-- /ai-teacher-form-scroll --}}
        </div>

        {{-- Hasil: tinggi sama form generator sampai bawah --}}
        <div class="ai-teacher-hasil card p-0 min-w-0 max-w-full">
            <div class="ai-teacher-col-shell p-4 sm:p-5">
                <div class="ai-teacher-hasil__toolbar flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-3 min-w-0">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 shrink-0">
                            <i data-lucide="file-text" class="w-4 h-4"></i> Hasil
                        </h2>
                        <p class="text-[11px] text-slate-400 mt-0.5" x-show="result && resultSource === 'ocr'" x-cloak>
                            Teks scan buku · kop + stempel sumber sekolah ·
                            <span class="font-mono" x-text="formatNumber((result || '').length) + ' / ' + formatNumber(ocr.maxChars) + ' karakter'"></span>
                            · edit · salin · Word/PDF
                        </p>
                        <p class="text-[10px] text-amber-700 dark:text-amber-300 mt-0.5 leading-snug" x-show="result && resultSource === 'ocr'" x-cloak>
                            Stempel sumber di header menandai materi dari foto buku (bukan karya AI orisinal). Jaga saat mengutip.
                        </p>
                    </div>
                    <div x-show="result" x-cloak class="flex flex-wrap items-center gap-1.5 sm:justify-end min-w-0">
                        <button type="button" @click="toggleEdit()" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800">
                            <i :data-lucide="editing ? 'check' : 'pencil'" class="w-4 h-4"></i><span x-text="editing ? 'Selesai' : 'Edit'"></span>
                        </button>
                        <button type="button" x-show="tab === 'quiz' || resultSource === 'ocr'" @click="exportQuiz('word')" :disabled="exportingWord" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                            <i :data-lucide="exportingWord ? 'loader-circle' : 'file-down'" class="w-4 h-4" :class="exportingWord ? 'animate-spin' : ''"></i><span x-text="exportingWord ? 'Export...' : 'Word'"></span>
                        </button>
                        <button type="button" x-show="tab === 'quiz' || resultSource === 'ocr'" @click="exportQuiz('pdf')" :disabled="exportingPdf" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                            <i :data-lucide="exportingPdf ? 'loader-circle' : 'file-type'" class="w-4 h-4" :class="exportingPdf ? 'animate-spin' : ''"></i><span x-text="exportingPdf ? 'Export...' : 'PDF'"></span>
                        </button>
                        <button type="button" x-show="tab === 'quiz' && resultSource !== 'ocr' && arenaBelajarAktif && arenaClassrooms.length"
                                @click="openSendToArena()" :disabled="sendingArena"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-primary transition hover:bg-primary/10 disabled:opacity-50">
                            <i :data-lucide="sendingArena ? 'loader-circle' : 'gamepad-2'" class="w-4 h-4" :class="sendingArena ? 'animate-spin' : ''"></i>
                            <span x-text="sendingArena ? 'Mengirim…' : 'Kirim ke Arena'"></span>
                        </button>
                        <button type="button" x-show="tab === 'learning' && resultSource !== 'ocr'" @click="exportLearning('word')" :disabled="exportingWord" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                            <i :data-lucide="exportingWord ? 'loader-circle' : 'file-down'" class="w-4 h-4" :class="exportingWord ? 'animate-spin' : ''"></i><span x-text="exportingWord ? 'Export...' : 'Word'"></span>
                        </button>
                        <button type="button" x-show="tab === 'learning' && resultSource !== 'ocr'" @click="exportLearning('pdf')" :disabled="exportingPdf" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                            <i :data-lucide="exportingPdf ? 'loader-circle' : 'file-type'" class="w-4 h-4" :class="exportingPdf ? 'animate-spin' : ''"></i><span x-text="exportingPdf ? 'Export...' : 'PDF'"></span>
                        </button>
                        <button type="button" @click="copy()" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800">
                            <i :data-lucide="copied ? 'check' : 'copy'" class="w-4 h-4"></i><span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                        </button>
                        <button type="button" @click="clearResult()" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 dark:text-rose-300 dark:hover:bg-rose-900/30">
                            <i data-lucide="trash-2" class="w-4 h-4"></i><span>Hapus</span>
                        </button>
                    </div>
                </div>

                <div class="ai-teacher-hasil__body" x-ref="hasilScrollBody">
                    <div x-show="loading || ocr.loading" x-cloak class="grid place-items-center py-16 text-slate-400">
                        <div class="text-center">
                            <i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin"></i>
                            <p class="text-sm mt-2" x-text="ocr.loading ? 'Membaca teks dari foto buku…' : 'Asisten Guru sedang menyusun...'"></p>
                        </div>
                    </div>

                    <div x-show="externalFlow && !loading" x-cloak class="rounded-xl border border-primary/20 bg-primary/[0.04] px-4 py-3 text-sm space-y-2">
                        <p class="font-bold text-slate-800 dark:text-slate-100">Langkah generate di Gemini web</p>
                        <ol class="list-decimal pl-5 space-y-1 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            <li>Pastikan Anda sudah login di Gemini web dengan akun Google yang dipakai membuat API key</li>
                            <li>Tempel perintah di Gemini (<kbd class="px-1 rounded bg-slate-200 dark:bg-slate-700">Ctrl</kbd>+<kbd class="px-1 rounded bg-slate-200 dark:bg-slate-700">V</kbd>) lalu generate</li>
                            <li>Salin jawaban Gemini, tempel di bawah, lalu klik <span class="font-semibold">Pakai hasil ini</span></li>
                        </ol>
                        <p class="text-[11px] text-emerald-700 dark:text-emerald-300 font-semibold" x-show="promptCopied" x-cloak>Perintah sudah disalin ke clipboard.</p>
                        <button type="button" @click="reopenExternalGemini()" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Buka Gemini lagi
                        </button>
                        <div class="pt-1 space-y-2">
                            <label class="form-label">Tempel jawaban dari Gemini</label>
                            <textarea x-model="externalPaste" rows="8" class="form-input text-sm leading-relaxed" placeholder="Tempel hasil generate dari Gemini di sini…"></textarea>
                            <button type="button" @click="applyExternalResult()" :disabled="applyingExternal || !(externalPaste || '').trim()"
                                    class="btn-primary w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-40">
                                <i :data-lucide="applyingExternal ? 'loader-circle' : 'check'" class="w-4 h-4" :class="applyingExternal ? 'animate-spin' : ''"></i>
                                <span x-text="applyingExternal ? 'Menyimpan…' : 'Pakai hasil ini'"></span>
                            </button>
                        </div>
                    </div>

                    <div x-show="error && !loading" x-cloak class="rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800 px-4 py-3 text-sm space-y-2">
                        <p x-text="error"></p>
                        <p class="text-[12px] opacity-90" x-show="materialError && materialError.hint" x-text="materialError.hint"></p>
                        <button type="button" class="ai-btn ai-btn--solid min-h-[36px] text-xs"
                                x-show="materialError && materialError.suggest_camera"
                                @click="switchToCameraFromMaterialError()">
                            <i data-lucide="camera" class="w-3.5 h-3.5"></i> Pakai Foto buku sebagai gantinya
                        </button>
                    </div>

                    <div x-show="!loading && !ocr.loading && !result && !error && !externalFlow" x-cloak
                         class="ai-teacher-hasil__empty text-slate-300 dark:text-slate-600">
                        <div class="text-center">
                            <i data-lucide="sparkles" class="w-10 h-10 mx-auto opacity-40"></i>
                            <p class="text-sm mt-2">Hasil generate / teks scan akan muncul di sini.</p>
                        </div>
                    </div>

                    <textarea x-show="result && !loading && !ocr.loading && editing" x-cloak
                              x-model="result" @input="syncOcrFromResult()"
                              rows="20" class="form-input w-full min-h-[min(60vh,520px)] resize-y text-sm leading-relaxed"></textarea>

                    <div x-show="result && !loading && !ocr.loading && !editing && previewHtml && resultSource !== 'ocr'" x-cloak
                         class="quiz-preview-scroll min-w-0 max-w-full"
                         x-html="previewHtml"></div>

                    {{-- Teks scan buku: polos, besar, mudah dibaca --}}
                    <div x-show="result && !loading && !ocr.loading && !editing && resultSource === 'ocr'" x-cloak
                         class="ai-answer min-w-0 max-w-full break-words whitespace-pre-wrap text-[15px] leading-relaxed text-slate-800 dark:text-slate-100"
                         x-text="result"></div>

                    <div x-show="result && !loading && !ocr.loading && !editing && resultSource !== 'ocr' && !previewHtml" x-cloak
                         class="ai-answer min-w-0 max-w-full break-words text-sm text-slate-800 dark:text-slate-100"
                         x-html="renderAiMarkdown(result)"></div>
                </div>
            </div>
        </div>

        {{-- History: tinggi sama form generator sampai bawah --}}
        <div class="ai-teacher-history card p-0 min-w-0 xl:col-span-2 2xl:col-span-1"
             x-data="{
                collapsed: localStorage.getItem('ai.teacher.historyCollapsed') === '1',
                toggle() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('ai.teacher.historyCollapsed', this.collapsed ? '1' : '0');
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
             }">
            <div class="ai-teacher-col-shell">
                <button type="button" @click="toggle()"
                        class="flex w-full shrink-0 items-center justify-between gap-3 px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 text-sm">
                        <i data-lucide="history" class="w-4 h-4"></i> History Generate
                        <span class="text-[11px] font-medium text-slate-400" x-text="histories.length ? '(' + histories.length + ')' : ''"></span>
                    </h2>
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-400">
                        <span x-text="collapsed ? 'Buka' : 'Tutup'"></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="collapsed ? '' : 'rotate-180'"></i>
                    </span>
                </button>

                <div x-show="!collapsed" x-cloak class="flex min-h-0 flex-1 flex-col border-t border-slate-100 dark:border-slate-800">
                    <div x-show="histories.length === 0" class="grid flex-1 place-items-center px-4 py-10 text-slate-300 dark:text-slate-600">
                        <p class="text-xs text-center">Belum ada history.</p>
                    </div>

                    <div x-show="histories.length > 0" class="ai-teacher-history-body space-y-2 px-3 py-3">
                        <template x-for="item in histories" :key="item.uuid">
                            <div class="rounded-lg border border-slate-200 bg-white transition hover:border-primary hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-primary/70">
                                <div class="flex items-start gap-1 p-2.5">
                                    <button type="button" @click="openHistory(item)" class="min-w-0 flex-1 text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="inline-flex items-center rounded-full bg-primary-50 px-1.5 py-0.5 text-[10px] font-semibold text-primary" x-text="item.type_label"></span>
                                            <span class="shrink-0 text-[10px] text-slate-400" x-text="item.created_at_human || ''"></span>
                                        </div>
                                        <div class="mt-1 text-xs font-semibold text-slate-700 dark:text-slate-100 break-words" x-text="item.title"></div>
                                        <p class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400 line-clamp-3" x-text="item.excerpt"></p>
                                    </button>
                                    <button type="button" @click="deleteHistory(item)" :disabled="deletingHistory === item.uuid"
                                            :title="'Hapus history: ' + item.title"
                                            class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50 dark:hover:bg-rose-900/30 dark:hover:text-rose-300">
                                        <i :data-lucide="deletingHistory === item.uuid ? 'loader-circle' : 'trash-2'" class="w-3.5 h-3.5" :class="deletingHistory === item.uuid ? 'animate-spin' : ''"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>{{-- /blur wrapper --}}

    {{-- Live kamera HP (getUserMedia rear camera) --}}
    {{-- JANGAN tambah h-screen/h-[100dvh] di sini lagi — sempat dicoba supaya footer tak ketutup
         address bar/gesture-nav, TAPI height eksplisit + inset-0 sekaligus jadi over-constrained:
         browser boleh ABAIKAN 'bottom:0' dari inset-0 & pakai top:0+height murni, jadi kalau
         100vh/100dvh perangkat itu ternyata LEBIH TINGGI dari layar yg benar2 terlihat, footer
         (tombol "Ambil foto") malah terdorong TOTAL ke luar layar & tak bisa di-scroll sama sekali
         — dilaporkan user via screenshot nyata, regresi lebih parah drpd sebelumnya. inset-0 SAJA
         (tanpa height) sudah cukup & paling aman: elemen fixed otomatis pas 4 sisi ke viewport
         sungguhan tanpa perlu hitung vh/dvh sama sekali. overflow-y-auto ditambah sbg jaring
         pengaman terakhir — kalau suatu saat tetap kepanjangan, minimal masih bisa di-scroll. --}}
    <div x-show="ocr.cameraOpen" x-cloak
         class="fixed inset-0 z-[90] flex flex-col overflow-y-auto bg-black"
         @keydown.escape.window="if (ocr.cameraOpen) stopOcrCamera()">
        <div class="flex items-center justify-between gap-2 px-4 py-3 text-white safe-top">
            <div class="min-w-0">
                <p class="text-sm font-bold">Kamera · foto buku</p>
                <p class="text-[11px] text-white/70">Arahkan ke halaman · jaga fokus · tap Ambil foto</p>
            </div>
            <button type="button" @click="stopOcrCamera()"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="relative min-h-0 flex-1 bg-black">
            <video x-ref="ocrCameraVideo" autoplay playsinline muted
                   class="absolute inset-0 h-full w-full object-cover"></video>
            <div class="pointer-events-none absolute inset-6 rounded-2xl border-2 border-white/35 shadow-[0_0_0_9999px_rgba(0,0,0,0.25)]"></div>
            <p class="absolute bottom-24 left-0 right-0 text-center text-xs font-semibold text-white drop-shadow"
               x-show="ocr.cameraError" x-text="ocr.cameraError"></p>
        </div>
        {{-- shrink-0 mencegah baris tombol ini ikut "digilas" oleh area video flex-1 di atasnya;
             pb pakai env(safe-area-inset-bottom) supaya tombol shutter tak tertutup gesture-bar HP. --}}
        <div class="flex items-center justify-center gap-6 px-4 py-5 pb-[max(2rem,env(safe-area-inset-bottom))] bg-black shrink-0">
            <button type="button" @click="stopOcrCamera()"
                    class="inline-flex h-12 items-center gap-2 rounded-full bg-white/15 px-5 text-sm font-bold text-white">
                Batal
            </button>
            <button type="button" @click="captureOcrFromVideo()"
                    class="grid h-16 w-16 place-items-center rounded-full border-4 border-white bg-primary shadow-lg"
                    title="Ambil foto">
                <span class="h-12 w-12 rounded-full bg-white"></span>
            </button>
            <button type="button" @click="openOcrCameraNativeFallback(ocr.cameraScope)"
                    class="inline-flex h-12 items-center gap-2 rounded-full bg-white/15 px-4 text-xs font-bold text-white">
                App kamera
            </button>
        </div>
    </div>

    {{-- Modal Arena di luar card Hasil agar fixed tidak ter-clip overflow --}}
    <div x-show="showArenaModal" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4" @keydown.escape.window="showArenaModal = false">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-5 space-y-4" @click.outside="showArenaModal = false">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <i data-lucide="gamepad-2" class="w-5 h-5 text-primary"></i>
                    Kirim ke Arena Belajar
                </h3>
                <p class="text-xs text-slate-500 mt-1">Pilih ruang kelas. Soal dari Nalar Guru / Generator Soal akan diimpor ke form buat kuis Arena.</p>
            </div>
            <div>
                <label class="form-label">Ruang kelas</label>
                <select x-model="arenaClassroomId" class="form-input">
                    <option value="">— pilih —</option>
                    <template x-for="c in arenaClassrooms" :key="c.uuid">
                        <option :value="c.uuid" x-text="c.title"></option>
                    </template>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary px-3 py-2 rounded-xl text-sm" @click="showArenaModal = false">Batal</button>
                <button type="button" class="btn-primary px-3 py-2 rounded-xl text-sm font-semibold disabled:opacity-40"
                        :disabled="!arenaClassroomId || sendingArena" @click="sendToArena()">
                    Buka form Arena
                </button>
            </div>
        </div>
    </div>
</div>

@include('partials.ai-markdown')

<script>
    function teacherAi() {
        return {
            tab: @js(in_array(request('tab'), ['gemini', 'quiz', 'learning', 'summary', 'feedback'], true) ? request('tab') : 'gemini'),
            loading: false,
            exportingWord: false,
            exportingPdf: false,
            exportNotice: '',
            exportNoticeTimer: null,
            result: '',
            resultSource: null, // 'generate' | 'ocr' | null
            error: '',
            copied: false,
            editing: false,
            previewHtml: '',      // dokumen berformat: soal (tab quiz) atau RPM (tab learning)
            previewLoading: false,
            deletingHistory: '',  // uuid item history yang sedang dihapus
            histories: @js($histories ?? []),
            quota: @js($quotaUsage ?? null),
            quotaLoading: false,
            quotaTimer: null,
            arenaBelajarAktif: @js((bool) ($arenaBelajarAktif ?? false)),
            arenaClassrooms: @js($arenaClassrooms ?? []),
            arenaClassroomId: '',
            showArenaModal: false,
            sendingArena: false,
            _arenaFromNalar: false,
            copiedMessageKey: '',
            copiedMessageTimer: null,
            launcherAktif: @js((bool) ($launcherAktif ?? true)),
            needsApiKeySetup: @js((bool) ($needsApiKeySetup ?? true)),
            external: {
                has_gemini_api_key: @js((bool) ($externalAccounts['has_gemini_api_key'] ?? false)),
                gemini_api_key_masked: @js($externalAccounts['gemini_api_key_masked'] ?? null),
            },
            canva: @js($canvaStatus ?? [
                'configured' => false,
                'feature_enabled' => true,
                'connected' => false,
                'email_masked' => null,
                'display_name' => null,
                'allowed_email_suffix' => '.belajar.id',
                'belajar_hint' => null,
                'connected_at' => null,
            ]),
            canvaBusy: false,
            canvaError: '',
            canvaMessage: '',
            belajarIdInput: @js(($canvaStatus['belajar_hint'] ?? null) ?: ''),
            apiKeyInput: '',
            apiKeySaving: false,
            apiKeyError: '',
            showReplaceKey: false,
            externalSaved: false,
            externalMessage: '',
            externalFlow: false,
            externalTitle: '',
            externalTool: '',
            externalPaste: '',
            externalGeminiUrl: 'https://gemini.google.com/app',
            promptCopied: false,
            applyingExternal: false,
            tabs: [
                { key: 'gemini',   label: 'Nalar Guru',      icon: 'brain' },
                { key: 'quiz',     label: 'Generator Soal',  icon: 'file-question' },
                { key: 'learning', label: 'RPM Learning',    icon: 'clipboard-list' },
                { key: 'summary',  label: 'Perangkum Materi', icon: 'list-collapse' },
                { key: 'feedback', label: 'Catatan Siswa',  icon: 'message-square-heart' },
            ],
            geminiMessages: [],
            geminiInput: '',
            geminiLoading: false,
            geminiError: '',
            geminiSuggestions: [
                'Buatkan 5 soal pilihan ganda fotosintesis tingkat sedang untuk kelas 7',
                'Buat 8 soal campuran PG dan isian tentang pecahan, mudah, kelas 5 SD',
                'Jelaskan cara membuat rubrik penilaian proyek singkat',
            ],
            get isToolTab() {
                return this.tab !== 'gemini';
            },
            quizTypeOptions: [
                { value: 'pg_kompleks', label: 'Pilihan Ganda Kompleks' },
                { value: 'pg', label: 'Pilihan Ganda' },
                { value: 'benar_salah', label: 'Benar/Salah' },
                { value: 'mencocokkan', label: 'Mencocokkan' },
                { value: 'isian', label: 'Isian' },
            ],
            outputLanguageOptions: @json(
                collect(\App\Support\TeacherOutputLanguage::OPTIONS)
                    ->map(fn ($label, $code) => ['value' => $code, 'label' => $label])
                    ->values()
            ),
            hsk1TopicExamples: @json(\App\Support\TeacherOutputLanguage::hsk1TopicExamples()),
            // Seq guards: ignore stale async generate/preview after tab switch or newer request.
            generateSeq: 0,
            previewSeq: 0,
            quiz:     { topik: '', jumlah: 5, jenis_soal: ['pg'], tingkat: 'sedang', jenjang: '', source: 'ai', file: null, fileName: '', document_uuid: '', soal_bergambar: false, output_language: 'id', include_pinyin: false },
            materials: @json($teacherMaterials ?? []),
            materialsTimer: null,
            learning: { tool: 'rpp', topik: '', mapel: '', jenjang: '', durasi: '', source: 'ai', file: null, fileName: '', document_uuid: '', output_language: 'id', include_pinyin: false },
            materialError: null,
            summary:  { materi: '' },
            feedback: { nama: '', konteks: '' },
            ocr: {
                loading: false,
                quiz: { images: [], text: '', error: '', notice: '' },
                learning: { images: [], text: '', error: '', notice: '' },
                maxImages: {{ (int) config('ai.ocr.max_images', 3) }},
                blurMin: {{ (int) config('ai.ocr.blur_variance_min', 100) }},
                maxEdge: {{ (int) config('ai.ocr.client_max_edge', 1920) }},
                jpegQuality: {{ (float) config('ai.ocr.client_jpeg_quality', 0.90) }},
                maxBytes: {{ (int) config('ai.ocr.max_bytes', 4 * 1024 * 1024) }},
                targetBytes: {{ (int) min(1.2 * 1024 * 1024, (int) config('ai.ocr.max_bytes', 4 * 1024 * 1024)) }},
                maxChars: {{ max(4000, (int) config('ai.max_input_chars', 8000) * 2) }},
                cameraOpen: false,
                cameraScope: 'quiz',
                cameraStream: null,
                cameraError: '',
                cameraSwitching: false,
            },
            urls: {
                quiz:     '{{ route('ai.teacher.quiz') }}',
                learning: '{{ route('ai.teacher.learning') }}',
                summary:  '{{ route('ai.teacher.summary') }}',
                feedback: '{{ route('ai.teacher.feedback') }}',
                quota:    '{{ route('ai.teacher.quota') }}',
                materials: '{{ route('ai.teacher.materials') }}',
                materialsCancel: '{{ url('/ai/teacher/materials') }}',
                ocr: '{{ route('ai.teacher.ocr') }}',
                historyBase: '{{ url('ai/teacher/history') }}',
                quizPreview: '{{ route('ai.teacher.quiz.preview') }}',
                quizWord: '{{ route('ai.teacher.quiz.export-word') }}',
                quizPdf: '{{ route('ai.teacher.quiz.export-pdf') }}',
                quizSendArena: '{{ route('ai.teacher.quiz.send-arena') }}',
                learningPreview: '{{ route('ai.teacher.learning.preview') }}',
                learningWord: '{{ route('ai.teacher.learning.export-word') }}',
                learningPdf: '{{ route('ai.teacher.learning.export-pdf') }}',
                geminiKey: '{{ route('ai.teacher.gemini-key') }}',
                canvaStatus: '{{ route('ai.teacher.canva.status') }}',
                canvaDisconnect: '{{ route('ai.teacher.canva.disconnect') }}',
                canvaBelajarId: '{{ route('ai.teacher.canva.belajar-id') }}',
                externalPrompt: '{{ route('ai.teacher.external-prompt') }}',
                externalResult: '{{ route('ai.teacher.external-result') }}',
                chat: '{{ route('ai.teacher.chat') }}',
            },

            init() {
                this.startQuotaPolling();
                this.scheduleMaterialPolling();
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        this.refreshQuota(true);
                        if (this.quiz.source === 'file') this.loadMaterials();
                    }
                });
                this.$nextTick(() => {
                    window.lucide && lucide.createIcons();
                    if (this.needsApiKeySetup && this.$refs.apiKeyGateInput) {
                        this.$refs.apiKeyGateInput.focus();
                    }
                });
            },

            topicPlaceholder(lang) {
                const map = {
                    id: 'mis. Bab 5 — Ekosistem, Fotosintesis, Pecahan...',
                    'zh-CN': 'mis. 第三课 打招呼, 数字和时间, 我的爱好',
                    en: 'e.g. Linear Equations, Photosynthesis, Reading Comprehension',
                    ja: '例: 自己紹介, 数字と時間',
                };
                return map[lang] || map.id;
            },

            applyMaterialErrorPayload(d, tool) {
                if (d.suggest_camera || d.error_code === 'material_extract_failed') {
                    this.materialError = {
                        message: d.message || 'File tidak bisa dibaca.',
                        hint: d.hint || '',
                        suggest_camera: !!d.suggest_camera,
                        tool: tool,
                    };
                    if (tool === 'quiz' && this.quiz.source === 'file') {
                        this.tab = 'quiz';
                    }
                    if (tool === 'learning' && this.learning.source === 'file') {
                        this.tab = 'learning';
                    }
                } else {
                    this.materialError = null;
                }
            },

            clearMaterialError() {
                this.materialError = null;
            },

            switchToCameraFromMaterialError() {
                const tool = this.materialError?.tool || (this.tab === 'learning' ? 'learning' : 'quiz');
                if (tool === 'learning') {
                    this.learning.source = 'camera';
                    this.clearLearningFile();
                } else {
                    this.quiz.source = 'camera';
                    this.quiz.document_uuid = '';
                    this.clearQuizFile();
                }
                this.materialError = null;
                this.error = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            canSubmitQuiz() {
                if ((this.quiz.topik || '').trim() === '') return false;
                if (this.quiz.source === 'file') return !!(this.quiz.file || this.quiz.document_uuid);
                // Foto buku: cukup ada foto tajam siap pakai — OCR jalan otomatis saat "Buat Soal"
                // ditekan (lihat submit()), tak perlu ocr.quiz.text sudah terisi lebih dulu.
                if (this.quiz.source === 'camera') return !!(this.ocr.quiz.text || '').trim() || this.ocrHasUsable('quiz');
                return true;
            },

            selectedMaterial() {
                if (!this.quiz.document_uuid) return null;
                return this.materials.find((m) => m.uuid === this.quiz.document_uuid) || null;
            },

            selectMaterial(m) {
                this.quiz.document_uuid = m.uuid;
                this.clearQuizFile(false);
                this.error = '';
                this.scheduleMaterialPolling();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            selectLearningMaterial(m) {
                this.learning.document_uuid = m.uuid;
                this.clearLearningFile(false);
                this.error = '';
                this.scheduleMaterialPolling();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            selectedLearningMaterial() {
                if (!this.learning.document_uuid) return null;
                return this.materials.find((m) => m.uuid === this.learning.document_uuid) || null;
            },

            async loadMaterials() {
                if (!this.urls.materials) return;
                try {
                    const r = await fetch(this.urls.materials, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const d = await r.json().catch(() => ({}));
                    if (r.ok && d.ok && Array.isArray(d.materials)) {
                        this.materials = d.materials;
                        this.$nextTick(() => window.lucide && lucide.createIcons());
                    }
                } catch (_) { /* diam: daftar buku opsional */ }
                this.scheduleMaterialPolling();
            },

            scheduleMaterialPolling() {
                if (this.materialsTimer) {
                    clearTimeout(this.materialsTimer);
                    this.materialsTimer = null;
                }
                const busy = this.materials.some((m) => m.status === 'pending' || m.status === 'partial');
                if (!busy || this.quiz.source !== 'file') return;
                this.materialsTimer = setTimeout(() => this.loadMaterials(), 15000);
            },

            clearGeminiChat() {
                this.geminiMessages = [];
                this.geminiError = '';
                this.geminiInput = '';
            },

            async sendGeminiChat() {
                const message = (this.geminiInput || '').trim();
                if (!message || this.geminiLoading) return;
                if (this.needsApiKeySetup) {
                    this.geminiError = 'Simpan API key Gemini terlebih dahulu.';
                    return;
                }
                this.geminiError = '';
                this.geminiMessages.push({ role: 'user', text: message });
                this.geminiInput = '';
                this.geminiLoading = true;
                this.$nextTick(() => {
                    window.lucide && lucide.createIcons();
                    const el = this.$refs.geminiScroll;
                    if (el) el.scrollTop = el.scrollHeight;
                });
                try {
                    const history = this.geminiMessages.slice(0, -1).map(m => ({ role: m.role, text: m.text }));
                    const r = await fetch(this.urls.chat, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ message, history }),
                    });
                    const d = await r.json().catch(() => ({}));
                    this.updateQuota(d.quota);
                    if (!r.ok || !d.ok) {
                        if (d.needs_api_key) this.needsApiKeySetup = true;
                        this.geminiError = d.message || 'Gagal mendapatkan jawaban Nalar Guru.';
                        this.geminiMessages.pop();
                        return;
                    }
                    const answer = this.tidyNalarAnswer(d.answer || '');
                    const msg = { role: 'assistant', text: answer, previewHtml: '' };
                    this.geminiMessages.push(msg);
                    if (d.history) this.histories.unshift(d.history);
                    await this.attachQuizPreviewToMessage(msg);
                } catch (_) {
                    this.geminiError = 'Koneksi gagal. Coba lagi.';
                    this.geminiMessages.pop();
                } finally {
                    this.geminiLoading = false;
                    this.$nextTick(() => {
                        window.lucide && lucide.createIcons();
                        const el = this.$refs.geminiScroll;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            },

            async launchExternalGemini(d) {
                this.externalFlow = true;
                this.externalTitle = d.title || '';
                this.externalTool = d.tool || this.externalTool || '';
                this.externalGeminiUrl = d.gemini_url || 'https://gemini.google.com/app';
                this.externalPaste = '';
                this.promptCopied = false;
                this.error = '';
                this.result = '';
                this.previewHtml = '';
                try {
                    await navigator.clipboard.writeText(d.prompt || '');
                    this.promptCopied = true;
                } catch (_) {
                    this.promptCopied = false;
                    this.error = 'Gagal menyalin otomatis. Salin manual dari riwayat perintah bila perlu.';
                }
                window.open(this.externalGeminiUrl, '_blank', 'noopener,noreferrer');
            },

            reopenExternalGemini() {
                if (this.externalGeminiUrl) {
                    window.open(this.externalGeminiUrl, '_blank', 'noopener,noreferrer');
                }
            },

            async applyExternalResult() {
                const answer = (this.externalPaste || '').trim();
                if (!answer || this.applyingExternal) return;
                const tool = this.externalTool || this.tab || 'quiz';
                this.applyingExternal = true;
                this.error = '';
                try {
                    const r = await fetch(this.urls.externalResult, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            tool,
                            title: this.externalTitle || '',
                            answer,
                        }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.error = d.message || 'Gagal menyimpan hasil dari Gemini.';
                        this.geminiError = this.error;
                        return;
                    }
                    this.result = this.tidyNalarAnswer(d.answer || answer);
                    this.externalFlow = false;
                    this.externalPaste = '';
                    this.editing = false;
                    if (d.history) this.addHistory(d.history);
                    if (tool === 'chat') {
                        // ganti pesan awaiting dengan jawaban asli
                        const idx = [...this.geminiMessages].map((m, i) => ({ m, i })).reverse().find(x => x.m.awaitingPaste)?.i;
                        if (idx !== undefined) {
                            this.geminiMessages[idx] = { role: 'assistant', text: this.result, previewHtml: '' };
                            await this.attachQuizPreviewToMessage(this.geminiMessages[idx]);
                        } else {
                            const msg = { role: 'assistant', text: this.result, previewHtml: '' };
                            this.geminiMessages.push(msg);
                            await this.attachQuizPreviewToMessage(msg);
                        }
                        this.tab = 'gemini';
                    } else {
                        this.tab = tool === 'learning' ? 'learning' : (tool === 'summary' ? 'summary' : (tool === 'feedback' ? 'feedback' : 'quiz'));
                        if (tool === 'learning' || tool === 'quiz') await this.refreshPreview();
                    }
                } catch (_) {
                    this.error = 'Gagal terhubung saat menyimpan hasil.';
                } finally {
                    this.applyingExternal = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            async attachQuizPreviewToMessage(msg) {
                if (!msg?.text || !this.urls.quizPreview) return;
                // Hanya pratinjau bila teks tampak seperti dokumen soal (kop / SOAL EVALUASI / kunci).
                if (!this.looksLikeQuizDocument(msg.text)) return;
                try {
                    const r = await fetch(this.urls.quizPreview, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ content: msg.text }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (r.ok && d.ok && d.html && d.parsed === true) {
                        msg.previewHtml = d.html;
                    }
                } catch (_) {
                    // biarkan teks polos
                }
            },

            useGeminiAsQuizResult(msg) {
                if (!msg?.text) return;
                this.result = msg.text;
                this.resultSource = 'generate';
                this.tab = 'quiz';
                this.editing = false;
                this.error = '';
                this.refreshPreview();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            /** Rapikan jawaban Nalar agar siap disalin (teks polos, spasi konsisten). */
            tidyNalarAnswer(raw) {
                let text = String(raw || '').replace(/\r\n?/g, '\n').trim();
                if (!text) return '';

                // Lepas Markdown ringan yang sering bocor meski sudah diminta teks polos.
                text = text
                    .replace(/^#{1,6}\s+/gm, '')
                    .replace(/\*\*(.*?)\*\*/g, '$1')
                    .replace(/__(.*?)__/g, '$1')
                    .replace(/\*([^*\n]+)\*/g, '$1')
                    // Hanya _italic_ berbatas kata; jangan makan snake_case (nilai_rata_rata).
                    .replace(/(^|[\s(])_([^_\s][^_\n]*)_(?=[\s).,]|$)/g, '$1$2')
                    .replace(/`([^`\n]+)`/g, '$1')
                    .replace(/^>\s?/gm, '')
                    .replace(/```[\s\S]*?```/g, (block) => block.replace(/```\w*\n?/g, '').trim());

                // Rapikan spasi & baris kosong (maks 1 baris kosong antar blok).
                text = text
                    .replace(/[ \t]+\n/g, '\n')
                    .replace(/\n{3,}/g, '\n\n')
                    .trim();

                return text;
            },

            geminiMessageKey(msg) {
                if (!msg) return '';
                return String(msg.role || '') + ':' + String(msg.text || '').slice(0, 80);
            },

            copyGeminiMessage(msg) {
                const text = this.tidyNalarAnswer(msg?.text || '');
                if (!text) return;
                navigator.clipboard.writeText(text).then(() => {
                    if (this.copiedMessageTimer) clearTimeout(this.copiedMessageTimer);
                    this.copiedMessageKey = this.geminiMessageKey(msg);
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                    this.copiedMessageTimer = setTimeout(() => {
                        this.copiedMessageKey = '';
                        this.$nextTick(() => window.lucide && lucide.createIcons());
                    }, 2000);
                });
            },

            async saveGeminiApiKey() {
                if (this.apiKeySaving) return;
                const key = (this.apiKeyInput || '').trim();
                if (!key) {
                    this.apiKeyError = 'API key wajib diisi.';
                    return;
                }
                this.apiKeySaving = true;
                this.apiKeyError = '';
                try {
                    const r = await fetch(this.urls.geminiKey, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ gemini_api_key: key }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.apiKeyError = d.message || 'Gagal menyimpan API key.';
                        return;
                    }
                    this.external.has_gemini_api_key = true;
                    this.external.gemini_api_key_masked = d.accounts?.gemini_api_key_masked || null;
                    this.needsApiKeySetup = false;
                    this.apiKeyInput = '';
                    this.showReplaceKey = false;
                    this.externalMessage = d.message || 'API key disimpan.';
                    this.externalSaved = true;
                    setTimeout(() => { this.externalSaved = false; }, 3000);
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.apiKeyError = 'Gagal menyimpan. Coba lagi.';
                } finally {
                    this.apiKeySaving = false;
                }
            },

            async deleteGeminiApiKey() {
                if (this.apiKeySaving) return;
                if (!confirm('Hapus API key Gemini dari akun SIMS Anda?')) return;
                this.apiKeySaving = true;
                this.apiKeyError = '';
                try {
                    const r = await fetch(this.urls.geminiKey, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.apiKeyError = d.message || 'Gagal menghapus API key.';
                        return;
                    }
                    this.external.has_gemini_api_key = false;
                    this.external.gemini_api_key_masked = null;
                    this.needsApiKeySetup = true;
                    this.showReplaceKey = false;
                    this.externalMessage = d.message || 'API key dihapus.';
                    this.externalSaved = true;
                    setTimeout(() => { this.externalSaved = false; }, 3000);
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.apiKeyError = 'Gagal menghapus. Coba lagi.';
                } finally {
                    this.apiKeySaving = false;
                }
            },

            async saveBelajarId() {
                if (this.canvaBusy) return;
                this.canvaBusy = true;
                this.canvaError = '';
                this.canvaMessage = '';
                try {
                    const r = await fetch(this.urls.canvaBelajarId, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ canva_belajar_id: (this.belajarIdInput || '').trim() }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.canvaError = d.message || (d.errors?.canva_belajar_id?.[0]) || 'Email belajar.id ditolak.';
                        return;
                    }
                    this.canva = Object.assign({}, this.canva, d.canva || {});
                    this.belajarIdInput = this.canva.belajar_hint || this.belajarIdInput;
                    this.canvaMessage = d.message || 'Email belajar.id disimpan.';
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.canvaError = 'Gagal menyimpan email.';
                } finally {
                    this.canvaBusy = false;
                }
            },

            async disconnectCanva() {
                if (this.canvaBusy) return;
                if (!confirm('Putuskan tautan Canva Pendidikan dari akun SIMS?')) return;
                this.canvaBusy = true;
                this.canvaError = '';
                this.canvaMessage = '';
                try {
                    const r = await fetch(this.urls.canvaDisconnect, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.canvaError = d.message || 'Gagal memutus Canva.';
                        return;
                    }
                    this.canva = Object.assign({}, this.canva, d.canva || { connected: false, email_masked: null });
                    this.canvaMessage = d.message || 'Tautan Canva diputus.';
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.canvaError = 'Gagal terhubung.';
                } finally {
                    this.canvaBusy = false;
                }
            },

            startQuotaPolling() {
                if (this.quotaTimer) clearInterval(this.quotaTimer);
                // 15s (was 10s); skip tick saat tab hidden
                this.quotaTimer = setInterval(() => {
                    if (document.hidden) return;
                    this.refreshQuota(false);
                }, 15000);
            },

            async refreshQuota(fresh = false) {
                if (this.quotaLoading) return;
                if (!fresh && document.hidden) return;
                this.quotaLoading = true;
                try {
                    const url = this.urls.quota + (fresh ? '?fresh=1' : '');
                    const r = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const d = await r.json();
                    if (r.ok && d.quota) this.updateQuota(d.quota);
                } catch (_) {
                    // diam: polling gagal tidak mengganggu form
                } finally {
                    this.quotaLoading = false;
                }
            },

            selectTab(key) {
                this.tab = key;
                if (this.isToolTab) {
                    // Batalkan writer dari generate/preview yang masih in-flight.
                    this.generateSeq++;
                    this.previewSeq++;
                    this.loading = false;
                    this.clearResult();
                    this.error = '';
                }
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            setQuizFile(event) {
                const file = event.target.files[0] || null;
                this.quiz.file = file;
                this.quiz.fileName = file ? file.name : '';
                if (file) {
                    this.quiz.document_uuid = '';
                    this.clearMaterialError();
                }
                this.error = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            clearQuizFile(keepDocument = true) {
                this.quiz.file = null;
                this.quiz.fileName = '';
                if (!keepDocument) { /* document_uuid sudah di-set pemanggil */ }
                if (this.$refs.quizFile) this.$refs.quizFile.value = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            setLearningFile(event) {
                const file = event.target.files[0] || null;
                this.learning.file = file;
                this.learning.fileName = file ? file.name : '';
                if (file) {
                    this.learning.document_uuid = '';
                    this.clearMaterialError();
                }
                this.error = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            clearLearningFile(keepDocument = true) {
                this.learning.file = null;
                this.learning.fileName = '';
                // keepDocument=false: document_uuid sudah di-set pemanggil (select material)
                // — jangan dihapus, samakan perilaku clearQuizFile.
                if (!keepDocument) { /* document_uuid sudah di-set pemanggil */ }
                if (this.$refs.learningFile) this.$refs.learningFile.value = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            learningSourceReady() {
                if (this.learning.source === 'file') return !!(this.learning.file || this.learning.document_uuid);
                if (this.learning.source === 'camera') return !!(this.ocr.learning.text || '').trim();
                return (this.learning.topik || '').trim() !== '';
            },
            ocrHasUsable(scope) {
                const imgs = this.ocr[scope]?.images || [];
                return imgs.some((i) => !i.blurry || i.forceKeep);
            },
            removeOcrImage(scope, idx) {
                const img = this.ocr[scope].images[idx];
                if (img?.preview) URL.revokeObjectURL(img.preview);
                this.ocr[scope].images.splice(idx, 1);
                this.ocr[scope].error = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },
            stopOcrCamera() {
                const stream = this.ocr.cameraStream;
                if (stream) {
                    stream.getTracks().forEach((t) => t.stop());
                }
                this.ocr.cameraStream = null;
                this.ocr.cameraOpen = false;
                this.ocr.cameraError = '';
                const v = this.$refs.ocrCameraVideo;
                if (v) v.srcObject = null;
            },
            openOcrCameraNativeFallback(scope) {
                const ref = scope === 'learning' ? this.$refs.ocrCameraNativeLearning : this.$refs.ocrCameraNativeQuiz;
                if (ref) {
                    ref.value = '';
                    ref.click();
                } else {
                    this.ocr[scope].error = 'Kamera tidak tersedia di perangkat ini. Pakai “Dari galeri”.';
                }
            },
            async openOcrCamera(scope) {
                this.ocr.cameraScope = scope;
                this.ocr.cameraError = '';
                this.ocr[scope].error = '';

                // Desktop / non-secure: getUserMedia sering gagal → native capture (HP) atau file
                const canLive = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
                    && (window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1');

                if (!canLive) {
                    this.openOcrCameraNativeFallback(scope);
                    return;
                }

                try {
                    // Hentikan stream lama dulu
                    this.stopOcrCamera();
                    const stream = await navigator.mediaDevices.getUserMedia({
                        audio: false,
                        video: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1920 },
                            height: { ideal: 1080 },
                        },
                    });
                    this.ocr.cameraStream = stream;
                    this.ocr.cameraOpen = true;
                    this.$nextTick(() => {
                        const v = this.$refs.ocrCameraVideo;
                        if (v) {
                            v.srcObject = stream;
                            v.setAttribute('playsinline', 'true');
                            v.muted = true;
                            v.play().catch(() => {});
                        }
                        window.lucide && lucide.createIcons();
                    });
                } catch (err) {
                    // Izin ditolak / tidak ada kamera → native capture di HP
                    this.ocr.cameraOpen = false;
                    this.openOcrCameraNativeFallback(scope);
                }
            },
            async captureOcrFromVideo() {
                const scope = this.ocr.cameraScope || 'quiz';
                const video = this.$refs.ocrCameraVideo;
                if (!video || !video.videoWidth) {
                    this.ocr.cameraError = 'Kamera belum siap. Tunggu sebentar lalu coba lagi.';
                    return;
                }
                const max = this.ocr.maxImages || 3;
                if ((this.ocr[scope].images || []).length >= max) {
                    this.ocr[scope].error = `Maksimal ${max} foto. Hapus salah satu dulu.`;
                    this.stopOcrCamera();
                    return;
                }
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0);
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
                if (!blob) {
                    this.ocr.cameraError = 'Gagal mengambil foto. Coba lagi.';
                    return;
                }
                const file = new File([blob], `kamera-${Date.now()}.jpg`, { type: 'image/jpeg' });
                await this.ingestOcrFiles([file], scope);
                this.stopOcrCamera();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },
            async addOcrImages(event, scope) {
                const files = Array.from(event.target.files || []);
                event.target.value = '';
                if (!files.length) return;
                await this.ingestOcrFiles(files, scope);
            },
            formatBytes(n) {
                const v = Number(n) || 0;
                if (v < 1024) return v + ' B';
                if (v < 1024 * 1024) return (v / 1024).toFixed(v < 10 * 1024 ? 1 : 0) + ' KB';
                return (v / (1024 * 1024)).toFixed(1) + ' MB';
            },
            formatNumber(n) {
                try {
                    return Number(n || 0).toLocaleString('id-ID');
                } catch (_) {
                    return String(n || 0);
                }
            },
            clampOcrText(scope) {
                const max = this.ocr.maxChars || 16000;
                let t = this.ocr[scope]?.text || '';
                if (t.length > max) {
                    this.ocr[scope].text = t.slice(0, max);
                    this.ocr[scope].notice = 'Teks dipotong otomatis ke ' + this.formatNumber(max) + ' karakter (batas maksimum).';
                    if (this.resultSource === 'ocr') this.result = this.ocr[scope].text;
                }
            },
            async ingestOcrFiles(files, scope) {
                this.ocr[scope].error = '';
                this.ocr[scope].notice = '';
                const max = this.ocr.maxImages || 3;
                const maxBytes = this.ocr.maxBytes || (4 * 1024 * 1024);
                let convertedCount = 0;
                for (const file of files) {
                    if (this.ocr[scope].images.length >= max) {
                        this.ocr[scope].error = `Maksimal ${max} foto. Hapus salah satu dulu.`;
                        break;
                    }
                    if (file.type && !file.type.startsWith('image/')) {
                        this.ocr[scope].error = 'Format harus gambar (JPEG/PNG/WebP).';
                        continue;
                    }
                    try {
                        const originalSize = file.size || 0;
                        // Selalu re-encode JPEG smart; foto besar / non-JPEG → auto convert.
                        const compressed = await this.compressImageSmart(file, {
                            maxEdge: this.ocr.maxEdge || 1920,
                            quality: this.ocr.jpegQuality || 0.9,
                            maxBytes,
                            targetBytes: this.ocr.targetBytes || Math.min(1.2 * 1024 * 1024, maxBytes),
                        });
                        if (compressed.blob.size > maxBytes) {
                            this.ocr[scope].error = 'Foto masih terlalu besar setelah kompres (maks. '
                                + this.formatBytes(maxBytes) + '). Ambil ulang dari jarak lebih dekat / resolusi lebih rendah.';
                            continue;
                        }
                        const converted = compressed.converted
                            || originalSize > compressed.blob.size * 1.05
                            || (file.type && file.type !== 'image/jpeg');
                        if (converted) convertedCount++;

                        const sharp = await this.scoreImageSharpness(compressed.blob);
                        const blurry = sharp < (this.ocr.blurMin || 100);
                        const preview = URL.createObjectURL(compressed.blob);
                        this.ocr[scope].images.push({
                            id: Date.now() + Math.random(),
                            blob: compressed.blob,
                            preview,
                            name: (file.name || 'foto').replace(/\.\w+$/, '') + '.jpg',
                            blurry,
                            forceKeep: false,
                            sharpScore: Math.round(sharp),
                            sizeKb: Math.round(compressed.blob.size / 1024),
                            originalKb: Math.round(originalSize / 1024),
                            converted,
                        });
                        if (blurry) {
                            this.ocr[scope].error = 'Ada foto buram. Potret ulang atau ketuk “Tetap pakai”.';
                        }
                    } catch (e) {
                        this.ocr[scope].error = 'Gagal memproses foto. Coba ambil ulang.';
                    }
                }
                if (convertedCount > 0 && !this.ocr[scope].error) {
                    this.ocr[scope].notice = convertedCount + ' foto dikonversi/kompres otomatis ke JPEG agar muat batas ukuran.';
                }
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },
            async scoreImageSharpness(file) {
                const bitmap = await createImageBitmap(file);
                const w = 320;
                const scale = w / bitmap.width;
                const h = Math.max(1, Math.round(bitmap.height * scale));
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(bitmap, 0, 0, w, h);
                bitmap.close?.();
                const { data } = ctx.getImageData(0, 0, w, h);
                const gray = new Float32Array(w * h);
                for (let i = 0, p = 0; i < data.length; i += 4, p++) {
                    gray[p] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                }
                let sum = 0, sumSq = 0, n = 0;
                for (let y = 1; y < h - 1; y++) {
                    for (let x = 1; x < w - 1; x++) {
                        const i = y * w + x;
                        const lap = -4 * gray[i] + gray[i - 1] + gray[i + 1] + gray[i - w] + gray[i + w];
                        sum += lap;
                        sumSq += lap * lap;
                        n++;
                    }
                }
                if (!n) return 0;
                const mean = sum / n;
                return (sumSq / n) - (mean * mean);
            },
            async compressImageSmart(file, opts = {}) {
                const maxBytes = opts.maxBytes || (4 * 1024 * 1024);
                const targetBytes = opts.targetBytes || Math.min(1.2 * 1024 * 1024, maxBytes);
                let maxEdge = opts.maxEdge || 1920;
                let quality = Math.min(0.95, Math.max(0.82, opts.quality || 0.9));
                const bitmap = await createImageBitmap(file);
                const srcW = bitmap.width;
                const srcH = bitmap.height;
                let last = null;

                try {
                    const encode = async (edge, q) => {
                        let width = srcW;
                        let height = srcH;
                        const long = Math.max(width, height);
                        if (long > edge) {
                            const s = edge / long;
                            width = Math.max(1, Math.round(width * s));
                            height = Math.max(1, Math.round(height * s));
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.imageSmoothingEnabled = true;
                        ctx.imageSmoothingQuality = 'high';
                        ctx.drawImage(bitmap, 0, 0, width, height);
                        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', q));
                        if (!blob) throw new Error('compress failed');
                        return { blob, width, height };
                    };

                    for (let attempt = 0; attempt < 8; attempt++) {
                        last = await encode(maxEdge, quality);
                        if (last.blob.size <= targetBytes) break;
                        // Prioritas: kecilkan edge dulu, baru turunkan quality (min ~0.82 jaga ketajaman teks)
                        if (maxEdge > 1600) maxEdge = 1600;
                        else if (maxEdge > 1280) maxEdge = 1280;
                        else if (quality > 0.86) quality = 0.86;
                        else if (maxEdge > 1024) maxEdge = 1024;
                        else if (quality > 0.82) quality = 0.82;
                        else if (maxEdge > 900) maxEdge = 900;
                        else break;
                    }

                    // Hard clamp: jika masih > maxBytes, paksa edge lebih kecil
                    while (last && last.blob.size > maxBytes && maxEdge > 640) {
                        maxEdge = Math.round(maxEdge * 0.85);
                        quality = Math.max(0.8, quality - 0.02);
                        last = await encode(maxEdge, quality);
                    }
                } finally {
                    bitmap.close?.();
                }

                const originalSize = file.size || 0;
                return {
                    blob: last.blob,
                    width: last.width,
                    height: last.height,
                    converted: true,
                    originalSize,
                    finalSize: last.blob.size,
                };
            },
            /** @return {Promise<boolean>} true kalau teks berhasil didapat dari foto. */
            async runOcr(scope) {
                if (this.ocr.loading) return false;
                if (this.needsApiKeySetup) {
                    this.ocr[scope].error = 'Simpan API key Gemini terlebih dahulu.';
                    return false;
                }
                const usable = (this.ocr[scope].images || []).filter((i) => !i.blurry || i.forceKeep);
                if (!usable.length) {
                    this.ocr[scope].error = 'Tambah foto tajam dulu, atau potret ulang yang buram.';
                    return false;
                }
                this.ocr.loading = true;
                this.ocr[scope].error = '';
                let ok = false;
                try {
                    const form = new FormData();
                    usable.forEach((img, i) => form.append('images[]', img.blob, img.name || `halaman-${i + 1}.jpg`));
                    form.append('scope', scope === 'learning' ? 'learning' : 'quiz');
                    if (scope === 'learning' && (this.learning.topik || '').trim()) {
                        form.append('title', 'Scan buku · ' + this.learning.topik.trim());
                    } else if (scope === 'quiz' && (this.quiz.topik || '').trim()) {
                        form.append('title', 'Scan buku · ' + this.quiz.topik.trim());
                    }
                    const r = await fetch(this.urls.ocr, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: form,
                    });
                    const d = await r.json().catch(() => ({}));
                    this.updateQuota(d.quota);
                    if (r.ok && d.ok) {
                        let text = d.text || '';
                        const maxChars = this.ocr.maxChars || 16000;
                        let notice = '';
                        if (text.length > maxChars) {
                            text = text.slice(0, maxChars);
                            notice = 'Teks dipotong otomatis ke ' + this.formatNumber(maxChars) + ' karakter (batas maksimum).';
                        }
                        this.ocr[scope].text = text;
                        this.ocr[scope].error = '';
                        this.ocr[scope].notice = notice || this.ocr[scope].notice || '';
                        ok = !!text.trim();
                        // Tampilkan di panel Hasil (besar, edit, salin, export Word/PDF).
                        this.result = text;
                        this.resultSource = 'ocr';
                        this.previewHtml = '';
                        this.editing = false;
                        this.error = '';
                        this.externalFlow = false;
                        this.copied = false;
                        if (d.history) this.addHistory(d.history);
                        this.$nextTick(() => {
                            if (this.$refs.hasilScrollBody) this.$refs.hasilScrollBody.scrollTop = 0;
                            window.lucide && lucide.createIcons();
                        });
                    } else {
                        this.ocr[scope].error = d.message || 'Gagal membaca teks dari foto.';
                        if (d.needs_api_key) this.needsApiKeySetup = true;
                    }
                } catch (_) {
                    this.ocr[scope].error = 'Gagal terhubung. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.ocr.loading = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
                return ok;
            },
            /** Sinkron edit di panel Hasil → material_text OCR (agar generate pakai teks yang diedit). */
            syncOcrFromResult() {
                if (this.resultSource !== 'ocr') return;
                const scope = this.tab === 'learning' ? 'learning' : 'quiz';
                if (this.ocr[scope]) this.ocr[scope].text = this.result || '';
            },
            /** Sinkron textarea kecil di form → panel Hasil. */
            syncResultFromOcr(scope) {
                if (this.resultSource !== 'ocr' && !(this.result || '').trim()) {
                    this.resultSource = 'ocr';
                }
                if (this.resultSource === 'ocr') {
                    this.result = this.ocr[scope]?.text || '';
                    this.previewHtml = '';
                }
            },

            payloadFor(tool) {
                if (tool === 'summary' || tool === 'feedback') {
                    return {
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify(this[tool]),
                    };
                }

                const form = new FormData();
                if (tool === 'learning') {
                    form.append('tool', this.learning.tool);
                    form.append('topik', this.learning.topik || '');
                    form.append('mapel', this.learning.mapel || '');
                    form.append('jenjang', this.learning.jenjang || '');
                    form.append('durasi', this.learning.durasi || '');
                    form.append('output_language', this.learning.output_language || 'id');
                    if (this.learning.include_pinyin) form.append('include_pinyin', '1');
                    if (this.learning.source === 'file' && this.learning.file) form.append('file', this.learning.file);
                    if (this.learning.source === 'file' && this.learning.document_uuid) form.append('document_uuid', this.learning.document_uuid);
                    if (this.learning.source === 'camera' && (this.ocr.learning.text || '').trim()) {
                        form.append('material_text', this.ocr.learning.text.trim());
                    }
                } else {
                    form.append('topik', this.quiz.topik || '');
                    form.append('jumlah', this.quiz.jumlah || 1);
                    this.quiz.jenis_soal.forEach((jenis) => form.append('jenis_soal[]', jenis));
                    form.append('tingkat', this.quiz.tingkat);
                    form.append('jenjang', this.quiz.jenjang || '');
                    form.append('soal_bergambar', this.quiz.soal_bergambar ? '1' : '0');
                    form.append('output_language', this.quiz.output_language || 'id');
                    if (this.quiz.include_pinyin) form.append('include_pinyin', '1');
                    if (this.quiz.source === 'file' && this.quiz.file) {
                        form.append('file', this.quiz.file);
                    } else if (this.quiz.source === 'file' && this.quiz.document_uuid) {
                        form.append('document_uuid', this.quiz.document_uuid);
                    } else if (this.quiz.source === 'camera' && (this.ocr.quiz.text || '').trim()) {
                        form.append('material_text', this.ocr.quiz.text.trim());
                    }
                }

                return {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: form,
                };
            },
            async submit(tool) {
                if (this.loading) return;
                if (this.needsApiKeySetup) {
                    this.error = 'Simpan API key Gemini terlebih dahulu.';
                    return;
                }
                const seq = ++this.generateSeq;
                this.loading = true;
                this.result = '';
                this.error = '';
                this.materialError = null;
                this.copied = false;
                this.editing = false;
                this.externalFlow = false;
                this.promptCopied = false;
                try {
                    // Foto buku di Generator Soal: OCR jalan otomatis sbg BAGIAN dari proses "Buat
                    // Soal" — user tak perlu klik "Jadikan teks" manual dulu. Kalau ocr.quiz.text
                    // sudah terisi (mis. user sempat edit manual, atau reload dari histori), pakai
                    // itu apa adanya tanpa OCR ulang.
                    if (tool === 'quiz' && this.quiz.source === 'camera' && !(this.ocr.quiz.text || '').trim()) {
                        const ok = await this.runOcr('quiz');
                        if (seq !== this.generateSeq) return;
                        if (!ok || !(this.ocr.quiz.text || '').trim()) {
                            this.error = this.ocr.quiz.error || 'Gagal membaca teks dari foto.';
                            return;
                        }
                    }
                    const payload = this.payloadFor(tool);
                    const r = await fetch(this.urls[tool], {
                        method: 'POST',
                        headers: payload.headers,
                        body: payload.body,
                    });
                    const d = await r.json().catch(() => ({}));
                    // Tab diganti / generate baru: jangan tulis ulang panel hasil.
                    if (seq !== this.generateSeq) return;
                    this.updateQuota(d.quota);
                    if (r.ok && d.ok) {
                        this.result = d.answer;
                        this.resultSource = 'generate';
                        if (d.history) this.addHistory(d.history);
                        if (d.warning) this.error = d.warning;
                        if (tool === 'quiz' && (d.history?.metadata?.document_uuid || d.history?.meta?.document_uuid)) {
                            this.quiz.document_uuid = d.history.metadata?.document_uuid || d.history.meta.document_uuid;
                        }
                        if (tool === 'learning' && (d.history?.metadata?.document_uuid || d.history?.meta?.document_uuid)) {
                            this.learning.document_uuid = d.history.metadata?.document_uuid || d.history.meta.document_uuid;
                        }
                        if (tool === 'learning' || tool === 'quiz') await this.refreshPreview();
                        if (seq !== this.generateSeq) return;
                        if (tool === 'quiz' && this.quiz.source === 'file') await this.loadMaterials();
                        if (tool === 'learning' && this.learning.source === 'file') await this.loadMaterials();
                        await this.refreshQuota(true);
                    } else if (r.status === 422) {
                        if (d.needs_api_key) this.needsApiKeySetup = true;
                        if (d.document_uuid) {
                            if (tool === 'learning') {
                                this.learning.document_uuid = d.document_uuid;
                                this.clearLearningFile(false);
                            } else {
                                this.quiz.document_uuid = d.document_uuid;
                                this.clearQuizFile(false);
                            }
                            await this.loadMaterials();
                        }
                        if (seq !== this.generateSeq) return;
                        this.applyMaterialErrorPayload(d, tool);
                        if (d.processing) {
                            this.error = 'Materi sedang diproses (embedding). Menunggu 4 detik lalu mencoba membuat soal lagi secara otomatis… (tidak perlu unggah ulang)';
                            setTimeout(() => {
                                if (seq === this.generateSeq) {
                                    this.doGenerate(tool);
                                }
                            }, 4000);
                            return;
                        }
                        this.error = d.message || 'Periksa isian form: ' + Object.values(d.errors || {}).flat().join(' ');
                    } else {
                        this.error = d.message || 'Terjadi kesalahan. Coba lagi.';
                    }
                } catch (_) {
                    if (seq !== this.generateSeq) return;
                    this.error = 'Gagal terhubung. Periksa koneksi lalu coba lagi.';
                } finally {
                    if (seq === this.generateSeq) {
                        this.loading = false;
                        this.$nextTick(() => window.lucide && lucide.createIcons());
                    }
                }
            },

            async submitExternal(tool) {
                if (this.loading) return;
                this.loading = true;
                this.result = '';
                this.error = '';
                this.materialError = null;
                this.copied = false;
                this.editing = false;
                this.externalFlow = false;
                this.promptCopied = false;
                try {
                    const payload = this.payloadForExternal(tool);
                    const r = await fetch(this.urls.externalPrompt, {
                        method: 'POST',
                        headers: payload.headers,
                        body: payload.body,
                    });
                    const d = await r.json().catch(() => ({}));
                    if (r.ok && d.ok) {
                        await this.launchExternalGemini(d);
                        this.externalTool = tool;
                    } else if (r.status === 422) {
                        this.error = d.message || 'Periksa isian form: ' + Object.values(d.errors || {}).flat().join(' ');
                    } else {
                        this.error = d.message || 'Gagal menyiapkan perintah untuk Gemini web.';
                    }
                } catch (_) {
                    this.error = 'Gagal terhubung. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.loading = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            payloadForExternal(tool) {
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                if (tool === 'summary' || tool === 'feedback') {
                    return {
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ tool, ...this[tool] }),
                    };
                }

                const form = new FormData();
                form.append('tool', tool);
                if (tool === 'learning') {
                    form.append('learning_tool', this.learning.tool || 'rpp');
                    form.append('topik', this.learning.topik || '');
                    form.append('mapel', this.learning.mapel || '');
                    form.append('jenjang', this.learning.jenjang || '');
                    form.append('durasi', this.learning.durasi || '');
                    form.append('output_language', this.learning.output_language || 'id');
                    if (this.learning.include_pinyin) form.append('include_pinyin', '1');
                    if (this.learning.source === 'file' && this.learning.file) form.append('file', this.learning.file);
                    if (this.learning.source === 'file' && this.learning.document_uuid) form.append('document_uuid', this.learning.document_uuid);
                } else {
                    form.append('topik', this.quiz.topik || '');
                    form.append('jumlah', this.quiz.jumlah || 1);
                    this.quiz.jenis_soal.forEach((jenis) => form.append('jenis_soal[]', jenis));
                    form.append('tingkat', this.quiz.tingkat);
                    form.append('jenjang', this.quiz.jenjang || '');
                    form.append('soal_bergambar', this.quiz.soal_bergambar ? '1' : '0');
                    form.append('output_language', this.quiz.output_language || 'id');
                    if (this.quiz.include_pinyin) form.append('include_pinyin', '1');
                    if (this.quiz.source === 'file' && this.quiz.file) {
                        form.append('file', this.quiz.file);
                    } else if (this.quiz.source === 'file' && this.quiz.document_uuid) {
                        form.append('document_uuid', this.quiz.document_uuid);
                    }
                }

                return {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: form,
                };
            },

            sendGeminiToArena(msg) {
                if (!msg?.text || !this.arenaBelajarAktif || !this.arenaClassrooms.length || this.sendingArena) return;
                if (!this.looksLikeQuizDocument(msg.text)) {
                    this.error = 'Jawaban ini belum berbentuk soal. Minta Nalar membuat soal (SOAL EVALUASI), atau buka di Generator Soal dulu.';
                    return;
                }
                this.result = msg.text;
                this.openSendToArena({ fromNalar: true });
            },

            looksLikeQuizDocument(text) {
                const t = String(text || '').trim();
                if (!t) return false;
                if (/SOAL EVALUASI|Kunci Jawaban|Bagian\s+[A-Z]\s*-/i.test(t)) return true;
                return /^\s*\d+[\.\)]\s+\S/m.test(t) && /^\s*[A-Da-d][\.\)]\s+\S/m.test(t);
            },

            openSendToArena(opts = {}) {
                if (!this.result || !this.arenaBelajarAktif || !this.arenaClassrooms.length) return;
                if (!this.looksLikeQuizDocument(this.result)) {
                    this.error = 'Teks hasil belum berbentuk soal yang bisa diimpor ke Arena.';
                    return;
                }
                this._arenaFromNalar = !!opts.fromNalar || this.tab === 'gemini';
                if (this.arenaClassrooms.length === 1) {
                    this.arenaClassroomId = this.arenaClassrooms[0].uuid;
                    this.sendToArena();
                    return;
                }
                this.arenaClassroomId = this.arenaClassroomId || this.arenaClassrooms[0].uuid;
                this.showArenaModal = true;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            sendToArena() {
                if (!this.result || !this.arenaClassroomId || this.sendingArena) return;
                this.sendingArena = true;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = this.urls.quizSendArena;
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = document.querySelector('meta[name="csrf-token"]').content;
                form.appendChild(csrf);
                const fromNalar = this._arenaFromNalar || this.tab === 'gemini';
                const defaultTitle = fromNalar
                    ? 'Kuis dari Nalar Guru'
                    : 'Kuis dari Asisten Guru';
                // Jangan pakai quiz.topik Generator Soal saat kirim dari Nalar.
                const title = (! fromNalar && this.quiz.topik)
                    ? ('Kuis: ' + this.quiz.topik)
                    : defaultTitle;
                const fields = {
                    classroom_id: this.arenaClassroomId,
                    raw_text: this.result,
                    title,
                };
                Object.entries(fields).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            },

            /** Deteksi Android WebView APK (; wv) atau bridge native. */
            isAndroidWebView() {
                try {
                    if (window.AndroidFcm || window.AndroidArena || window.AndroidBridge) return true;
                } catch (_) {}
                return /; wv\)/i.test(navigator.userAgent || '') || /\bwv\b/i.test(navigator.userAgent || '');
            },
            isMobileDevice() {
                return this.isAndroidWebView()
                    || /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '')
                    || (window.matchMedia && window.matchMedia('(max-width: 768px)').matches);
            },
            flashExportNotice(msg) {
                this.exportNotice = msg || '';
                if (this.exportNoticeTimer) clearTimeout(this.exportNoticeTimer);
                if (!msg) return;
                this.exportNoticeTimer = setTimeout(() => {
                    this.exportNotice = '';
                    this.exportNoticeTimer = null;
                }, 6000);
            },
            /**
             * Unduh file export — andal di Chrome mobile + Android WebView.
             * WebView: blob+a.download sering DIABAIKAN; form POST ke iframe
             * memicu setDownloadListener (DownloadManager) di APK.
             */
            async downloadExportFile({ url, fields, fileName, mimeHint }) {
                const preferForm = this.isAndroidWebView() || this.isMobileDevice();

                if (preferForm) {
                    this.downloadViaForm(url, fields);
                    this.flashExportNotice(
                        this.isAndroidWebView()
                            ? 'Mengunduh lewat DownloadManager… cek notifikasi / folder Unduhan.'
                            : 'Mengunduh file… cek folder Unduhan atau notifikasi browser.'
                    );
                    return true;
                }

                // Desktop: fetch + blob (lebih cepat, tanpa navigasi).
                try {
                    const title = this.resultSource === 'ocr'
                        ? ('Teks scan buku' + (this.quiz.topik ? ' - ' + this.quiz.topik : ''))
                        : (this.quiz.topik ? 'Soal - ' + this.quiz.topik : 'Soal dari Asisten Guru');
                    const r = await fetch(isPdf ? this.urls.quizPdf : this.urls.quizWord, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': (mimeHint || '*/*') + ',application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(fields),
                    });

                    if (!r.ok) {
                        const d = await r.json().catch(() => ({}));
                        throw new Error(d.message || 'Export gagal. Coba lagi.');
                    }

                    const blob = await r.blob();
                    if (!blob || blob.size < 32) {
                        throw new Error('File export kosong. Coba lagi.');
                    }

                    // Kalau server mengembalikan JSON error tersamar sebagai blob.
                    if ((blob.type || '').includes('json')) {
                        const text = await blob.text();
                        let msg = 'Export gagal.';
                        try { msg = JSON.parse(text).message || msg; } catch (_) {}
                        throw new Error(msg);
                    }

                    const cd = r.headers.get('Content-Disposition') || '';
                    const match = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(cd);
                    const name = match
                        ? decodeURIComponent(match[1].replace(/"/g, '').trim())
                        : fileName;

                    const saved = await this.saveBlobToDevice(blob, name);
                    if (!saved) {
                        // Fallback form bila blob gagal (beberapa browser ketat).
                        this.downloadViaForm(url, fields);
                        this.flashExportNotice('Mengunduh file… cek folder Unduhan.');
                    } else {
                        this.flashExportNotice('File diunduh: ' + name);
                    }
                    return true;
                } catch (e) {
                    // Fallback form juga di desktop jika fetch gagal.
                    try {
                        this.downloadViaForm(url, fields);
                        this.flashExportNotice('Mengunduh file… cek folder Unduhan.');
                        return true;
                    } catch (_) {
                        throw e;
                    }
                }
            },
            downloadViaForm(url, fields) {
                let iframe = document.getElementById('ai-teacher-export-frame');
                if (!iframe) {
                    iframe = document.createElement('iframe');
                    iframe.id = 'ai-teacher-export-frame';
                    iframe.name = 'ai-teacher-export-frame';
                    iframe.setAttribute('aria-hidden', 'true');
                    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;left:-9999px;top:0;opacity:0;pointer-events:none';
                    document.body.appendChild(iframe);
                }

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.target = 'ai-teacher-export-frame';
                form.enctype = 'application/x-www-form-urlencoded';
                form.style.display = 'none';

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                form.appendChild(csrf);

                Object.entries(fields || {}).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value == null ? '' : String(value);
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
                setTimeout(() => form.remove(), 4000);
            },
            async saveBlobToDevice(blob, fileName) {
                // IE/legacy
                if (window.navigator && typeof window.navigator.msSaveOrOpenBlob === 'function') {
                    window.navigator.msSaveOrOpenBlob(blob, fileName);
                    return true;
                }

                // iOS Safari: a.download + blob sering diabaikan → data URL
                const isIOS = /iP(ad|hone|od)/i.test(navigator.userAgent || '')
                    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                if (isIOS) {
                    try {
                        const dataUrl = await new Promise((resolve, reject) => {
                            const reader = new FileReader();
                            reader.onloadend = () => resolve(reader.result);
                            reader.onerror = () => reject(new Error('read fail'));
                            reader.readAsDataURL(blob);
                        });
                        const a = document.createElement('a');
                        a.href = dataUrl;
                        a.download = fileName;
                        a.rel = 'noopener';
                        a.style.display = 'none';
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(() => a.remove(), 1500);
                        return true;
                    } catch (_) {
                        // lanjut ke blob URL
                    }
                }

                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                a.rel = 'noopener';
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                setTimeout(() => {
                    a.remove();
                    URL.revokeObjectURL(url);
                }, 2500);
                return true;
            },
            async exportQuiz(format) {
                if (!this.result) return;
                const isPdf = format === 'pdf';
                if ((isPdf && this.exportingPdf) || (!isPdf && this.exportingWord)) return;
                if (isPdf) this.exportingPdf = true; else this.exportingWord = true;
                this.error = '';
                this.exportNotice = '';
                try {
                    const title = this.resultSource === 'ocr'
                        ? ('Teks scan buku' + (this.quiz.topik ? ' - ' + this.quiz.topik : ''))
                        : (this.quiz.topik ? 'Soal - ' + this.quiz.topik : 'Soal dari Asisten Guru');
                    const fileName = this.slugify(title || 'soal-asisten-ai') + (isPdf ? '.pdf' : '.docx');
                    await this.downloadExportFile({
                        url: isPdf ? this.urls.quizPdf : this.urls.quizWord,
                        fields: { title, content: this.result },
                        fileName,
                        mimeHint: isPdf
                            ? 'application/pdf'
                            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    });
                } catch (e) {
                    this.error = (e && e.message) ? e.message : 'Export gagal. Periksa koneksi lalu coba lagi.';
                } finally {
                    if (isPdf) this.exportingPdf = false; else this.exportingWord = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },
            /**
             * Ambil pratinjau dokumen berformat dari server (parser + template yang sama
             * dengan export), jadi tampilan di layar persis seperti hasil unduhannya.
             * Berlaku untuk tab yang punya dokumen berformat: soal dan RPM Learning.
             */
            async refreshPreview() {
                const url = { quiz: this.urls.quizPreview, learning: this.urls.learningPreview }[this.tab];
                if (!url || !this.result) {
                    this.previewHtml = '';
                    return;
                }
                const seq = ++this.previewSeq;
                const tab = this.tab;
                const content = this.result;
                this.previewLoading = true;
                try {
                    const body = tab === 'learning'
                        ? { tool: this.learning.tool, content }
                        : { content };
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify(body),
                    });
                    const d = await r.json().catch(() => ({}));
                    // Latest-wins: respons lama tidak menimpa pratinjau terbaru.
                    if (seq !== this.previewSeq) return;
                    if (this.tab !== tab || this.result !== content) return;
                    // Gagal pratinjau bukan kegagalan fatal: teks hasil tetap tampil apa adanya.
                    this.previewHtml = (r.ok && d.ok) ? (d.html || '') : '';
                } catch (_) {
                    if (seq !== this.previewSeq) return;
                    this.previewHtml = '';
                } finally {
                    if (seq === this.previewSeq) {
                        this.previewLoading = false;
                        this.$nextTick(() => {
                            window.lucide && lucide.createIcons();
                            if (this.$refs.hasilScrollBody) this.$refs.hasilScrollBody.scrollTop = 0;
                        });
                    }
                }
            },
            updateQuota(quota) {
                if (quota) this.quota = quota;
            },

            addHistory(item) {
                this.histories = [item, ...this.histories.filter((history) => history.uuid !== item.uuid)].slice(0, 20);
            },

            async deleteHistory(item) {
                if (this.deletingHistory) return;
                if (!confirm('Hapus history "' + item.title + '"? Hasil yang sudah diunduh tidak ikut terhapus.')) return;

                this.deletingHistory = item.uuid;
                try {
                    const r = await fetch(this.urls.historyBase + '/' + item.uuid, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                    });
                    if (!r.ok) {
                        this.error = 'Gagal menghapus history. Coba lagi.';
                        return;
                    }
                    this.histories = this.histories.filter((history) => history.uuid !== item.uuid);
                } catch (_) {
                    this.error = 'Gagal menghapus history. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.deletingHistory = '';
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            openHistory(item) {
                const learningTypes = ['rpp'];
                this.error = '';
                this.copied = false;
                this.editing = false;
                this.previewHtml = '';
                this.geminiError = '';

                if (item.type === 'gemini_chat') {
                    this.tab = 'gemini';
                    const prompt = (item.metadata && item.metadata.prompt) || item.title || '';
                    const answer = this.tidyNalarAnswer(item.answer || '');
                    this.geminiMessages = [];
                    if (prompt) this.geminiMessages.push({ role: 'user', text: prompt });
                    if (answer) {
                        const msg = { role: 'assistant', text: answer, previewHtml: '' };
                        this.geminiMessages.push(msg);
                        this.attachQuizPreviewToMessage(msg);
                    }
                    this.$nextTick(() => {
                        window.lucide && lucide.createIcons();
                        if (this.$refs.geminiScroll) this.$refs.geminiScroll.scrollTop = this.$refs.geminiScroll.scrollHeight;
                    });
                    return;
                }

                // Teks scan buku: muat ulang ke Foto buku + panel Hasil (siap generate ulang).
                if (item.type === 'ocr_scan') {
                    const scope = (item.metadata && item.metadata.scope) === 'learning' ? 'learning' : 'quiz';
                    const text = item.answer || '';
                    this.tab = scope === 'learning' ? 'learning' : 'quiz';
                    if (scope === 'learning') {
                        this.learning.source = 'camera';
                    } else {
                        this.quiz.source = 'camera';
                    }
                    this.ocr[scope].text = text;
                    this.ocr[scope].error = '';
                    this.result = text;
                    this.resultSource = 'ocr';
                    this.previewHtml = '';
                    this.$nextTick(() => {
                        window.lucide && lucide.createIcons();
                        if (this.$refs.hasilScrollBody) this.$refs.hasilScrollBody.scrollTop = 0;
                    });
                    return;
                }

                this.tab = learningTypes.includes(item.type) ? 'learning' : item.type;
                if (learningTypes.includes(item.type)) this.learning.tool = item.type;
                this.result = item.answer || '';
                this.resultSource = 'generate';
                if (this.tab === 'learning' || this.tab === 'quiz') this.refreshPreview();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            learningToolLabel() {
                return 'RPM Learning';
            },
            async exportLearning(format) {
                if (!this.result) return;
                const isPdf = format === 'pdf';
                if ((isPdf && this.exportingPdf) || (!isPdf && this.exportingWord)) return;
                if (isPdf) this.exportingPdf = true; else this.exportingWord = true;
                this.error = '';
                this.exportNotice = '';
                try {
                    const toolLabel = this.learningToolLabel();
                    const title = this.learning.topik ? toolLabel + ' - ' + this.learning.topik : toolLabel;
                    const fileName = this.slugify(title || 'perangkat-ajar-learning') + (isPdf ? '.pdf' : '.docx');
                    await this.downloadExportFile({
                        url: isPdf ? this.urls.learningPdf : this.urls.learningWord,
                        fields: {
                            tool: this.learning.tool || 'rpp',
                            title,
                            content: this.result,
                        },
                        fileName,
                        mimeHint: isPdf
                            ? 'application/pdf'
                            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    });
                } catch (e) {
                    this.error = (e && e.message) ? e.message : 'Export gagal. Periksa koneksi lalu coba lagi.';
                } finally {
                    if (isPdf) this.exportingPdf = false; else this.exportingWord = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            slugify(value) {
                return (value || 'dokumen')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '') || 'dokumen';
            },
            toggleEdit() {
                this.editing = !this.editing;
                // Keluar dari mode edit: sinkron OCR + susun ulang pratinjau (bukan teks scan).
                if (!this.editing) {
                    this.syncOcrFromResult();
                    if (this.resultSource !== 'ocr') this.refreshPreview();
                }
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            clearResult() {
                this.result = '';
                this.resultSource = null;
                this.previewHtml = '';
                this.copied = false;
                this.editing = false;
                this.externalFlow = false;
                this.externalPaste = '';
                this.promptCopied = false;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            copy() {
                navigator.clipboard.writeText(this.result).then(() => {
                    this.copied = true;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                    setTimeout(() => { this.copied = false; this.$nextTick(() => window.lucide && lucide.createIcons()); }, 2000);
                });
            },
        }
    }
</script>
@endsection
