<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApnsEnvironment;
use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The iPhone app registers here for cook alerts, with which kinds it wants.
 */
class PushDevicesController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'regex:/^[0-9a-f]{32,200}$/i'],
            'environment' => ['required', Rule::enum(ApnsEnvironment::class)],
            'pit_alerts' => ['sometimes', 'boolean'],
            'food_alerts' => ['sometimes', 'boolean'],
            'offline_alerts' => ['sometimes', 'boolean'],
        ]);

        $device = PushDevice::query()->updateOrCreate(
            ['token' => strtolower($validated['token'])],
            [
                ...$validated,
                'token' => strtolower($validated['token']),
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $request->user()->currentPersonalAccessTokenId(),
            ],
        );

        return response()->json([
            'data' => $device->fresh()->only('pit_alerts', 'food_alerts', 'offline_alerts'),
        ]);
    }

    public function destroy(Request $request, string $token): Response
    {
        PushDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('token', strtolower($token))
            ->delete();

        return response()->noContent();
    }
}
