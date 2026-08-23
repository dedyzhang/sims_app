<?php

namespace App\Http\Controllers\Keuangan;

use App\Exports\Keuangan\Rkas\RkasPlanExport;
use App\Http\Controllers\Controller;
use App\Imports\RkasReferenceImport;
use App\Models\RkasPlan;
use App\Models\RkasReference;
use App\Models\RkasReferenceSet;
use App\Models\RkasSyncLog;
use App\Models\Setting;
use App\Services\Keuangan\RkasReferenceService;
use App\Services\Keuangan\RkasValidationService;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RkasController extends Controller
{
    public function __construct(
        private readonly RkasValidationService $validationService,
        private readonly RkasReferenceService $referenceService,
    ) {
    }

    public function index(Request $request)
    {
        $this->ensureCanReview($request);

        $plans = RkasPlan::query()
            ->with('referenceSet')
            ->withCount(['items', 'validations as validation_errors_count' => fn ($query) => $query->where('severity', 'error')])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('keuangan.rkas.index', [
            'plans' => $plans,
            'referenceSets' => RkasReferenceSet::query()->latest()->get(),
            'canManage' => $this->canManage($request->user()),
            'canManageReferences' => $this->canManageReferences($request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $this->ensureCanManage($request);

        return view('keuangan.rkas.create', [
            'mode' => 'create',
            'plan' => new RkasPlan([
                'npsn' => Setting::get('npsn', ''),
                'nama_sekolah' => Setting::get('nama_sekolah', ''),
                'tahun_anggaran' => (int) now()->format('Y'),
                'jenjang' => 'Dikdasmen',
                'sumber_dana' => 'BOSP Reguler',
            ]),
            'referenceSets' => RkasReferenceSet::query()->with('references')->where('is_active', true)->latest()->get(),
            'items' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureCanManage($request);
        $data = $this->validatedPlan($request);

        $plan = DB::transaction(function () use ($data, $request) {
            $referenceSet = $this->referenceSetFor($data['reference_set_uuid'], true);
            $plan = RkasPlan::create([
                ...$this->planAttributes($data),
                'reference_set_uuid' => $referenceSet->uuid,
                'status' => RkasPlan::STATUS_DRAFT,
                'created_by' => $request->user()->uuid,
                'updated_by' => $request->user()->uuid,
            ]);

            $this->replaceItems($plan, $data['items'], $referenceSet);
            $this->persistValidation($plan, $request->user()->uuid, false);
            Audit::log('rkas_plan_created', $plan, ['reference_set_uuid' => $referenceSet->uuid]);

            return $plan;
        });

        return redirect()->route('keuangan.rkas.show', $plan)->with('success', 'RKAS berhasil disimpan sebagai draft.');
    }

    public function show(Request $request, RkasPlan $plan)
    {
        $this->ensureCanReview($request);
        $plan->load(['referenceSet', 'items.reference', 'validations', 'syncLogs.actor']);

        return view('keuangan.rkas.show', [
            'plan' => $plan,
            'canManage' => $this->canManage($request->user()),
            'canManageReferences' => $this->canManageReferences($request->user()),
        ]);
    }

    public function edit(Request $request, RkasPlan $plan)
    {
        $this->ensureCanManage($request);
        $this->ensureEditable($plan);
        $plan->load(['referenceSet', 'items.reference']);

        return view('keuangan.rkas.create', [
            'mode' => 'edit',
            'plan' => $plan,
            'referenceSets' => RkasReferenceSet::query()->with('references')
                ->where('is_active', true)
                ->orWhere('uuid', $plan->reference_set_uuid)
                ->latest()
                ->get(),
            'items' => $plan->items,
        ]);
    }

    public function update(Request $request, RkasPlan $plan)
    {
        $this->ensureCanManage($request);
        $this->ensureEditable($plan);
        $data = $this->validatedPlan($request);

        DB::transaction(function () use ($data, $request, $plan) {
            $referenceSet = $this->referenceSetFor($data['reference_set_uuid'], true);
            $plan->update([
                ...$this->planAttributes($data),
                'reference_set_uuid' => $referenceSet->uuid,
                'status' => RkasPlan::STATUS_DRAFT,
                'validated_at' => null,
                'updated_by' => $request->user()->uuid,
            ]);
            $this->replaceItems($plan, $data['items'], $referenceSet);
            $this->persistValidation($plan, $request->user()->uuid, false);
            Audit::log('rkas_plan_updated', $plan, ['reference_set_uuid' => $referenceSet->uuid]);
        });

        return redirect()->route('keuangan.rkas.show', $plan)->with('success', 'RKAS diperbarui dan status dikembalikan ke draft.');
    }

    public function validatePlan(Request $request, RkasPlan $plan)
    {
        $this->ensureCanManage($request);
        $this->ensureEditable($plan);

        DB::transaction(function () use ($plan, $request) {
            $findings = $this->persistValidation($plan, $request->user()->uuid, true);
            Audit::log('rkas_plan_validated', $plan, [
                'errors' => $findings->where('severity', 'error')->count(),
                'warnings' => $findings->where('severity', 'warning')->count(),
            ]);
        });

        return back()->with('success', 'Validasi RKAS selesai. Periksa temuan sebelum menyiapkan input ARKAS.');
    }

    public function exportExcel(Request $request, RkasPlan $plan)
    {
        $this->ensureCanReview($request);
        if (! $this->ensureExportable($plan)) {
            return back()->with('error', 'RKAS belum dapat diekspor karena masih memiliki temuan error.');
        }

        Audit::log('rkas_export_excel', $plan, ['transport' => 'manual_arkas']);

        return Excel::download(new RkasPlanExport($plan->fresh(['referenceSet', 'items.reference', 'validations'])), 'RKAS-'.$plan->tahun_anggaran.'-'.$plan->uuid.'.xlsx');
    }

    public function exportPdf(Request $request, RkasPlan $plan)
    {
        $this->ensureCanReview($request);
        if (! $this->ensureExportable($plan)) {
            return back()->with('error', 'RKAS belum dapat diekspor karena masih memiliki temuan error.');
        }

        Audit::log('rkas_export_pdf', $plan, ['transport' => 'manual_arkas']);

        return Pdf::loadView('keuangan.rkas.exports.pdf', [
            'plan' => $plan->fresh(['referenceSet', 'items.reference', 'validations']),
        ])->download('RKAS-'.$plan->tahun_anggaran.'-'.$plan->uuid.'.pdf');
    }

    public function syncStatus(Request $request, RkasPlan $plan)
    {
        $this->ensureCanManage($request);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                RkasPlan::STATUS_READY,
                RkasPlan::STATUS_SUBMITTED,
                RkasPlan::STATUS_APPROVED,
                RkasPlan::STATUS_REVISION,
                RkasPlan::STATUS_ARCHIVED,
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $this->ensureTransitionAllowed($plan, $data['status']);

        DB::transaction(function () use ($data, $request, $plan) {
            $path = ($data['evidence'] ?? null)?->store('rkas-evidence');
            $from = $plan->status;
            $plan->update(['status' => $data['status'], 'updated_by' => $request->user()->uuid]);
            RkasSyncLog::create([
                'plan_uuid' => $plan->uuid,
                'actor_id' => $request->user()->uuid,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'evidence_path' => $path,
                'occurred_at' => now(),
            ]);
            Audit::log('rkas_status_changed', $plan, ['from' => $from, 'to' => $data['status'], 'evidence' => (bool) $path]);
        });

        return back()->with('success', 'Status RKAS dicatat melalui audit pengguna. Ini belum menyatakan data sudah tersinkron ke MARKAS.');
    }

    public function downloadEvidence(Request $request, RkasSyncLog $syncLog)
    {
        $this->ensureCanReview($request);
        $syncLog->load('plan');
        abort_unless($syncLog->evidence_path && Storage::disk('local')->exists($syncLog->evidence_path), 404);

        return Storage::disk('local')->download($syncLog->evidence_path);
    }

    public function importReference(Request $request)
    {
        $this->ensureCanManageReferences($request);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
            'versi' => ['required', 'string', 'max:100'],
            'jenjang' => ['required', 'string', 'max:40'],
            'sumber_dana' => ['required', 'string', 'max:80'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'rules_json' => ['nullable', 'json'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        try {
            $sheets = Excel::toCollection(new RkasReferenceImport, $request->file('file'));
            $rows = $this->referenceService->rows($sheets->first() ?? collect());
            $rules = $this->referenceService->parseRules($data['rules_json'] ?? null);
        } catch (\InvalidArgumentException|\JsonException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'Berkas referensi tidak memiliki baris data yang dapat diimpor.']);
        }
        $this->rejectDuplicateReferenceCodes($rows);

        $set = DB::transaction(function () use ($data, $rows, $request, $rules) {
            $set = RkasReferenceSet::create([
                'label' => $data['label'],
                'tahun_anggaran' => $data['tahun_anggaran'],
                'versi' => $data['versi'],
                'jenjang' => $data['jenjang'],
                'sumber_dana' => $data['sumber_dana'],
                'source_url' => $data['source_url'] ?? null,
                'source_checksum' => hash_file('sha256', $request->file('file')->getRealPath()),
                'rules' => $rules,
                'metadata' => ['imported_rows' => $rows->count(), 'filename' => $request->file('file')->getClientOriginalName()],
                'imported_by' => $request->user()->uuid,
                'is_active' => true,
            ]);

            foreach ($rows as $row) {
                $set->references()->create([
                    'kode_kegiatan' => $row['kode_kegiatan'],
                    'snp' => ($row['snp'] ?? '') ?: null,
                    'komponen' => ($row['komponen'] ?? '') ?: null,
                    'uraian_kegiatan' => $row['uraian_kegiatan'],
                    'kode_rekening_belanja' => ($row['kode_rekening_belanja'] ?? '') ?: null,
                ]);
            }
            $this->referenceService->deactivatePeers($set);
            Audit::log('rkas_reference_imported', $set, ['rows' => $rows->count(), 'checksum' => $set->source_checksum]);

            return $set;
        });

        return back()->with('success', "Referensi {$set->versi} berhasil diimpor dan versi sejenis sebelumnya dinonaktifkan.");
    }

    public function deactivateReference(Request $request, RkasReferenceSet $referenceSet)
    {
        $this->ensureCanManageReferences($request);
        $referenceSet->update(['is_active' => false]);
        Audit::log('rkas_reference_deactivated', $referenceSet);

        return back()->with('success', 'Referensi dinonaktifkan. RKAS historis tetap menyimpan versi referensinya.');
    }

    private function validatedPlan(Request $request): array
    {
        return $request->validate([
            'npsn' => ['nullable', 'string', 'max:20'],
            'nama_sekolah' => ['required', 'string', 'max:200'],
            'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
            'jenjang' => ['required', 'string', 'max:40'],
            'sumber_dana' => ['required', 'string', 'max:80'],
            'reference_set_uuid' => ['required', 'uuid', 'exists:rkas_reference_sets,uuid'],
            'pagu' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.reference_uuid' => ['required', 'uuid'],
            'items.*.penjelasan_implementasi' => ['nullable', 'string', 'max:1000'],
            'items.*.uraian_belanja' => ['required', 'string', 'max:1000'],
            'items.*.bulan_dianggarkan' => ['required', 'integer', 'between:1,12'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
            'items.*.satuan' => ['required', 'string', 'max:40'],
            'items.*.harga_satuan' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
        ]);
    }

    private function planAttributes(array $data): array
    {
        return [
            'npsn' => $data['npsn'] ?: Setting::get('npsn'),
            'nama_sekolah' => $data['nama_sekolah'],
            'tahun_anggaran' => $data['tahun_anggaran'],
            'jenjang' => $data['jenjang'],
            'sumber_dana' => $data['sumber_dana'],
            'pagu' => $data['pagu'],
        ];
    }

    private function replaceItems(RkasPlan $plan, array $items, RkasReferenceSet $referenceSet): void
    {
        $references = $referenceSet->references()->whereIn('uuid', collect($items)->pluck('reference_uuid'))->get()->keyBy('uuid');
        $missing = collect($items)->pluck('reference_uuid')->diff($references->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Ada kode kegiatan yang tidak berasal dari paket referensi yang dipilih.']);
        }

        $plan->items()->delete();
        foreach ($items as $item) {
            $reference = $references->get($item['reference_uuid']);
            $plan->items()->create([
                'reference_uuid' => $reference->uuid,
                'kode_kegiatan' => $reference->kode_kegiatan,
                'komponen' => $reference->komponen,
                'penjelasan_implementasi' => $item['penjelasan_implementasi'] ?? null,
                'uraian_belanja' => $item['uraian_belanja'],
                'bulan_dianggarkan' => $item['bulan_dianggarkan'],
                'jumlah' => $item['jumlah'],
                'satuan' => $item['satuan'],
                'harga_satuan' => $item['harga_satuan'],
                'total' => $this->validationService->calculateTotal((int) $item['jumlah'], (int) $item['harga_satuan']),
                'kode_rekening_belanja' => $reference->kode_rekening_belanja,
            ]);
        }
    }

    private function persistValidation(RkasPlan $plan, string $userUuid, bool $promoteStatus = true)
    {
        $plan->load(['referenceSet', 'items.reference']);
        $findings = $this->validationService->inspect($plan);
        $plan->validations()->delete();
        foreach ($findings as $finding) {
            $plan->validations()->create($finding);
        }

        $hasErrors = $findings->contains(fn (array $finding) => $finding['severity'] === 'error');
        $plan->update([
            'status' => $promoteStatus ? ($hasErrors ? RkasPlan::STATUS_DRAFT : RkasPlan::STATUS_VALIDATED) : $plan->status,
            'validated_at' => $promoteStatus && ! $hasErrors ? now() : ($promoteStatus ? null : $plan->validated_at),
            'updated_by' => $userUuid,
        ]);

        return $findings;
    }

    private function ensureExportable(RkasPlan $plan): bool
    {
        $plan->load(['referenceSet', 'items.reference', 'validations']);
        if ($plan->validations->isEmpty()) {
            $this->persistValidation($plan, auth()->user()->uuid, true);
            $plan->load('validations');
        }

        return in_array($plan->status, [
            RkasPlan::STATUS_VALIDATED,
            RkasPlan::STATUS_READY,
            RkasPlan::STATUS_SUBMITTED,
            RkasPlan::STATUS_APPROVED,
            RkasPlan::STATUS_ARCHIVED,
        ], true) && ! $plan->validations->contains(fn ($finding) => $finding->severity === 'error');
    }

    private function referenceSetFor(string $uuid, bool $active): RkasReferenceSet
    {
        $query = RkasReferenceSet::query()->whereKey($uuid);
        if ($active) {
            $query->where('is_active', true);
        }

        $set = $query->first();
        abort_unless($set, 422, 'Paket referensi ARKAS tidak aktif atau tidak ditemukan.');

        return $set;
    }

    private function rejectDuplicateReferenceCodes($rows): void
    {
        $duplicates = $rows
            ->groupBy(fn (array $row) => mb_strtolower(trim((string) ($row['kode_kegiatan'] ?? ''))))
            ->filter(fn ($group, string $code) => $code !== '' && $group->count() > 1)
            ->keys()
            ->values();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'Berkas referensi memiliki kode kegiatan duplikat: '.$duplicates->take(5)->implode(', ').'.',
            ]);
        }
    }

    private function ensureTransitionAllowed(RkasPlan $plan, string $target): void
    {
        $allowed = match ($target) {
            RkasPlan::STATUS_READY => [RkasPlan::STATUS_VALIDATED],
            RkasPlan::STATUS_SUBMITTED => [RkasPlan::STATUS_VALIDATED, RkasPlan::STATUS_READY],
            RkasPlan::STATUS_APPROVED, RkasPlan::STATUS_REVISION => [RkasPlan::STATUS_SUBMITTED],
            RkasPlan::STATUS_ARCHIVED => [RkasPlan::STATUS_APPROVED],
            default => [],
        };
        abort_unless(in_array($plan->status, $allowed, true), 422, 'Perubahan status RKAS tidak mengikuti alur yang diizinkan.');
    }

    private function ensureEditable(RkasPlan $plan): void
    {
        abort_if(in_array($plan->status, [
            RkasPlan::STATUS_READY,
            RkasPlan::STATUS_SUBMITTED,
            RkasPlan::STATUS_APPROVED,
            RkasPlan::STATUS_ARCHIVED,
        ], true), 422, 'RKAS yang sudah disiapkan untuk input, dicatat submitted, disetujui, atau diarsipkan tidak dapat diubah.');
    }

    private function ensureCanReview(Request $request): void
    {
        abort_unless($this->canReview($request->user()), 403);
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->canManage($request->user()), 403);
    }

    private function ensureCanManageReferences(Request $request): void
    {
        abort_unless($this->canManageReferences($request->user()), 403);
    }

    private function canManage(?object $user): bool
    {
        return $user && ($user->isAdmin() || $user->canAccess('manage_keuangan'));
    }

    private function canReview(?object $user): bool
    {
        return $this->canManage($user) || ($user && in_array($user->access, ['kepala', 'kepala_sekolah'], true));
    }

    private function canManageReferences(?object $user): bool
    {
        return $user && $user->isAdmin();
    }
}
