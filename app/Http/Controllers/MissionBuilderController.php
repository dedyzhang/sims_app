<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionActivityLog;
use App\Models\MissionItemBank;
use App\Models\MissionReflectionPrompt;
use App\Models\MissionStep;
use App\Support\ArenaJenjang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MissionBuilderController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Mission::class);

        $missions = Mission::query()
            ->withCount('steps')
            ->where(function ($q) {
                $q->where('created_by', auth()->user()->uuid)
                    ->orWhere(function ($q2) {
                        $q2->where('visible_to_teachers', true)
                            ->whereNull('created_by');
                    });
            })
            ->orderByDesc('updated_at')
            ->get();

        $bankItems = MissionItemBank::query()
            ->where(function ($q) {
                $q->where('created_by', auth()->user()->uuid)
                    ->orWhere('is_shared', true);
            })
            ->orderBy('title')
            ->get();

        return view('jagat-misi.builder-index', compact('missions', 'bankItems'));
    }

    public function create(): View
    {
        Gate::authorize('create', Mission::class);

        return view('jagat-misi.builder', ['mission' => null]);
    }

    public function edit(Mission $mission): View
    {
        Gate::authorize('manage', $mission);
        $mission->load(['steps', 'reflectionPrompts']);

        return view('jagat-misi.builder', compact('mission'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Mission::class);

        $mission = $this->persistMission($request, new Mission);

        return redirect()->route('jagat-misi.builder.edit', $mission)->with('success', 'Misi disimpan.');
    }

    public function update(Request $request, Mission $mission): RedirectResponse
    {
        Gate::authorize('manage', $mission);

        $this->persistMission($request, $mission);

        return redirect()->route('jagat-misi.builder.edit', $mission)->with('success', 'Misi diperbarui.');
    }

    public function publish(Mission $mission): RedirectResponse
    {
        Gate::authorize('manage', $mission);

        if (! $mission->steps()->exists()) {
            return back()->with('error', 'Misi belum punya langkah permainan. Saat ini tugaskan misi dari katalog yang sudah siap, atau isi langkah lewat seeder/admin.');
        }

        $mission->forceFill([
            'status' => 'published',
            'is_published' => true,
        ])->save();

        return back()->with('success', 'Misi diterbitkan.');
    }

    public function storeBankItem(Request $request): JsonResponse
    {
        Gate::authorize('create', Mission::class);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        $item = MissionItemBank::create([
            'created_by' => $request->user()->uuid,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'is_shared' => (bool) ($validated['is_shared'] ?? false),
        ]);

        return response()->json(['data' => $item], 201);
    }

    private function persistMission(Request $request, Mission $mission): Mission
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:100'],
            'jenjang' => ['required', Rule::in(['sd', 'smp', 'sma', 'umum'])],
            'grade_detail' => ['nullable', 'string', 'max:50'],
            'grade_level' => ['nullable', 'string', 'max:50'], // legacy fallback
            'mechanic_type' => ['required', 'string', 'max:50'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'summary' => ['required', 'string'],
            'objectives' => ['nullable', 'array'],
            'requires_reflection' => ['nullable', 'boolean'],
            'visible_to_teachers' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
            'steps' => ['nullable', 'array'],
            'steps.*.module_key' => ['required_with:steps', 'string'],
            'steps.*.title' => ['required_with:steps', 'string'],
            'steps.*.prompt' => ['nullable', 'string'],
            'steps.*.body' => ['nullable', 'string'],
            'steps.*.payload' => ['nullable', 'array'],
            'steps.*.max_points' => ['nullable', 'integer', 'min:0'],
            'reflections' => ['nullable', 'array'],
            'reflections.*' => ['string'],
        ]);

        return DB::transaction(function () use ($request, $mission, $validated) {
            $isNew = ! $mission->exists;
            $slug = $mission->slug ?? Str::slug($validated['title']).'-'.Str::random(4);

            $jenjang = $validated['jenjang'];
            $jenjangLabel = $jenjang === 'umum' ? 'Umum' : ArenaJenjang::label($jenjang);
            $detail = trim((string) ($validated['grade_detail'] ?? ''));
            $gradeLevel = $detail !== ''
                ? trim($jenjangLabel.' '.$detail)
                : ($validated['grade_level'] ?? $jenjangLabel);

            $meta = array_merge($mission->meta ?? [], $validated['meta'] ?? [], [
                'jenjang' => $jenjang,
            ]);

            $mission->fill([
                'created_by' => $mission->created_by ?? $request->user()->uuid,
                'slug' => $slug,
                'title' => $validated['title'],
                'subject' => $validated['subject'],
                'grade_level' => $gradeLevel,
                'mechanic_type' => $validated['mechanic_type'],
                'duration_minutes' => $validated['duration_minutes'],
                'summary' => $validated['summary'],
                'objectives' => $validated['objectives'] ?? [],
                'requires_reflection' => (bool) ($validated['requires_reflection'] ?? true),
                'visible_to_teachers' => (bool) ($validated['visible_to_teachers'] ?? false),
                'status' => $mission->status ?? 'draft',
                'is_published' => $mission->is_published ?? false,
                'max_score' => $mission->max_score ?: 100,
                'meta' => $meta,
            ])->save();

            if (isset($validated['steps'])) {
                $mission->steps()->delete();
                foreach (array_values($validated['steps']) as $index => $stepData) {
                    MissionStep::create([
                        'mission_id' => $mission->uuid,
                        'module_key' => $stepData['module_key'],
                        'position' => $index + 1,
                        'title' => $stepData['title'],
                        'prompt' => $stepData['prompt'] ?? '',
                        'body' => $stepData['body'] ?? null,
                        'payload' => $stepData['payload'] ?? [],
                        'max_points' => (int) ($stepData['max_points'] ?? 0),
                    ]);
                }
            }

            if (isset($validated['reflections'])) {
                $mission->reflectionPrompts()->delete();
                foreach (array_values($validated['reflections']) as $index => $prompt) {
                    if (! trim($prompt)) {
                        continue;
                    }
                    MissionReflectionPrompt::create([
                        'mission_id' => $mission->uuid,
                        'position' => $index + 1,
                        'prompt_text' => $prompt,
                        'is_required' => true,
                    ]);
                }
            }

            MissionActivityLog::create([
                'action' => $isNew ? 'mission.created' : 'mission.updated',
                'subject_type' => Mission::class,
                'subject_id' => $mission->uuid,
                'causer_type' => $request->user()::class,
                'causer_id' => $request->user()->uuid,
                'properties' => ['title' => $mission->title],
            ]);

            return $mission->refresh();
        });
    }
}
