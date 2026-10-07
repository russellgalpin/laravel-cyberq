<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApnsEnvironment;
use App\Enums\LiveActivityTokenKind;
use App\Http\Controllers\Controller;
use App\Models\LiveActivityToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The iPhone app registers the push tokens iOS gives its Live Activities, so the
 * server can start, update and end them.
 */
class LiveActivityTokensController extends Controller
{
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'regex:/^[0-9a-f]{32,200}$/i'],
            'kind' => ['required', Rule::enum(LiveActivityTokenKind::class)],
            'environment' => ['required', Rule::enum(ApnsEnvironment::class)],
            'cook_id' => ['required_if:kind,'.LiveActivityTokenKind::Update->value, 'nullable', 'integer', 'exists:cooks,id'],
        ]);

        $isStartToken = $validated['kind'] === LiveActivityTokenKind::Start->value;

        if ($isStartToken) {
            LiveActivityToken::query()
                ->where('user_id', $request->user()->id)
                ->where('kind', LiveActivityTokenKind::Start)
                ->where('environment', $validated['environment'])
                ->where('token', '!=', $validated['token'])
                ->delete();
        }

        LiveActivityToken::query()->updateOrCreate(
            ['token' => strtolower($validated['token'])],
            [
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $request->user()->currentPersonalAccessTokenId(),
                'kind' => $validated['kind'],
                'environment' => $validated['environment'],
                'cook_id' => $isStartToken ? null : $validated['cook_id'],
            ],
        );

        return response()->noContent();
    }

    public function destroy(Request $request, string $token): Response
    {
        LiveActivityToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', strtolower($token))
            ->delete();

        return response()->noContent();
    }
}
