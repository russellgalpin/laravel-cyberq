<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Support\WebAuthn;

/**
 * Starts a passkey sign-in for the app. The API has no session, so the
 * challenge is kept in the cache under an id the app sends back.
 */
class PasskeyChallengesController extends Controller
{
    public const int LIFETIME_MINUTES = 5;

    public function store(GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate();
        $challengeId = (string) Str::uuid();

        Cache::put(self::cacheKey($challengeId), WebAuthn::toJson($options), now()->addMinutes(self::LIFETIME_MINUTES));

        return response()->json([
            'challenge_id' => $challengeId,
            'options' => WebAuthn::toBrowserArray($options),
        ], 201);
    }

    public static function cacheKey(string $challengeId): string
    {
        return "passkey-challenge:{$challengeId}";
    }
}
