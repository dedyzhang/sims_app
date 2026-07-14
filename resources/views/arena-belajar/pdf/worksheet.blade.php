<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $quiz->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 16px; font-size: 11px; }
        .q { margin-bottom: 14px; page-break-inside: avoid; }
        .q-title { font-weight: bold; margin-bottom: 4px; }
        .opt { margin-left: 12px; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #333; margin-right: 6px; }
        .key { color: #065f46; font-size: 11px; margin-top: 4px; }
        .line { border-bottom: 1px solid #999; height: 18px; margin: 4px 0; }
    </style>
</head>
<body>
    <h1>{{ $quiz->title }}</h1>
    <div class="meta">
        {{ $classroom->title }} · Nilai maks {{ $quiz->max_score }}
        @if($withKey) · LEMBAR KUNCI GURU @endif
    </div>

    @foreach($quiz->questions as $i => $q)
    <div class="q">
        <div class="q-title">{{ $i+1 }}. {{ $q->question_text }} <small>({{ $q->typeLabel() }})</small></div>
        @if(in_array($q->type, ['mcq', 'true_false']))
            @foreach($q->options as $o)
            <div class="opt"><span class="box"></span>{{ $o->option_text }}@if($withKey && $o->is_correct) ✓@endif</div>
            @endforeach
        @elseif($q->type === 'short_answer')
            <div class="line"></div>
            @if($withKey)<div class="key">Kunci: {{ implode(' / ', $q->meta['answers'] ?? []) }}</div>@endif
        @elseif($q->type === 'match')
            @foreach(($q->meta['pairs'] ?? []) as $p)
            <div class="opt">{{ $p['left'] }} ↔ ____________ @if($withKey)<span class="key">({{ $p['right'] }})</span>@endif</div>
            @endforeach
        @endif
    </div>
    @endforeach
</body>
</html>
