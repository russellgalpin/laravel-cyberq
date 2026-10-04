<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

class PasskeyTokensController extends Controller
{
    public function store(Request $request, VerifyPasskey $verify): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:255'],
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ]);

        $serializedOptions = Cache::pull(PasskeyChallengesController::cacheKey($validated['challenge_id']));

        if (! $serializedOptions) {
            throw ValidationException::withMessages(['challenge_id' => 'The passkey sign-in timed out. Please try again.']);
        }

        try {
            $credential = WebAuthn::fromJson(json_encode($validated['credential']), PublicKeyCredential::class);
        } catch (Throwable) {
            throw ValidationException::withMessages(['credential' => 'Invalid credential format.']);
        }

        try {
            $passkey = $verify($credential, WebAuthn::fromJson($serializedOptions, PublicKeyCredentialRequestOptions::class));
        } catch (InvalidPasskeyException $exception) {
            throw ValidationException::withMessages(['credential' => $exception->getMessage()]);
        }

        if (! Passkeys::allowsLogin($request, $passkey)) {
            throw ValidationException::withMessages(['credential' => 'Unable to sign in with this account.']);
        }

        return TokensController::tokenResponse($passkey->user, $validated['device_name']);
    }
}
