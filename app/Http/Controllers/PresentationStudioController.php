<?php

namespace App\Http\Controllers;

use App\Models\TeacherPresentation;
use App\Support\PresentationSlides;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PresentationStudioController extends Controller
{
    public function index(Request $request): View
    {
        $items = TeacherPresentation::query()
            ->where('user_uuid', $request->user()->uuid)
            ->latest('updated_at')
            ->get();

        return view('ai.presentasi.index', [
            'items' => $items,
            'statuses' => TeacherPresentation::STATUSES,
            'canvaStatus' => app(\App\Services\CanvaConnectService::class)->statusPayload($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $outline = $data['outline'] ?? null;
        $slides = PresentationSlides::fromOutline($outline);

        $item = TeacherPresentation::create([
            'user_uuid' => $request->user()->uuid,
            'title' => $data['title'],
            'subject' => $data['subject'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'outline' => $outline,
            'notes' => $data['notes'] ?? null,
            'slides' => $slides !== [] ? $slides : null,
            'last_opened_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Presentasi dibuat.',
                'redirect' => route('ai.teacher.presentasi.show', $item),
                'presentation' => $this->itemPayload($item),
            ]);
        }

        return redirect()
            ->route('ai.teacher.presentasi.show', $item)
            ->with('success', 'Studio presentasi siap. Susun slide lalu presentasikan.');
    }

    public function show(Request $request, TeacherPresentation $presentation): View
    {
        $this->authorizeOwner($request, $presentation);
        $presentation->update(['last_opened_at' => now()]);
        $presentation = $presentation->fresh();

        return view('ai.presentasi.studio', [
            'presentation' => $presentation,
            'slides' => $presentation->resolvedSlides(),
            'statuses' => TeacherPresentation::STATUSES,
            'canvaStatus' => app(\App\Services\CanvaConnectService::class)->statusPayload($request->user()),
        ]);
    }

    public function update(Request $request, TeacherPresentation $presentation): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($request, $presentation);
        $data = $this->validated($request, false);

        $slides = null;
        if ($request->exists('slides') && is_array($request->input('slides'))) {
            $slides = PresentationSlides::normalize($request->input('slides'));
        } elseif (array_key_exists('outline', $data)) {
            $slides = PresentationSlides::fromOutline($data['outline'] ?? null);
        }

        $presentation->update([
            'title' => $data['title'],
            'subject' => $data['subject'] ?? null,
            'status' => $data['status'] ?? $presentation->status,
            'outline' => $data['outline'] ?? null,
            'notes' => $data['notes'] ?? null,
            'slides' => $slides !== null && $slides !== [] ? $slides : $presentation->slides,
        ]);

        if ($request->wantsJson()) {
            $presentation->refresh();

            return response()->json([
                'ok' => true,
                'message' => 'Presentasi disimpan.',
                'presentation' => $this->itemPayload($presentation),
                'slides' => $presentation->resolvedSlides(),
            ]);
        }

        return back()->with('success', 'Presentasi disimpan.');
    }

    public function destroy(Request $request, TeacherPresentation $presentation): RedirectResponse
    {
        $this->authorizeOwner($request, $presentation);
        $presentation->delete();

        return redirect()
            ->route('ai.teacher.presentasi.index')
            ->with('success', 'Presentasi dihapus.');
    }

    public function exportPdf(Request $request, TeacherPresentation $presentation): Response
    {
        $this->authorizeOwner($request, $presentation);
        $slides = $presentation->resolvedSlides();
        $safe = Str::slug($presentation->title) ?: 'presentasi';

        $pdf = Pdf::loadView('ai.presentasi.pdf', [
            'presentation' => $presentation,
            'slides' => $slides,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($safe.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function authorizeOwner(Request $request, TeacherPresentation $presentation): void
    {
        abort_unless($presentation->user_uuid === $request->user()->uuid, 403);
    }

    private function validated(Request $request, bool $requireTitle = true): array
    {
        return $request->validate([
            'title' => [$requireTitle ? 'required' : 'required', 'string', 'max:200'],
            'subject' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:draft,in_progress,done'],
            'outline' => ['nullable', 'string', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'slides' => ['nullable', 'array', 'max:60'],
            'slides.*.title' => ['nullable', 'string', 'max:200'],
            'slides.*.body' => ['nullable', 'string', 'max:5000'],
        ], [
            'title.required' => 'Judul presentasi wajib diisi.',
        ]);
    }

    private function itemPayload(TeacherPresentation $item): array
    {
        return [
            'uuid' => $item->uuid,
            'title' => $item->title,
            'subject' => $item->subject,
            'status' => $item->status,
            'updated_at' => optional($item->updated_at)->toIso8601String(),
        ];
    }
}
