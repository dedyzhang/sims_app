<?php

namespace App\Http\Controllers;

use App\Models\TeacherPresentation;
use App\Services\CanvaConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CanvaConnectController extends Controller
{
    public function __construct(private readonly CanvaConnectService $canva) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'canva' => $this->canva->statusPayload($request->user()),
        ]);
    }

    public function updateBelajarId(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'canva_belajar_id' => ['required', 'email', 'max:191'],
        ]);

        $email = strtolower(trim($data['canva_belajar_id']));
        try {
            $this->canva->assertBelajarIdEmail($email);
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $request->user()->forceFill(['canva_belajar_id' => $email])->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Email belajar.id disimpan.',
                'canva' => $this->canva->statusPayload($request->user()->fresh()),
            ]);
        }

        return back()->with('success', 'Email belajar.id disimpan.');
    }

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->canva->featureEnabled()) {
            return redirect()
                ->route('ai.teacher.index')
                ->with('error', 'Integrasi Canva dimatikan di Pengaturan Sistem.');
        }

        try {
            $this->canva->assertUserBelajarIdReady($request->user());
            $auth = $this->canva->beginAuthorization();
        } catch (RuntimeException $e) {
            return redirect()
                ->route('ai.teacher.index')
                ->with('error', $e->getMessage());
        }

        $request->session()->put('canva_oauth', [
            'state' => $auth['state'],
            'code_verifier' => $auth['code_verifier'],
            'user_uuid' => $request->user()->uuid,
        ]);

        return redirect()->away($auth['url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->canva->featureEnabled()) {
            $request->session()->forget('canva_oauth');

            return redirect()
                ->route('ai.teacher.index')
                ->with('error', 'Integrasi Canva dimatikan di Pengaturan Sistem.');
        }

        $session = $request->session()->pull('canva_oauth');
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        $error = (string) $request->query('error', '');
        $errorDescription = (string) $request->query('error_description', '');

        if ($error !== '') {
            $pesan = 'Otorisasi Canva dibatalkan atau ditolak.';
            if ($error !== 'access_denied') {
                // access_denied = user sendiri klik Batal/Tolak di layar Canva — pesan generik cukup.
                // Selain itu (misalnya invalid_scope, invalid_request) tampilkan detailnya agar mudah didiagnosis.
                $pesan .= ' ('.$error.($errorDescription !== '' ? ': '.$errorDescription : '').')';
            }

            return redirect()
                ->route('ai.teacher.index')
                ->with('error', $pesan);
        }

        if (! is_array($session)
            || $state === ''
            || ! hash_equals((string) ($session['state'] ?? ''), $state)
            || ($session['user_uuid'] ?? null) !== $request->user()->uuid
        ) {
            return redirect()
                ->route('ai.teacher.index')
                ->with('error', 'Sesi OAuth Canva tidak valid. Coba hubungkan lagi.');
        }

        if ($code === '') {
            return redirect()
                ->route('ai.teacher.index')
                ->with('error', 'Kode otorisasi Canva tidak diterima.');
        }

        try {
            $this->canva->completeAuthorization(
                $request->user(),
                $code,
                (string) $session['code_verifier'],
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('ai.teacher.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('ai.teacher.index')
            ->with('success', 'Canva Pendidikan terhubung dengan akun belajar.id. Anda bisa membuat desain dari Studio Presentasi.');
    }

    public function disconnect(Request $request): JsonResponse|RedirectResponse
    {
        $this->canva->disconnect($request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Tautan Canva diputus.',
                'canva' => $this->canva->statusPayload($request->user()),
            ]);
        }

        return redirect()
            ->route('ai.teacher.index')
            ->with('success', 'Tautan Canva diputus.');
    }

    public function designs(Request $request): JsonResponse
    {
        try {
            $items = $this->canva->listDesigns($request->user(), (int) $request->query('limit', 20));
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'designs' => $items]);
    }

    public function createDesign(Request $request, TeacherPresentation $presentation): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $presentation);

        try {
            $design = $this->canva->createPresentationDesign($request->user(), $presentation->title);
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $presentation->forceFill([
            'canva_design_id' => $design['id'],
            'canva_edit_url' => $design['edit_url'],
            'canva_view_url' => $design['view_url'],
            'canva_last_synced_at' => now(),
            'status' => $presentation->status === 'draft' ? 'in_progress' : $presentation->status,
        ])->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Desain Canva dibuat. Lanjutkan di tab Canva Pendidikan.',
                'design' => $design,
                'presentation' => [
                    'uuid' => $presentation->uuid,
                    'canva_design_id' => $presentation->canva_design_id,
                    'canva_edit_url' => $presentation->canva_edit_url,
                    'canva_view_url' => $presentation->canva_view_url,
                ],
                'open_url' => $design['edit_url'],
            ]);
        }

        if ($design['edit_url']) {
            return redirect()->away($design['edit_url']);
        }

        return redirect()
            ->route('ai.teacher.presentasi.show', $presentation)
            ->with('success', 'Desain Canva dibuat.');
    }

    public function refreshUrl(Request $request, TeacherPresentation $presentation): JsonResponse
    {
        $this->authorizeOwner($request, $presentation);
        $designId = trim((string) $presentation->canva_design_id);
        if ($designId === '') {
            return response()->json(['ok' => false, 'message' => 'Presentasi belum tertaut ke Canva.'], 422);
        }

        try {
            $design = $this->canva->getDesign($request->user(), $designId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $presentation->forceFill([
            'canva_edit_url' => $design['edit_url'],
            'canva_view_url' => $design['view_url'],
            'canva_last_synced_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'edit_url' => $presentation->canva_edit_url,
            'view_url' => $presentation->canva_view_url,
        ]);
    }

    public function exportPdf(Request $request, TeacherPresentation $presentation): JsonResponse
    {
        $this->authorizeOwner($request, $presentation);

        try {
            @set_time_limit((int) config('services.canva.export_timeout', 90) + 30);
            $exported = $this->canva->exportPresentationPdf($request->user(), $presentation->fresh());
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => ($exported['pages'] ?? 1) > 1
                ? 'Ekspor Canva siap (ZIP multi-halaman).'
                : 'PDF Canva berhasil diekspor ke SIMS.',
            'path' => $exported['path'],
            'url' => $exported['url'],
            'pages' => $exported['pages'] ?? 1,
            'download' => route('ai.teacher.presentasi.canva.download', $presentation),
        ]);
    }

    public function downloadExport(Request $request, TeacherPresentation $presentation): StreamedResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $presentation);
        $path = trim((string) $presentation->canva_exported_pdf_path);
        if ($path === '') {
            abort(404, 'Belum ada PDF Canva yang diekspor.');
        }

        try {
            $this->canva->assertExportPathOwnedBy($request->user(), $path);
        } catch (RuntimeException) {
            abort(404, 'File PDF Canva tidak ditemukan.');
        }

        $disk = (string) config('services.canva.export_disk', 'local');
        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'File PDF Canva tidak ditemukan.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf');
        $filename = Str::slug($presentation->title).'-canva.'.$ext;

        return Storage::disk($disk)->download($path, $filename);
    }

    private function authorizeOwner(Request $request, TeacherPresentation $presentation): void
    {
        abort_unless($presentation->user_uuid === $request->user()->uuid, 403);
    }
}
