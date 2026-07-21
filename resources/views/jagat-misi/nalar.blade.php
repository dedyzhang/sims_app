@extends('layouts.app')
@section('title', $mission->title . ' — Arena Belajar')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/jagat-misi/styles.css') }}">
<link rel="stylesheet" href="{{ asset('assets/jagat-misi/nalar.css') }}">
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-4 arena-stage">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ isset($classroom) ? route('classroom.jagat.show', [$classroom, $mission]) : route('jagat-misi.index') }}"
               class="arena-hud-back mb-3">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span class="truncate max-w-[12rem] sm:max-w-xs">{{ isset($classroom) ? $classroom->title : 'Katalog Misi' }}</span>
            </a>
            <h1 class="text-xl sm:text-2xl font-black">{{ $mission->title }}</h1>
            <p class="text-sm text-slate-500">{{ $mission->summary }}</p>
        </div>
        <button type="button" id="submitMission" class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-bold">Kumpulkan Misi</button>
    </div>

    <div id="missionPlayer" class="nalar-shell" data-mission-slug="{{ $mission->slug }}"
         data-api-show="{{ route('jagat-misi.api.show', $mission) }}"
         data-api-submit="{{ route('jagat-misi.api.attempts', $mission) }}"
         data-csrf="{{ csrf_token() }}"
         @isset($assignment) data-assignment-id="{{ $assignment->uuid }}" @endisset>
        <div class="module-switch" role="tablist">
            <button class="module-tab active" type="button" data-module="narrative">Narasi</button>
            <button class="module-tab" type="button" data-module="decision">Keputusan</button>
            <button class="module-tab" type="button" data-module="puzzle">Puzzle</button>
        </div>
        <div class="nalar-layout">
            <section class="nalar-stage">
                <section class="module-panel active" id="panel-narrative">
                    <div class="module-head"><h2 id="narrativeTitle">Memuat…</h2><div id="narrativeScore" class="font-bold text-primary"></div></div>
                    <div class="story-card" id="narrativeCard">
                        <p class="story-tag" id="narrativeTag"></p>
                        <p id="narrativePrompt" class="font-bold text-slate-800"></p>
                        <p id="narrativeBody"></p>
                        <div class="choice-grid" id="narrativeChoices"></div>
                    </div>
                    <div class="path-row" id="narrativePath"></div>
                </section>
                <section class="module-panel" id="panel-decision">
                    <div class="module-head"><h2 id="decisionRound">Keputusan Strategis</h2><span id="decisionTag" class="story-tag"></span></div>
                    <div class="decision-meter">
                        <div>
                            <span>Stabilitas</span><strong id="stabilityValue">0</strong>
                            <div class="meter-bar"><span id="stabilityBar" style="width: 0%"></span></div>
                        </div>
                        <div>
                            <span>Kepercayaan</span><strong id="trustValue">0</strong>
                            <div class="meter-bar"><span id="trustBar" style="width: 0%"></span></div>
                        </div>
                        <div>
                            <span>Anggaran</span><strong id="budgetValue">0</strong>
                            <div class="meter-bar budget"><span id="budgetBar" style="width: 0%"></span></div>
                        </div>
                    </div>
                    <div class="story-card decision-card" id="decisionBoard">
                        <p id="decisionPrompt" class="font-bold text-slate-800"></p>
                        <p id="decisionBody"></p>
                        <div class="choice-grid" id="decisionChoices"></div>
                    </div>
                    <div class="path-row" id="decisionLog"></div>
                </section>
                <section class="module-panel" id="panel-puzzle">
                    <div class="module-head"><h2>Puzzle Sequencing</h2><span id="puzzleScore" class="font-bold text-primary"></span></div>
                    <div id="puzzleBoard" class="puzzle-board">
                        <div class="puzzle-grid">
                            <div class="sequence-list" id="sequenceList"></div>
                            <div class="target-list" id="targetList"></div>
                        </div>
                        <div class="nalar-actions path-row mt-4 flex justify-end gap-2">
                            <button type="button" class="path-pill" id="shufflePuzzle">Acak Urutan</button>
                            <button type="button" class="path-pill !bg-primary !text-white" id="checkPuzzle">Periksa</button>
                        </div>
                    </div>
                </section>
            </section>
            <aside class="nalar-side">
                <div class="summary-card p-4 space-y-3" id="missionSummary">
                    <p class="eyebrow text-xs font-bold text-slate-400 uppercase">Ringkasan</p>
                    <p id="activeModuleLabel" class="text-sm font-semibold text-primary"></p>
                    <div>
                        <p class="text-sm text-slate-500">Skor sementara</p>
                        <p id="summaryScore" class="text-2xl font-black text-slate-800"><span id="totalPoints">0</span></p>
                    </div>
                    <p id="moduleStatus" class="text-sm font-medium text-amber-600">Belum selesai</p>
                    <div class="flex flex-col gap-2 mt-4">
                        <button type="button" id="resetModule" class="path-pill text-center">Reset Modul</button>
                        <button type="button" id="randomModule" class="path-pill text-center">Modul Acak</button>
                        <button type="button" id="completeModule" class="path-pill text-center !bg-emerald-100 !text-emerald-700">Selesaikan Modul</button>
                        <button type="button" id="finishNalar" class="hidden">Finish</button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    <div id="toast" class="fixed bottom-4 right-4 bg-slate-800 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 transition-opacity duration-300 pointer-events-none z-50"></div>
    <div id="submitResult" class="hidden rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 p-4 text-sm"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/jagat-misi/nalar.js') }}"></script>
<script>
document.getElementById('submitMission')?.addEventListener('click', async () => {
    const root = document.getElementById('missionPlayer');
    const url = root.dataset.apiSubmit;
    const payload = window.JagatMisiNalar?.buildSubmitPayload?.() ?? {};
    if (root.dataset.assignmentId) {
        payload.assignment_id = root.dataset.assignmentId;
    }
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': root.dataset.csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });
    const data = await res.json();
    const box = document.getElementById('submitResult');
    box.classList.remove('hidden');
    if (res.ok) {
        box.textContent = `Misi selesai! Skor: ${data.data?.attempt?.score ?? 0}%`;
        box.className = 'rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700';
    } else {
        box.textContent = data.message || 'Gagal mengumpulkan misi.';
        box.className = 'rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700';
    }
});
</script>
@endpush
