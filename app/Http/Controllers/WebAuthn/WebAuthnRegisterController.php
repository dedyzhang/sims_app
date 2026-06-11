<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

use function response;

class WebAuthnRegisterController
{
    /**
     * Returns a challenge to be verified by the user device.
     */
    public function options(AttestationRequest $request): Responsable
    {
        $user = auth()->user();
        if ($user && $user->must_change_password && !$user->username_customized) {
            abort(403, 'Silakan kustomisasi username Anda terlebih dahulu.');
        }

        return $request
            ->fastRegistration()
//            ->userless()
//            ->allowDuplicates()
            ->toCreate();
    }

    /**
     * Registers a device for further WebAuthn authentication.
     */
    public function register(AttestedRequest $request): Response
    {
        $user = auth()->user();
        if ($user && $user->must_change_password && !$user->username_customized) {
            abort(403, 'Silakan kustomisasi username Anda terlebih dahulu.');
        }

        $request->save();

        if ($user) {
            $user->update(['must_change_password' => false]);
        }

        return response()->noContent();
    }
}
