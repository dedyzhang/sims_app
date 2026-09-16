<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\DemoProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoProvisionController extends Controller
{
    public function __construct(private readonly DemoProvisioningService $service) {}

    public function store(Request $request): JsonResponse
    {
        $correlationId = (string) $request->attributes->get('demo_correlation_id', (string) Str::uuid());
        $idempotencyKey = (string) $request->attributes->get('demo_idempotency_key', '');
        $bodyHash = (string) $request->attributes->get('demo_body_hash', '');
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        $result = $this->service->provision($payload, $idempotencyKey, $bodyHash, $correlationId);

        return response()->json($result['body'], $result['status']);
    }

    public function revoke(Request $request, string $demoAccess): JsonResponse
    {
        $correlationId = (string) $request->attributes->get('demo_correlation_id', (string) Str::uuid());

        $result = DB::transaction(fn () => $this->service->revoke($demoAccess, $correlationId));

        return response()->json($result['body'], $result['status']);
    }

    public function resendOnboarding(Request $request, string $demoAccess): JsonResponse
    {
        $correlationId = (string) $request->attributes->get('demo_correlation_id', (string) Str::uuid());
        $result = $this->service->resendOnboarding($demoAccess, $correlationId);

        return response()->json($result['body'], $result['status']);
    }
}
