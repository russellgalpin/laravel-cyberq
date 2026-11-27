<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookResource;
use App\Models\Cook;
use App\Models\Probe;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * When a food probe's joint was wrapped, which speeds it up through the
 * stall, so the forecast needs to know.
 */
class CookWrapsController extends Controller
{
    public function update(Request $request, Cook $cook, string $probe): CookResource
    {
        $this->ensureFoodProbe($cook, $probe);

        $validated = $request->validate([
            'wrapped_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:'.CookNotesController::MAXIMUM_LENGTH],
        ]);

        $wrappedAt = isset($validated['wrapped_at']) ? Carbon::parse($validated['wrapped_at']) : now();

        if ($cook->started_at && $wrappedAt->lessThan($cook->started_at)) {
            throw ValidationException::withMessages(['wrapped_at' => 'The joint cannot be wrapped before the cook started.']);
        }

        $cook->wrap($probe, $wrappedAt);

        if ($request->has('note')) {
            $cook->noteWrap($probe, $validated['note']);
        }

        return new CookResource($cook);
    }

    public function destroy(Cook $cook, string $probe): CookResource
    {
        $this->ensureFoodProbe($cook, $probe);

        $cook->wrap($probe, null);

        return new CookResource($cook);
    }

    private function ensureFoodProbe(Cook $cook, string $probe): void
    {
        if ($probe === Probe::PIT || ! $cook->probes->contains('identifier', $probe)) {
            throw ValidationException::withMessages(['probe' => 'That is not one of this cook\'s food probes.']);
        }
    }
}
